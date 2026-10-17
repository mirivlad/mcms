<?php

declare(strict_types=1);

namespace Tests\Unit;

use MCMS\Core\Install\SharedHostSetup;
use PHPUnit\Framework\TestCase;

final class SharedHostSetupTest extends TestCase
{
    public function testDecodesPrivatePasswordWithoutInterpretingDollarOrQuotes(): void
    {
        $names = ['MCMS_INSTALL_MODE', 'MCMS_DB_PASSWORD', 'MCMS_DB_PASSWORD_B64'];
        $before = [];
        foreach ($names as $name) {
            $before[$name] = getenv($name);
        }
        try {
            putenv('MCMS_INSTALL_MODE=shared');
            putenv('MCMS_DB_PASSWORD=');
            $password = 'dollar${HOME}"back\\slash#equals=';
            putenv('MCMS_DB_PASSWORD_B64=' . base64_encode($password));
            self::assertTrue(SharedHostSetup::enabled());
            self::assertSame($password, SharedHostSetup::databasePassword());
        } finally {
            foreach ($before as $name => $value) {
                putenv($value === false ? $name : $name . '=' . $value);
            }
        }
    }

    public function testDockerModeKeepsExistingRawPasswordConfiguration(): void
    {
        $mode = getenv('MCMS_INSTALL_MODE');
        $password = getenv('MCMS_DB_PASSWORD');
        $encoded = getenv('MCMS_DB_PASSWORD_B64');
        try {
            putenv('MCMS_INSTALL_MODE=docker');
            putenv('MCMS_DB_PASSWORD=docker-db-secret');
            putenv('MCMS_DB_PASSWORD_B64=' . base64_encode('should-not-be-used'));
            self::assertFalse(SharedHostSetup::enabled());
            self::assertSame('docker-db-secret', SharedHostSetup::databasePassword());
        } finally {
            putenv($mode === false ? 'MCMS_INSTALL_MODE' : 'MCMS_INSTALL_MODE=' . $mode);
            putenv($password === false ? 'MCMS_DB_PASSWORD' : 'MCMS_DB_PASSWORD=' . $password);
            putenv($encoded === false ? 'MCMS_DB_PASSWORD_B64' : 'MCMS_DB_PASSWORD_B64=' . $encoded);
        }
    }
}
