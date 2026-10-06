<?php

declare(strict_types=1);

namespace App\Domain\User\Exceptions;

use Exception;

class CannotUnlinkLastAuthMethodException extends Exception
{
    public function __construct(string $message = 'Không thể hủy liên kết: đây là phương thức đăng nhập duy nhất còn lại của tài khoản.')
    {
        parent::__construct($message);
    }
}
