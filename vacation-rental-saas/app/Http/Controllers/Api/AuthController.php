<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterTenantRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Registro de nuevo tenant (propietario) con onboarding inicial
     */
    public function register(RegisterTenantRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            // Crear Tenant
            $tenant = Tenant::create([
                'name' => $request->validated('tenant_name'),
                'subdomain' => Str::slug($request->validated('subdomain')),
                'status' => 'trial',
                'default_locale' => $request->validated('default_locale', 'es'),
                'branding' => [
                    'primary_color' => '#3B82F6',
                    'secondary_color' => '#10B981',
                    'logo_url' => null,
                    'favicon_url' => null,
                ],
                'subscribed_until' => now()->addDays(14), // 14 días de prueba
            ]);

            // Crear Usuario Propietario
            $user = User::create([
                'tenant_id' => $tenant->id,
                'email' => $request->validated('email'),
                'password' => Hash::make($request->validated('password')),
                'role' => 'owner',
                'is_super_admin' => false,
                'preferences' => [
                    'locale' => $request->validated('default_locale', 'es'),
                    'timezone' => $request->validated('timezone', 'Europe/Madrid'),
                ],
            ]);

            // Crear plantillas de email por defecto
            $this->createDefaultEmailTemplates($tenant);

            DB::commit();

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message' => 'Registro completado exitosamente',
                'tenant' => $tenant,
                'user' => $user,
                'access_url' => "https://{$tenant->subdomain}.tudominio.com",
                'token' => $token,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error en registro de tenant', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al completar el registro',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Login de usuario
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Credenciales inválidas',
            ], 401);
        }

        $user = Auth::user();
        
        // Verificar estado del tenant
        if ($user->tenant && $user->tenant->status !== 'active' && $user->tenant->status !== 'trial') {
            Auth::logout();
            return response()->json([
                'message' => 'Tu cuenta está suspendida. Contacta con soporte.',
            ], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'user' => $user,
            'tenant' => $user->tenant,
            'token' => $token,
        ]);
    }

    /**
     * Logout
     */
    public function logout(): JsonResponse
    {
        Auth::user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout exitoso',
        ]);
    }

    /**
     * Obtener usuario actual
     */
    public function me(): JsonResponse
    {
        $user = Auth::user();
        
        return response()->json([
            'user' => $user,
            'tenant' => $user->tenant,
            'permissions' => $this->getUserPermissions($user),
        ]);
    }

    /**
     * Crear plantillas de email por defecto para un tenant
     */
    private function createDefaultEmailTemplates(Tenant $tenant): void
    {
        $templates = [
            'welcome' => [
                'subject_key' => 'email.welcome.subject',
                'body_key' => 'email.welcome.body',
            ],
            'check_in' => [
                'subject_key' => 'email.check_in.subject',
                'body_key' => 'email.check_in.body',
            ],
            'check_out' => [
                'subject_key' => 'email.check_out.subject',
                'body_key' => 'email.check_out.body',
            ],
            'review_request' => [
                'subject_key' => 'email.review_request.subject',
                'body_key' => 'email.review_request.body',
            ],
            'payment_reminder' => [
                'subject_key' => 'email.payment_reminder.subject',
                'body_key' => 'email.payment_reminder.body',
            ],
        ];

        foreach ($templates as $key => $template) {
            $emailTemplate = \App\Models\EmailTemplate::create([
                'tenant_id' => $tenant->id,
                'key' => $key,
                'is_active' => true,
            ]);

            // Crear traducciones por defecto en español e inglés
            $defaultTranslations = [
                'es' => [
                    'email.welcome.subject' => '¡Bienvenido a tu reserva!',
                    'email.welcome.body' => 'Gracias por reservar con nosotros. Pronto recibirás más información.',
                    'email.check_in.subject' => 'Instrucciones de Check-in',
                    'email.check_in.body' => 'Tu check-in es mañana. Aquí tienes las instrucciones...',
                    'email.check_out.subject' => 'Recordatorio de Check-out',
                    'email.check_out.body' => 'Hoy es tu día de check-out. Hora límite: 11:00h',
                    'email.review_request.subject' => '¿Cómo fue tu estancia?',
                    'email.review_request.body' => 'Esperamos que hayas disfrutado. ¿Nos dejas una reseña?',
                    'email.payment_reminder.subject' => 'Recordatorio de Pago Pendiente',
                    'email.payment_reminder.body' => 'Tu pago sigue pendiente. Por favor, complétalo.',
                ],
                'en' => [
                    'email.welcome.subject' => 'Welcome to your booking!',
                    'email.welcome.body' => 'Thank you for booking with us. You will receive more information soon.',
                    'email.check_in.subject' => 'Check-in Instructions',
                    'email.check_in.body' => 'Your check-in is tomorrow. Here are the instructions...',
                    'email.check_out.subject' => 'Check-out Reminder',
                    'email.check_out.body' => 'Today is your check-out day. Deadline: 11:00 AM',
                    'email.review_request.subject' => 'How was your stay?',
                    'email.review_request.body' => 'We hope you enjoyed. Would you leave us a review?',
                    'email.payment_reminder.subject' => 'Pending Payment Reminder',
                    'email.payment_reminder.body' => 'Your payment is still pending. Please complete it.',
                ],
            ];

            foreach ($defaultTranslations as $locale => $translations) {
                \App\Models\Translation::create([
                    'tenant_id' => $tenant->id,
                    'entity_type' => 'email_template',
                    'entity_id' => $emailTemplate->id,
                    'field_key' => 'subject',
                    'locale' => $locale,
                    'value' => $translations[$template['subject_key']],
                    'is_machine_translated' => false,
                ]);

                \App\Models\Translation::create([
                    'tenant_id' => $tenant->id,
                    'entity_type' => 'email_template',
                    'entity_id' => $emailTemplate->id,
                    'field_key' => 'body',
                    'locale' => $locale,
                    'value' => $translations[$template['body_key']],
                    'is_machine_translated' => false,
                ]);
            }
        }
    }

    /**
     * Obtener permisos según rol
     */
    private function getUserPermissions(User $user): array
    {
        $permissions = [];

        if ($user->is_super_admin) {
            $permissions = ['*'];
        } elseif ($user->role === 'owner') {
            $permissions = [
                'properties.*',
                'bookings.*',
                'payments.*',
                'settings.*',
                'translations.*',
                'emails.*',
            ];
        } elseif ($user->role === 'staff') {
            $permissions = [
                'properties.read',
                'bookings.*',
                'payments.read',
            ];
        }

        return $permissions;
    }
}
