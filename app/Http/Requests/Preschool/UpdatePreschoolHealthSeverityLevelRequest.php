<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Validation\Rule;

class UpdatePreschoolHealthSeverityLevelRequest extends StorePreschoolHealthSeverityLevelRequest
{
    public function rules(): array
    {
        $severity = $this->route('severity');
        $severityId = is_object($severity) ? $severity->id : $severity;

        return [
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:64', Rule::unique('preschool_health_severity_levels', 'code')->ignore($severityId)],
            'priority' => ['required', 'integer', 'min:0', 'max:999'],
            'color' => ['nullable', 'string', 'max:32'],
            'requires_acknowledgment' => ['required', 'boolean'],
            'triggers_notification' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
