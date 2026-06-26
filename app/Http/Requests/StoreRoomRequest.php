<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'room_number'     => ['required', 'string', 'unique:rooms,room_number'],
            'type'            => ['required', 'string', 'in:standard,deluxe,suite,presidential'],
            'floor'           => ['required', 'integer', 'min:1'],
            'capacity'        => ['required', 'integer', 'min:1'],
            'price_per_night' => ['required', 'numeric', 'min:0'],
            'description'     => ['nullable', 'string'],
            'facilities'      => ['nullable', 'array'],
        ];
    }
}
