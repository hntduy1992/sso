<?php

declare(strict_types=1);

namespace App\Domain\User\Exceptions;

use Exception;

class InvalidMfaCodeException extends Exception
{
    public function __construct()
    {
        parent::__construct('Mã xác thực không hợp lệ hoặc đã hết hạn. Vui lòng thử lại.');
    }
}
