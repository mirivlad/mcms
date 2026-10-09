<?php

declare(strict_types=1);

namespace MCMS\Modules\Stock\Api;

use RuntimeException;

final class StockConflict extends RuntimeException
{
    public function __construct(string $message, public readonly array $current)
    {
        parent::__construct($message);
    }
}
