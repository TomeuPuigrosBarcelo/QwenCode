<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Payment;
use App\Models\BookingRule;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Dashboard principal del propietario
     */
    public function index(Request $request)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        // Validar parámetros de fecha
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from'
        ]);

        $dateFrom = $validated['date_from'] ? Carbon::parse($validated['date_from']) : now()->startOfMonth();
        $dateTo = $validated['date_to'] ? Carbon::parse($validated['date_to']) : now()->endOfMonth();

        // Reservas próximas (próximos 7 días)
        $upcomingBookings = Booking::where('tenant_id', $tenantId)
            ->whereIn('status', ['confirmed', 'pending', 'checked_in'])
            ->whereDate('check_in', '<=', now()->addDays(7))
            ->whereDate('check_out', '>=', now())
            ->with(['property', 'payments'])
            ->orderBy('check_in')
            ->limit(10)
            ->get();

        // Tareas pendientes (check-ins hoy, check-outs hoy)
        $checkInsToday = Booking::where('tenant_id', $tenantId)
            ->where('status', 'confirmed')
            ->whereDate('check_in', today())
            ->count();

        $checkOutsToday = Booking::where('tenant_id', $tenantId)
            ->where('status', 'checked_in')
            ->whereDate('check_out', today())
            ->count();

        // Ocupación del mes actual
        $occupancyRate = $this->calculateOccupancyRate($tenantId, now());

        // Ingresos del mes
        $monthRevenue = Payment::where('tenant_id', $tenantId)
            ->where('status', 'succeeded')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('amount');

        // Ingresos del año
        $yearRevenue = Payment::where('tenant_id', $tenantId)
            ->where('status', 'succeeded')
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        return response()->json([
            'success' => true,
            'dashboard' => [
                'upcoming_bookings' => $upcomingBookings,
                'check_ins_today' => $checkInsToday,
                'check_outs_today' => $checkOutsToday,
                'occupancy_rate' => $occupancyRate,
                'revenue' => [
                    'month' => $monthRevenue,
                    'year' => $yearRevenue,
                    'currency' => 'EUR'
                ],
                'period' => [
                    'from' => $dateFrom->toDateString(),
                    'to' => $dateTo->toDateString()
                ]
            ]
        ]);
    }

    /**
     * Estadísticas detalladas
     */
    public function stats(Request $request)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $validated = $request->validate([
            'period' => 'nullable|in:week,month,year,custom',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from'
        ]);

        $period = $validated['period'] ?? 'month';
        
        // Determinar rango de fechas
        if ($period === 'custom' && isset($validated['date_from'], $validated['date_to'])) {
            $dateFrom = Carbon::parse($validated['date_from']);
            $dateTo = Carbon::parse($validated['date_to']);
        } else {
            switch ($period) {
                case 'week':
                    $dateFrom = now()->startOfWeek();
                    $dateTo = now()->endOfWeek();
                    break;
                case 'year':
                    $dateFrom = now()->startOfYear();
                    $dateTo = now()->endOfYear();
                    break;
                case 'month':
                default:
                    $dateFrom = now()->startOfMonth();
                    $dateTo = now()->endOfMonth();
            }
        }

        // Total reservas en periodo
        $totalBookings = Booking::where('tenant_id', $tenantId)
            ->whereBetween('check_in', [$dateFrom, $dateTo])
            ->count();

        // Reservas por estado
        $bookingsByStatus = Booking::where('tenant_id', $tenantId)
            ->whereBetween('check_in', [$dateFrom, $dateTo])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        // Ingresos totales
        $totalRevenue = Payment::where('tenant_id', $tenantId)
            ->where('status', 'succeeded')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('amount');

        // Ingresos por método de pago
        $revenueByProvider = Payment::where('tenant_id', $tenantId)
            ->where('status', 'succeeded')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('provider, SUM(amount) as total')
            ->groupBy('provider')
            ->get();

        // Propiedades más reservadas
        $topProperties = Booking::where('tenant_id', $tenantId)
            ->whereBetween('check_in', [$dateFrom, $dateTo])
            ->selectRaw('property_id, COUNT(*) as bookings_count, SUM(total_amount) as revenue')
            ->groupBy('property_id')
            ->orderByDesc('bookings_count')
            ->limit(5)
            ->with('property')
            ->get();

        // Tasa de cancelación
        $cancelledBookings = Booking::where('tenant_id', $tenantId)
            ->where('status', 'cancelled')
            ->whereBetween('check_in', [$dateFrom, $dateTo])
            ->count();
        
        $cancellationRate = $totalBookings > 0 
            ? round(($cancelledBookings / $totalBookings) * 100, 2) 
            : 0;

        return response()->json([
            'success' => true,
            'stats' => [
                'period' => [
                    'from' => $dateFrom->toDateString(),
                    'to' => $dateTo->toDateString()
                ],
                'bookings' => [
                    'total' => $totalBookings,
                    'by_status' => $bookingsByStatus,
                    'cancellation_rate' => $cancellationRate
                ],
                'revenue' => [
                    'total' => $totalRevenue,
                    'by_provider' => $revenueByProvider,
                    'currency' => 'EUR'
                ],
                'top_properties' => $topProperties
            ]
        ]);
    }

    /**
     * Calcular tasa de ocupación para un mes
     */
    private function calculateOccupancyRate(int $tenantId, Carbon $date): float
    {
        $startOfMonth = clone $date;
        $startOfMonth->startOfMonth();
        
        $endOfMonth = clone $date;
        $endOfMonth->endOfMonth();
        
        $totalDays = $startOfMonth->daysInMonth;
        $properties = Property::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        if ($properties === 0) {
            return 0;
        }

        $totalAvailableNights = $totalDays * $properties;

        // Noches ocupadas
        $occupiedNights = Booking::where('tenant_id', $tenantId)
            ->whereIn('status', ['confirmed', 'checked_in', 'completed'])
            ->where(function ($query) use ($startOfMonth, $endOfMonth) {
                $query->where(function ($q) use ($startOfMonth, $endOfMonth) {
                    $q->whereDate('check_in', '<=', $endOfMonth)
                      ->whereDate('check_out', '>=', $startOfMonth);
                });
            })
            ->get()
            ->sum(function ($booking) use ($startOfMonth, $endOfMonth) {
                $checkIn = Carbon::parse($booking->check_in);
                $checkOut = Carbon::parse($booking->check_out);
                
                // Intersección con el mes
                $effectiveCheckIn = $checkIn->lt($startOfMonth) ? clone $startOfMonth : $checkIn;
                $effectiveCheckOut = $checkOut->gt($endOfMonth) ? clone $endOfMonth : $checkOut;
                
                return max(0, $effectiveCheckIn->diffInDays($effectiveCheckOut));
            });

        return $totalAvailableNights > 0 
            ? round(($occupiedNights / $totalAvailableNights) * 100, 2) 
            : 0;
    }
}
