<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Admin;

use App\Application\User\Services\NewUserValidationRules;
use App\Domain\User\DTOs\CreateUserDTO;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = app(NewUserValidationRules::class);

        return [
            ...$rules->attributeRules(),
            'role' => ['required', 'string', 'in:admin,user'],
            'status' => ['required', 'string', 'in:active,suspended'],
            'password' => $rules->passwordRules(),
            'department_id' => $rules->departmentRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...app(NewUserValidationRules::class)->messages(),
            'role.in' => 'Vai trò phải là admin hoặc user.',
            'status.in' => 'Trạng thái phải là active hoặc suspended.',
        ];
    }

    public function toDTO(): CreateUserDTO
    {
        /** @var array{name: string, email: string, role: string, status: string, password: string, department_id?: int|string|null} $validated */
        $validated = $this->validated();

        return CreateUserDTO::fromArray(
            $validated,
            $validated['password'],
            isset($validated['department_id']) ? (int) $validated['department_id'] : null,
        );
    }
}
