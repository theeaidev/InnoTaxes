<?php

namespace App\Http\Requests\Aeat;

use App\Services\Aeat\AeatDocumentHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAeatFiscalDataRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'taxpayer_nif' => AeatDocumentHelper::sanitizeNif($this->input('taxpayer_nif')),
            'auth_nif' => AeatDocumentHelper::sanitizeNif($this->input('auth_nif')),
            'reference_code' => AeatDocumentHelper::sanitizeReference($this->input('reference_code')),
            'fecha' => AeatDocumentHelper::sanitizeDate($this->input('fecha')),
            'soporte' => AeatDocumentHelper::sanitizeSupport($this->input('soporte')),
            'pdp' => strtoupper((string) $this->input('pdp', 'S')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->getKey();
        $certificateExists = Rule::exists('aeat_certificate_profiles', 'id')->where('user_id', $userId);

        return [
            'auth_method' => ['required', Rule::in(['certificate', 'reference', 'clave_movil'])],
            'taxpayer_nif' => ['required', 'string', 'max:16'],
            'auth_nif' => ['nullable', 'string', 'max:16'],
            'pdp' => ['required', Rule::in(['S', 'N'])],
            'precheck_certificate_profile_id' => ['nullable', $certificateExists],
            'certificate_profile_id' => ['nullable', $certificateExists],
            'reference_code' => ['nullable', 'string', 'size:6'],
            'fecha' => ['nullable', 'string', 'max:10'],
            'soporte' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $authMethod = $this->input('auth_method');
            $authNif = $this->input('auth_nif');

            if ($authMethod === 'certificate' && ! $this->filled('certificate_profile_id')) {
                $validator->errors()->add('certificate_profile_id', 'Select a certificate profile to use certificate-based access.');
            }

            if (in_array($authMethod, ['reference', 'clave_movil'], true) && ! $authNif) {
                $validator->errors()->add('auth_nif', 'The authentication NIF is required for this AEAT flow.');
            }

            if ($authMethod === 'reference' && ! $this->filled('reference_code')) {
                $validator->errors()->add('reference_code', 'Enter the AEAT reference code to continue.');
            }

            if ($authMethod === 'clave_movil' && $authNif) {
                if (AeatDocumentHelper::looksLikeNie($authNif) && ! $this->filled('soporte')) {
                    $validator->errors()->add('soporte', 'The support number is required when the authentication document is a NIE.');
                }

                if (! AeatDocumentHelper::looksLikeNie($authNif) && ! $this->filled('fecha')) {
                    $validator->errors()->add('fecha', 'The document date is required when the authentication document is a DNI.');
                }
            }
        });
    }
}
