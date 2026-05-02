<?php

namespace App\Http\Requests\Property;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'name_locale' => ['sometimes', 'string', 'in:es,en,fr,de,it,pt'],
            'address' => ['required', 'string', 'max:500'],
            'google_maps_place_id' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'area_house_m2' => ['nullable', 'numeric', 'min:0'],
            'area_land_m2' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'description_locale' => ['sometimes', 'string', 'in:es,en,fr,de,it,pt'],
            'min_stay_default' => ['nullable', 'integer', 'min:1'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'policies_config' => ['nullable', 'array'],
            'policies_config.cancellation_policy' => ['nullable', 'string', 'in:flexible,moderate,strict'],
            'policies_config.deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'policies_config.house_rules' => ['nullable', 'array'],
            'images' => ['nullable', 'array', 'max:50'],
            'images.*.url' => ['required_with:images', 'url', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la propiedad es obligatorio.',
            'address.required' => 'La dirección es obligatoria.',
            'images.*.url.required_with' => 'Cada imagen debe tener una URL válida.',
            'latitude.between' => 'La latitud debe estar entre -90 y 90.',
            'longitude.between' => 'La longitud debe estar entre -180 y 180.',
        ];
    }
}
