<?php

namespace App\Http\Requests;

use App\DashboardLayout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardLayoutRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $widgets = array_keys(app(DashboardLayout::class)->widgets($this->user()));

        return [
            'widgets' => ['required', 'array', 'size:'.count($widgets)],
            'widgets.*' => ['required', 'array:id,visible,width,height'],
            'widgets.*.id' => ['required', 'string', 'distinct:strict', Rule::in($widgets)],
            'widgets.*.visible' => ['required', 'boolean'],
            'widgets.*.width' => ['required', 'string', Rule::in(['small', 'medium', 'wide'])],
            'widgets.*.height' => ['required', 'string', Rule::in(['auto', 'compact', 'regular', 'tall'])],
        ];
    }
}
