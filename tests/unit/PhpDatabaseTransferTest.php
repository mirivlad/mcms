<?php

declare(strict_types=1);

namespace Tests\Unit;

use MCMS\Core\Backup\PhpDatabaseTransfer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PhpDatabaseTransferTest extends TestCase
{
    public function testRejectsUnsafeMysqlIdentifiers(): void
    {
        self::assertSame('`some``column`', PhpDatabaseTransfer::quoteIdentifier('some`column'));
        foreach (['', str_repeat('X', 65), "bad\0name"] as $invalid) {
            try {
                PhpDatabaseTransfer::quoteIdentifier($invalid);
                self::fail('Invalid identifier was accepted');
            } catch (RuntimeException $exception) {
                self::assertSame('Invalid MySQL identifier.', $exception->getMessage());
            }
        }
    }

    public function testNonNativeSqlCannotBeImportedOnRestrictedHosting(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mcms-php-db-test-');
        self::assertNotFalse($path);
        try {
            file_put_contents($path, 'CREATE TABLE foo (id int);');
            self::assertFalse(PhpDatabaseTransfer::isNative($path));
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('native mCMS PHP database dumps');
            PhpDatabaseTransfer::import($path);
        } finally {
            unlink($path);
        }
    }
}
