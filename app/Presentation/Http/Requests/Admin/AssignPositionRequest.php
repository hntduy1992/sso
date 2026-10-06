<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Admin;

use App\Domain\User\DTOs\AssignPositionDTO;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignPositionRequest extends FormRequest
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
        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'position_type_id' => ['required', 'integer', 'exists:position_types,id'],
            'started_at' => ['required', 'date_format:Y-m-d'],
            'is_primary' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'department_id.required' => 'Vui lòng chọn phòng ban/tổ chuyên môn.',
            'department_id.exists' => 'Phòng ban/tổ chuyên môn không hợp lệ.',
            'position_type_id.required' => 'Vui lòng chọn chức danh.',
            'position_type_id.exists' => 'Chức danh không hợp lệ.',
            'started_at.required' => 'Vui lòng chọn ngày bắt đầu nhiệm kỳ.',
            'started_at.date_format' => 'Ngày bắt đầu phải theo định dạng YYYY-MM-DD.',
            'notes.max' => 'Ghi chú không được vượt quá 500 ký tự.',
        ];
    }

    public function toDTO(int $userId): AssignPositionDTO
    {
        return new AssignPositionDTO(
            userId: $userId,
            departmentId: (int) $this->validated('department_id'),
            positionTypeId: (int) $this->validated('position_type_id'),
            startedAt: (string) $this->validated('started_at'),
            isPrimary: (bool) $this->validated('is_primary', false),
            notes: $this->has('notes') ? (string) $this->validated('notes') : null,
        );
    }
}
