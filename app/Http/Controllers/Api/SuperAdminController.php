<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Booking;
use App\Models\Property;
use App\Models\SystemLog;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SuperAdminController extends Controller
{
    /**
     * Solo usuarios SuperAdmin pueden acceder a estos métodos
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = Auth::guard('api')->user();
            
            if (!$user || !$user->is_super_admin) {
                return response()->json([
                    'message' => 'Unauthorized. SuperAdmin access required.'
                ], 403);
            }
            
            return $next($request);
        });
    }

    /**
     * Dashboard global con métricas de todos los tenants
     */
    public function dashboard()
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $suspendedTenants = Tenant::where('status', 'suspended')->count();
        
        $totalUsers = User::whereNotNull('tenant_id')->count();
        $totalProperties = Property::count();
        $totalBookings = Booking::count();
        $totalRevenue = \App\Models\Payment::where('status', 'succeeded')->sum('amount');

        // Tenants por estado de suscripción
        $subscriptionsStats = Tenant::selectRaw('
            CASE 
                WHEN subscribed_until IS NULL THEN "no_subscription"
                WHEN subscribed_until < NOW() THEN "expired"
                ELSE "active"
            END as status,
            COUNT(*) as count
        ')
        ->groupBy('status')
        ->get();

        // Top 10 tenants por ingresos
        $topTenants = \App\Models\Payment::join('tenants', 'payments.tenant_id', '=', 'tenants.id')
            ->where('payments.status', 'succeeded')
            ->selectRaw('tenants.id, tenants.name, tenants.subdomain, SUM(payments.amount) as total_revenue')
            ->groupBy('tenants.id', 'tenants.name', 'tenants.subdomain')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        // Errores recientes (últimas 24h)
        $recentErrors = SystemLog::where('level', 'error')
            ->where('created_at', '>=', now()->subHours(24))
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'tenant_id', 'message', 'context', 'created_at']);

        return response()->json([
            'success' => true,
            'dashboard' => [
                'tenants' => [
                    'total' => $totalTenants,
                    'active' => $activeTenants,
                    'suspended' => $suspendedTenants
                ],
                'users' => $totalUsers,
                'properties' => $totalProperties,
                'bookings' => $totalBookings,
                'revenue' => [
                    'total' => $totalRevenue,
                    'currency' => 'EUR'
                ],
                'subscriptions' => $subscriptionsStats,
                'top_tenants' => $topTenants,
                'recent_errors' => $recentErrors
            ]
        ]);
    }

    /**
     * Listar todos los tenants
     */
    public function tenants(Request $request)
    {
        $query = Tenant::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('subdomain', 'like', "%{$search}%");
            });
        }

        $tenants = $query->withCount(['properties', 'users', 'bookings'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'tenants' => $tenants
        ]);
    }

    /**
     * Ver detalle de un tenant
     */
    public function tenantDetail(int $tenantId)
    {
        $tenant = Tenant::with(['users', 'properties', 'stripeConfig'])->findOrFail($tenantId);

        // Métricas del tenant
        $totalBookings = Booking::where('tenant_id', $tenantId)->count();
        $totalRevenue = \App\Models\Payment::where('tenant_id', $tenantId)
            ->where('status', 'succeeded')
            ->sum('amount');
        
        $recentLogs = SystemLog::where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $recentAudits = AuditLog::where('tenant_id', $tenantId)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'tenant' => $tenant,
            'metrics' => [
                'total_bookings' => $totalBookings,
                'total_revenue' => $totalRevenue
            ],
            'recent_logs' => $recentLogs,
            'recent_audits' => $recentAudits
        ]);
    }

    /**
     * Impersonar usuario de un tenant (Login as)
     */
    public function impersonate(Request $request, int $tenantId, int $userId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        $user = User::where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // Registrar en audit log
        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => Auth::id(),
            'action' => 'login_as',
            'metadata' => [
                'impersonated_user_id' => $userId,
                'impersonated_user_email' => $user->email,
                'super_admin_id' => Auth::id(),
                'super_admin_email' => Auth::user()->email
            ],
            'ip_address' => $request->ip()
        ]);

        Log::info('SuperAdmin impersonation', [
            'super_admin_id' => Auth::id(),
            'impersonated_user_id' => $userId,
            'tenant_id' => $tenantId
        ]);

        // Generar token temporal para el usuario impersonado
        // Nota: En producción, considerar usar un sistema de tokens temporales con expiración corta
        $token = $user->createToken('impersonation-' . time())->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Impersonation successful',
            'user' => $user,
            'tenant' => $tenant,
            'token' => $token,
            'warning' => 'You are now acting as this user. All actions will be logged.'
        ]);
    }

    /**
     * Suspender/Activar tenant
     */
    public function toggleTenantStatus(Request $request, int $tenantId)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,suspended',
            'reason' => 'nullable|string|max:500'
        ]);

        $tenant = Tenant::findOrFail($tenantId);
        $oldStatus = $tenant->status;
        
        $tenant->update([
            'status' => $validated['status']
        ]);

        // Registrar acción
        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => Auth::id(),
            'action' => 'tenant_status_change',
            'metadata' => [
                'old_status' => $oldStatus,
                'new_status' => $validated['status'],
                'reason' => $validated['reason'] ?? null
            ],
            'ip_address' => $request->ip()
        ]);

        Log::info('Tenant status changed', [
            'tenant_id' => $tenantId,
            'old_status' => $oldStatus,
            'new_status' => $validated['status'],
            'super_admin_id' => Auth::id()
        ]);

        return response()->json([
            'success' => true,
            'message' => "Tenant {$validated['status']} successfully",
            'tenant' => $tenant->fresh()
        ]);
    }

    /**
     * Ver logs del sistema filtrados por tenant
     */
    public function logs(Request $request, ?int $tenantId = null)
    {
        $validated = $request->validate([
            'level' => 'nullable|in:error,warning,info',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255'
        ]);

        $query = SystemLog::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if (isset($validated['level'])) {
            $query->where('level', $validated['level']);
        }

        if (isset($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (isset($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        if (isset($validated['search'])) {
            $query->where('message', 'like', "%{$validated['search']}%");
        }

        $logs = $query->orderByDesc('created_at')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'logs' => $logs
        ]);
    }

    /**
     * Ver audit logs
     */
    public function auditLogs(Request $request, ?int $tenantId = null)
    {
        $validated = $request->validate([
            'action' => 'nullable|string',
            'user_id' => 'nullable|integer',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from'
        ]);

        $query = AuditLog::with('user');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if (isset($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        if (isset($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        if (isset($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (isset($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        $logs = $query->orderByDesc('created_at')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'audit_logs' => $logs
        ]);
    }
}
