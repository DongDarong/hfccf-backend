<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    protected function prepareForValidation(): void
    {
        $metadata = $this->input('metadata');

        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $metadata = $decoded;
            }
        }

        $this->merge([
            'type' => is_string($this->input('type')) ? strtolower(trim($this->input('type'))) : $this->input('type'),
            'title' => trim((string) $this->input('title', '')),
            'message' => trim((string) $this->input('message', '')),
            'module' => is_string($this->input('module')) ? strtolower(trim($this->input('module'))) : $this->input('module'),
            'action_url' => ($actionUrl = trim((string) $this->input('action_url', ''))) !== '' ? $actionUrl : null,
            'target_type' => is_string($this->input('target_type')) ? strtolower(trim($this->input('target_type'))) : $this->input('target_type'),
            'target_value' => ($targetValue = trim((string) $this->input('target_value', ''))) !== '' ? $targetValue : null,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['info', 'success', 'warning', 'error', 'system'])],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:65535'],
            'module' => ['required', 'string', Rule::in(['global', 'english', 'preschool', 'scholarship', 'sport'])],
            'action_url' => ['nullable', 'string', 'max:2048'],
            'metadata' => ['nullable', 'array'],
            'target_type' => ['required', 'string', Rule::in(['all', 'role', 'department', 'module', 'user'])],
            'target_value' => ['nullable', 'string', 'max:191'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $targetType = (string) $this->input('target_type');
            $targetValue = $this->input('target_value');
            $targetValue = is_string($targetValue) ? trim($targetValue) : $targetValue;
            $hasTargetValue = ! blank($targetValue);

            if ($targetType === 'all' || $targetType === 'department') {
                if ($targetType === 'all' && $hasTargetValue) {
                    $validator->errors()->add('target_value', 'The target value must be empty when targeting all users.');
                }

                if ($targetType === 'department' && ! $hasTargetValue) {
                    $validator->errors()->add('target_value', 'The target value is required when targeting a department.');
                }
            }

            if (in_array($targetType, ['role', 'module', 'user'], true) && ! $hasTargetValue) {
                $validator->errors()->add('target_value', 'The target value is required for the selected target type.');
            }

            if ($targetType === 'module' && ! in_array($targetValue, ['global', 'english', 'preschool', 'scholarship', 'sport'], true)) {
                $validator->errors()->add('target_value', 'The target value must be a valid module.');
            }

            if ($targetType === 'role' && ! \App\Models\Role::query()->where('code', $targetValue)->exists()) {
                $validator->errors()->add('target_value', 'The selected role does not exist.');
            }

            if ($targetType === 'department' && ! \App\Models\Department::query()->where('code', $targetValue)->exists()) {
                $validator->errors()->add('target_value', 'The selected department does not exist.');
            }

            if ($targetType === 'user' && ! \App\Models\User::query()->where('id', $targetValue)->exists()) {
                $validator->errors()->add('target_value', 'The selected user does not exist.');
            }
        });
    }
}
