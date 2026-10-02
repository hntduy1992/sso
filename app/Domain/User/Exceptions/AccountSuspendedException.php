<?php

declare(strict_types=1);

namespace App\Domain\User\Exceptions;

use Exception;

class AccountSuspendedException extends Exception
{
    public function __construct(string $message = 'Tài khoản của bạn đã bị khóa hoặc tạm ngưng hoạt động. Vui lòng liên hệ Quản trị viên.')
    {
        parent::__construct($message);
    }
}
