<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HoldRoomRequest extends FormRequest
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
            'room_type_id' => ['required', 'uuid', 'exists:room_types,id'],
            'check_in'     => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out'    => ['required', 'date', 'date_format:Y-m-d', 'after:check_in'],
        ];
    }
}
