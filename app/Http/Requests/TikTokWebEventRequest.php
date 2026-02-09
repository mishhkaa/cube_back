<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class TikTokWebEventRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'event' => 'required|string|max:64|not_regex:/\s/',
            'event_id' => 'string',
            'page' => 'required|array',
            'user' => 'required|array',
            'properties' => 'array',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user' => $this->post('user', []) + ['ip' => $this->ip()]
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json($validator->errors(), 412));
    }
}
