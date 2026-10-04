<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    public function rules(): array
    {
        return ['enabled' => ['required', 'boolean'], 'host' => ['required_if:enabled,1', 'nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_.:\[\]-]+$/'], 'port' => ['required', 'integer', 'between:1,65535'], 'security' => ['required', Rule::in(['starttls', 'smtps', 'none'])], 'username' => ['nullable', 'string', 'max:255'], 'password' => ['nullable', 'string', 'max:1024'], 'clear_password' => ['sometimes', 'boolean'], 'from_address' => ['required_if:enabled,1', 'nullable', 'email', 'max:255'], 'from_name' => ['required', 'string', 'max:255']];
    }
}
