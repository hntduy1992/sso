<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && $user->can('manage users');

    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username'=>'required|unique:users,username',
            'password'=>'required|regex:/^[a-zA-Z](?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{7,}$/'
        ];
    }
    public function messages()
    {
        return [
            'username.required'=>'Vui lòng không được bỏ trống!.',
            'password.required'=>'Vui lòng không được bỏ trống!.',
            'username.unique'=>'Username đã tồn tại!.',
            'password.regex'=>'Mật khẩu từ 8 ký tự, ít nhất 1 chữ in hoa, 1 số, 1 ký tự @$!%*?&.',
        ];
    }
}
