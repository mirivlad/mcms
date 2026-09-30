<?php

declare(strict_types=1);

use MCMS\Core\Admin\AdminRegistry;
use PHPUnit\Framework\TestCase;

final class AdminRegistryTest extends TestCase
{
    public function testMenuAndWidgetsAreOrdered(): void
    {
        $registry = new AdminRegistry();

        $registry->addMenu('later', 'Later', '/later', order: 90);
        $registry->addMenu('first', 'First', '/first', order: 10);
        $registry->addWidget('later', 'later', order: 50);
        $registry->addWidget('first', 'first', order: 5);

        self::assertSame(['first', 'later'], array_column($registry->menu(), 'id'));
        self::assertSame(['first', 'later'], array_column($registry->widgets(), 'id'));
    }

    public function testReplacingSameIdDoesNotDuplicateContribution(): void
    {
        $registry = new AdminRegistry();
        $registry->addMenu('example', 'Old', '/old');
        $registry->addMenu('example', 'New', '/new');

        self::assertCount(1, $registry->menu());
        self::assertSame('New', $registry->menu()[0]['label']);
    }
}
