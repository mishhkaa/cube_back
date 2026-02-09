<?php

namespace App\Http\Requests;

use App\Models\FacebookPixel;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class FbCApiSiteEventRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'event_name' => [
                'required',
                'string',
                'not_regex:/\s/'
            ],
            'partner_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value) {
                    (new FacebookPixel())->resolveRouteBinding($value);
                },
            ],
            'event_source_url' => 'required|string',
            'user_data' => 'required|array',
            'custom_data' => 'array',
            'fbclid' => 'string',
            'event_id' => 'string'
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_data' => $this->post('user_data', []) + ['client_ip_address' => $this->ip()],
            'custom_data' => $this->post('custom_data') ?: []
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json($validator->errors(), 412));
    }
}
