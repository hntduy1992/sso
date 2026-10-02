<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Auth;

use App\Domain\User\DTOs\LoginDTO;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'remember' => ['sometimes', 'boolean'],
            'redirect' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Vui lòng nhập địa chỉ email.',
            'email.email' => 'Địa chỉ email không đúng định dạng.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
        ];
    }

    public function toDTO(): LoginDTO
    {
        return new LoginDTO(
            email: (string) $this->validated('email'),
            password: (string) $this->validated('password'),
            remember: (bool) $this->boolean('remember'),
            redirect: $this->validated('redirect') ? (string) $this->validated('redirect') : null,
        );
    }
}
