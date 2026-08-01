<?php

declare(strict_types=1);

namespace WhatstheUp\Support;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message, public readonly string $codeName = 'request_error')
    {
        parent::__construct($message);
    }
}
