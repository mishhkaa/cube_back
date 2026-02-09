<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class XEventRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'event_id' => 'required|string',
            'identifiers' => 'required|array',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'identifiers' => $this->post('identifiers', []) + ['ip_address' => $this->ip()]
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json($validator->errors(), 412));
    }
}
