<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Profile;

use App\Domain\User\DTOs\UpdateProfileDTO;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('full_name') && $this->has('name')) {
            $this->merge(['full_name' => $this->input('name')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => 'họ và tên',
            'date_of_birth' => 'ngày sinh',
            'gender' => 'giới tính',
            'phone_number' => 'số điện thoại',
            'contact_email' => 'email liên hệ',
            'address' => 'địa chỉ',
            'bio' => 'giới thiệu',
        ];
    }

    public function toDTO(): UpdateProfileDTO
    {
        /** @var User $user */
        $user = $this->user();

        return new UpdateProfileDTO(
            userId: $user->id,
            fullName: $this->string('full_name')->toString(),
            dateOfBirth: $this->input('date_of_birth'),
            gender: $this->input('gender'),
            phoneNumber: $this->input('phone_number'),
            contactEmail: $this->input('contact_email'),
            address: $this->input('address'),
            bio: $this->input('bio'),
        );
    }
}
