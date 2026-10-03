<?php

declare(strict_types=1);

namespace App\Application\User\Services;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Password;

/**
 * Shared validation rules for creating new user accounts, used by both
 * the single-user form and the bulk Excel import so both paths enforce
 * identical constraints.
 */
class NewUserValidationRules
{
    /**
     * Rules for the account + HRM profile attributes of a new user.
     *
     * @return array<string, array<int, mixed>>
     */
    public function attributeRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'full_name' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s().]{8,20}$/'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Rule for the default/initial password set by the administrator.
     *
     * @return array<int, mixed>
     */
    public function passwordRules(): array
    {
        return ['required', 'string', 'max:255', Password::min(8)->letters()->numbers()];
    }

    /**
     * Department must be an active, non-deleted specialized team because
     * newly created users are attached with the MEMBER (Tổ viên) position.
     *
     * @return array<int, mixed>
     */
    public function departmentRules(): array
    {
        return ['nullable', 'integer', $this->assignableDepartmentRule()];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên hiển thị là bắt buộc.',
            'name.max' => 'Tên hiển thị không được vượt quá 255 ký tự.',
            'email.required' => 'Email đăng nhập là bắt buộc.',
            'email.email' => 'Email đăng nhập không đúng định dạng.',
            'email.max' => 'Email không được vượt quá 255 ký tự.',
            'email.unique' => 'Email này đã tồn tại trong hệ thống.',
            'full_name.max' => 'Họ và tên không được vượt quá 255 ký tự.',
            'phone_number.max' => 'Số điện thoại không được vượt quá 20 ký tự.',
            'phone_number.regex' => 'Số điện thoại không hợp lệ.',
            'contact_email.email' => 'Email liên hệ không đúng định dạng.',
            'gender.in' => 'Giới tính phải là Nam, Nữ hoặc Khác.',
            'date_of_birth.date_format' => 'Ngày sinh không hợp lệ (định dạng dd/mm/yyyy).',
            'date_of_birth.before' => 'Ngày sinh phải trước ngày hôm nay.',
            'address.max' => 'Địa chỉ không được vượt quá 500 ký tự.',
            'password.required' => 'Vui lòng nhập mật khẩu mặc định.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.letters' => 'Mật khẩu phải chứa ít nhất một chữ cái.',
            'password.numbers' => 'Mật khẩu phải chứa ít nhất một chữ số.',
            'department_id.exists' => 'Đơn vị không hợp lệ (chỉ chọn được tổ chuyên môn đang hoạt động).',
        ];
    }

    private function assignableDepartmentRule(): Exists
    {
        return Rule::exists('departments', 'id')
            ->where('type', 'specialized_team')
            ->where('is_active', true)
            ->whereNull('deleted_at');
    }
}
