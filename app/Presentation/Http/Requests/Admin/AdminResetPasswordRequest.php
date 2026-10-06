<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AdminResetPasswordRequest extends FormRequest
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
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.confirmed' => 'Xác nhận mật khẩu mới không khớp.',
            'reason.required' => 'Vui lòng cung cấp lý do đặt lại mật khẩu (lưu vết kiểm toán).',
            'reason.min' => 'Lý do phải có ít nhất 5 ký tự.',
            'reason.max' => 'Lý do không được vượt quá 255 ký tự.',
        ];
    }
}
