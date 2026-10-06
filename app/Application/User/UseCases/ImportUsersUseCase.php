<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Application\User\Services\NewUserValidationRules;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\User\DTOs\CreateUserDTO;
use App\Domain\User\Exceptions\PositionConflictException;
use App\Domain\User\Exceptions\UserNotFoundException;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ImportUsersUseCase
{
    /**
     * Row attributes accepted from the client-side parsed spreadsheet.
     *
     * @var list<string>
     */
    public const array ROW_FIELDS = [
        'name',
        'email',
        'full_name',
        'phone_number',
        'contact_email',
        'gender',
        'date_of_birth',
        'address',
    ];

    public function __construct(
        private readonly CreateUserUseCase $createUserUseCase,
        private readonly NewUserValidationRules $validationRules,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Validate (and optionally create) a chunk of imported rows.
     *
     * Each row is processed independently: invalid rows are returned with their
     * errors so the administrator can fix them and resubmit, while valid rows are
     * created (unless $dryRun is true).
     *
     * @param  list<array<string, mixed>>  $rows  Each row must contain a client-side `key`.
     * @return list<array{key: string, status: 'valid'|'invalid'|'created', errors: array<string, string>, user_id: int|null}>
     */
    public function execute(
        array $rows,
        string $defaultPassword,
        ?int $departmentId,
        bool $dryRun,
        ?User $adminUser = null,
    ): array {
        $results = [];
        $seenEmails = [];
        $createdUserIds = [];
        $hashedPassword = $dryRun ? '' : Hash::make($defaultPassword);

        foreach ($rows as $row) {
            $key = (string) ($row['key'] ?? '');
            $attributes = $this->normalizeRow($row);

            $errors = $this->validateRow($attributes);

            $email = $attributes['email'] ?? null;
            if (! isset($errors['email']) && is_string($email)) {
                if (isset($seenEmails[$email])) {
                    $errors['email'] = 'Email bị trùng với một dòng khác trong danh sách.';
                }
                $seenEmails[$email] = true;
            }

            if ($errors !== []) {
                $results[] = $this->result($key, 'invalid', $errors);

                continue;
            }

            if ($dryRun) {
                $results[] = $this->result($key, 'valid');

                continue;
            }

            try {
                /** @var array{name: string, email: string} $attributes */
                $user = $this->createUserUseCase->execute(
                    CreateUserDTO::fromArray($attributes, $hashedPassword, $departmentId),
                    $adminUser,
                );

                $createdUserIds[] = $user->id;
                $results[] = $this->result($key, 'created', userId: $user->id);
            } catch (UniqueConstraintViolationException) {
                $results[] = $this->result($key, 'invalid', ['email' => 'Email này đã tồn tại trong hệ thống.']);
            } catch (PositionConflictException|UserNotFoundException $e) {
                $results[] = $this->result($key, 'invalid', ['department_id' => $e->getMessage()]);
            }
        }

        if ($createdUserIds !== []) {
            $this->auditLogger->log(
                event: 'ADMIN_IMPORT_USERS',
                user: $adminUser,
                payload: [
                    'created_count' => count($createdUserIds),
                    'failed_count' => count($results) - count($createdUserIds),
                    'department_id' => $departmentId,
                    'user_ids' => $createdUserIds,
                ]
            );
        }

        return $results;
    }

    /**
     * Keep only known fields, trim strings and lowercase the login email.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, string|null>
     */
    private function normalizeRow(array $row): array
    {
        $attributes = [];

        foreach (self::ROW_FIELDS as $field) {
            $value = $row[$field] ?? null;
            $value = is_scalar($value) ? trim((string) $value) : null;
            $attributes[$field] = $value === '' ? null : $value;
        }

        if ($attributes['email'] !== null) {
            $attributes['email'] = mb_strtolower($attributes['email']);
        }

        return $attributes;
    }

    /**
     * @param  array<string, string|null>  $attributes
     * @return array<string, string>
     */
    private function validateRow(array $attributes): array
    {
        $validator = Validator::make(
            $attributes,
            $this->validationRules->attributeRules(),
            $this->validationRules->messages(),
        );

        if ($validator->passes()) {
            return [];
        }

        return collect($validator->errors()->messages())
            ->map(fn (array $messages): string => (string) $messages[0])
            ->all();
    }

    /**
     * @param  'valid'|'invalid'|'created'  $status
     * @param  array<string, string>  $errors
     * @return array{key: string, status: 'valid'|'invalid'|'created', errors: array<string, string>, user_id: int|null}
     */
    private function result(string $key, string $status, array $errors = [], ?int $userId = null): array
    {
        return [
            'key' => $key,
            'status' => $status,
            'errors' => $errors,
            'user_id' => $userId,
        ];
    }
}
