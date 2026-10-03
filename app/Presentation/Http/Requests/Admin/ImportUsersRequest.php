<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Admin;

use App\Application\User\Services\NewUserValidationRules;
use App\Application\User\UseCases\ImportUsersUseCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the envelope of one import chunk (password, department, row list).
 * Individual row attributes are validated per-row by ImportUsersUseCase so that
 * errors can be reported back for each row instead of rejecting the chunk.
 */
class ImportUsersRequest extends FormRequest
{
    public const int MAX_ROWS_PER_REQUEST = 200;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = app(NewUserValidationRules::class);

        $rowFieldRules = collect(ImportUsersUseCase::ROW_FIELDS)
            ->mapWithKeys(fn (string $field): array => ["rows.*.{$field}" => ['nullable', 'string', 'max:1000']])
            ->all();

        return [
            'password' => $rules->passwordRules(),
            'department_id' => $rules->departmentRules(),
            'rows' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS_PER_REQUEST],
            'rows.*' => ['required', 'array'],
            'rows.*.key' => ['required', 'string', 'max:64'],
            ...$rowFieldRules,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...app(NewUserValidationRules::class)->messages(),
            'rows.required' => 'Danh sách người dùng trống.',
            'rows.max' => 'Mỗi lượt gửi tối đa '.self::MAX_ROWS_PER_REQUEST.' dòng.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = array_values($this->validated('rows'));

        return $rows;
    }

    public function departmentId(): ?int
    {
        $departmentId = $this->validated('department_id');

        return $departmentId === null ? null : (int) $departmentId;
    }
}
