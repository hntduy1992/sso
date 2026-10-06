<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('id') ?? $this->route('user');
        if (is_object($userId)) {
            $userId = $userId->id;
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore((int) $userId),
            ],
            'role' => ['required', 'string', 'in:admin,user'],
            'status' => ['required', 'string', 'in:active,suspended'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên hiển thị là bắt buộc.',
            'email.required' => 'Email đăng nhập là bắt buộc.',
            'email.unique' => 'Email này đã được sử dụng bởi người dùng khác.',
            'role.in' => 'Vai trò phải là admin hoặc user.',
            'status.in' => 'Trạng thái phải là active hoặc suspended.',
        ];
    }
}
