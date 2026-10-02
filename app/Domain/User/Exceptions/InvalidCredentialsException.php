<?php

declare(strict_types=1);

namespace App\Domain\User\Exceptions;

use Exception;

class InvalidCredentialsException extends Exception
{
    public function __construct(string $message = 'Thông tin đăng nhập không chính xác.')
    {
        parent::__construct($message);
    }
}
