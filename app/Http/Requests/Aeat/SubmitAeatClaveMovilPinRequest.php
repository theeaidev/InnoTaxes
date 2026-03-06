<?php

namespace App\Http\Requests\Aeat;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAeatClaveMovilPinRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pin' => ['required', 'digits:6'],
        ];
    }
}
