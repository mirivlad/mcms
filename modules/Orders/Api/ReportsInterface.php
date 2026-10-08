<?php

declare(strict_types=1);

namespace MCMS\Modules\Orders\Api;

interface ReportsInterface
{
    /** Totals grouped by current status and currency, filtered by order creation date. */
    public function summary(string $from = '', string $to = '', string $status = ''): array;

    /** @param resource $stream UTF-8 CSV output; amounts are integer minor units. */
    public function writeCsv($stream, string $format, string $from = '', string $to = '', string $status = ''): void;
}
