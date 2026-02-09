<?php

namespace App\Http\Requests;

use Exception;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;

class GAdsConversionsEventRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'gclid' => ['nullable', 'string', 'required_without_all:click_id'],
            'click_id' => ['nullable', 'string', 'required_without_all:gclid'],
            'event' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9 _\-.]+$/',],
            'value' => ['nullable', 'numeric'],
            'currency' => ['nullable', 'string', 'max:3', 'regex:/^[A-Z]{3}$/'],
            'time' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if (!is_numeric($value) && !$this->isValidDateTime($value)) {
                        $fail("attribute must be timestamp or in the format Y-m-d H:i:s.");
                    }
                },
            ]
        ];
    }

    protected function isValidDateTime($value): bool
    {
        try {
            Carbon::parse($value);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json($validator->errors(), 412));
    }
}
