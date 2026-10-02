<?php

declare(strict_types=1);

namespace App\Domain\User\Exceptions;

use Exception;

class UserNotFoundException extends Exception
{
    public function __construct(string $message = 'Không tìm thấy thông tin người dùng.')
    {
        parent::__construct($message);
    }
}
