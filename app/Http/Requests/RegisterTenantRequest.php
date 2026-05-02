<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterTenantRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'subdomain' => 'required|string|alpha_dash|unique:tenants,subdomain',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'default_locale' => 'nullable|string|size:2',
        ];
    }

    /**
     * Custom messages
     */
    public function messages(): array
    {
        return [
            'subdomain.unique' => 'Este nombre de subdominio ya está en uso.',
            'email.unique' => 'Este email ya está registrado.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }
}
