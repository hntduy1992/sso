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
            'login' => ['required_without:email', 'nullable', 'string', 'max:255'],
            'email' => ['required_without:login', 'nullable', 'string', 'max:255'],
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
            'login.required' => 'Vui lòng nhập địa chỉ email hoặc số điện thoại.',
            'login.required_without' => 'Vui lòng nhập địa chỉ email hoặc số điện thoại.',
            'email.required' => 'Vui lòng nhập địa chỉ email hoặc số điện thoại.',
            'email.required_without' => 'Vui lòng nhập địa chỉ email hoặc số điện thoại.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
        ];
    }

    public function toDTO(): LoginDTO
    {
        $identifier = (string) ($this->input('login') ?: $this->input('email'));

        return new LoginDTO(
            login: $identifier,
            password: (string) $this->validated('password'),
            remember: (bool) $this->boolean('remember'),
            redirect: $this->validated('redirect') ? (string) $this->validated('redirect') : null,
        );
    }
}
