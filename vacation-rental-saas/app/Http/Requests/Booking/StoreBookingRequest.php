<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'guest_email' => ['required', 'email', 'max:255'],
            'guest_phone' => ['required', 'string', 'max:50'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'num_guests' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'currency' => ['sometimes', 'string', 'size:3', 'in:EUR,USD,GBP'],
            'guest_details' => ['nullable', 'array'],
            'guest_details.first_name' => ['required_with:guest_details', 'string', 'max:100'],
            'guest_details.last_name' => ['required_with:guest_details', 'string', 'max:100'],
            'guest_details.notes' => ['nullable', 'string', 'max:1000'],
            'guest_notes' => ['nullable', 'string', 'max:1000'],
            'guest_notes_locale' => ['sometimes', 'string', 'in:es,en,fr,de,it,pt'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'property_id.required' => 'La propiedad es obligatoria.',
            'property_id.exists' => 'La propiedad seleccionada no existe.',
            'guest_email.required' => 'El email del huésped es obligatorio.',
            'guest_email.email' => 'El email del huésped no es válido.',
            'guest_phone.required' => 'El teléfono del huésped es obligatorio.',
            'check_in.required' => 'La fecha de check-in es obligatoria.',
            'check_in.after_or_equal' => 'La fecha de check-in debe ser hoy o en el futuro.',
            'check_out.required' => 'La fecha de check-out es obligatoria.',
            'check_out.after' => 'La fecha de check-out debe ser posterior al check-in.',
            'num_guests.min' => 'Debe haber al menos 1 huésped.',
            'num_guests.max' => 'Número máximo de huéspedes excedido.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Verificar que las fechas no sean demasiado lejanas (máximo 2 años)
            $checkIn = \Carbon\Carbon::parse($this->check_in);
            $checkOut = \Carbon\Carbon::parse($this->check_out);
            
            if ($checkIn->diffInYears(now()) > 2) {
                $validator->errors()->add('check_in', 'No se pueden hacer reservas con más de 2 años de antelación.');
            }

            // Verificar duración máxima de estancia (ej: 90 días)
            if ($checkIn->diffInDays($checkOut) > 90) {
                $validator->errors()->add('check_out', 'La estancia máxima permitida es de 90 días.');
            }
        });
    }
}
