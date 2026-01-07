<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;

class CreateDepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && $user->can('department.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|max:200',
            'parent_id' => 'nullable|exists:departments,id|not_in:' . $this->id,
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Vui lòng không bỏ trống.',
            'parent_id.exists' => 'Đơn vị cha không tồn tại.',
            'parent_id.not_in' => 'Đơn vị cha không đúng.',
            'name.max' => 'Nội dung không được quá 200 ký tự.',
        ];
    }
}
