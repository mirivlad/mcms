<?php

declare(strict_types=1);

namespace MCMS\Modules\Api\Api;

use RuntimeException;

final class ApiException extends RuntimeException
{
    public function __construct(public readonly int $status, public readonly string $errorCode, string $message, public readonly array $details = [])
    {
        parent::__construct($message);
    }
}
