<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Admin;

use App\Domain\User\DTOs\UpdateUserStatusDTO;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserStatusRequest extends FormRequest
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
            'status' => ['required', 'string', 'in:active,suspended'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Trạng thái người dùng là bắt buộc.',
            'status.in' => 'Trạng thái chỉ có thể là active hoặc suspended.',
        ];
    }

    public function toDTO(int $userId): UpdateUserStatusDTO
    {
        return new UpdateUserStatusDTO(
            userId: $userId,
            status: (string) $this->validated('status'),
        );
    }
}
