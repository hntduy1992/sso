<?php

declare(strict_types=1);

namespace App\Domain\User\Exceptions;

use Exception;

class MfaChallengeRequiredException extends Exception
{
    public function __construct()
    {
        parent::__construct('Xác thực hai yếu tố bắt buộc. Vui lòng nhập mã TOTP.');
    }
}
