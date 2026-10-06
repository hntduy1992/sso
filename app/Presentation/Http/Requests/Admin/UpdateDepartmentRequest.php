<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $departmentId = $this->route('department');
        if (is_object($departmentId)) {
            $departmentId = $departmentId->id;
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('departments', 'code')->ignore($departmentId),
            ],
            'type' => ['required', 'string', 'in:management_board,specialized_team'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên tổ/phòng ban là bắt buộc.',
            'code.required' => 'Mã tổ/phòng ban là bắt buộc.',
            'code.unique' => 'Mã tổ/phòng ban này đã tồn tại trong hệ thống.',
            'type.required' => 'Loại tổ chức là bắt buộc.',
            'type.in' => 'Loại tổ chức không hợp lệ.',
        ];
    }
}
