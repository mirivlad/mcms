<?php

declare(strict_types=1);

namespace MCMS\Modules\Orders\Api;

final class OrderEvents
{
    // Inside the order transaction: listeners may persist an outbox on the same
    // connection, but must not perform network I/O or start another transaction.
    public const RECORDED = 'mcms.orders.recorded';

    // After the outer transaction commits. Delivery failures cannot undo an order.
    public const COMMITTED = 'mcms.orders.committed';
}
