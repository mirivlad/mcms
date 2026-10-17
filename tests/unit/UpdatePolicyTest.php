<?php

declare(strict_types=1);

use MCMS\Core\Updates\AutoUpdater;
use MCMS\Core\Updates\CoreUpdater;
use MCMS\Core\Updates\UpdatePolicy;
use PHPUnit\Framework\TestCase;

final class UpdatePolicyTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/mcms-update-test-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            \MCMS\Core\Packages\PackageState::remove($this->root);
        }
    }

    public function testDisabledByDefaultAndPersistentSelections(): void
    {
        $policy = new UpdatePolicy($this->root);
        self::assertSame(['core' => false, 'modules' => false, 'themes' => false, 'excluded' => []], $policy->get());
        $policy->save(['core' => false, 'modules' => true, 'themes' => true, 'excluded' => ['mcms-atelier', 'mcms-atelier']]);
        self::assertSame(['core' => false, 'modules' => true, 'themes' => true, 'excluded' => ['mcms-atelier']],
            (new UpdatePolicy($this->root))->get());
    }

    public function testMalformedIdsCannotEnterPersistentPolicy(): void
    {
        $policy = new UpdatePolicy($this->root);
        $this->expectException(RuntimeException::class);
        $policy->save(['core' => false, 'modules' => false, 'themes' => true, 'excluded' => ['../../etc/passwd']]);
    }

    public function testEventHistoryIsBounded(): void
    {
        $policy = new UpdatePolicy($this->root);
        for ($i = 0; $i < 90; $i++) {
            $policy->record(['result' => 'checked', 'sequence' => $i]);
        }
        self::assertCount(80, $policy->status()['events']);
        self::assertSame(89, $policy->status()['events'][0]['sequence']);
    }

    public function testMigrationChangesRequireManualUpdate(): void
    {
        $a = $this->root . '/old/Database/Migrations';
        $b = $this->root . '/new/Database/Migrations';
        mkdir($a, 0755, true);
        mkdir($b, 0755, true);
        file_put_contents($a . '/001.php', '<?php class Example {}');
        file_put_contents($b . '/001.php', '<?php class Example {}');
        self::assertFalse(AutoUpdater::containsNewMigrations(dirname(dirname($a)), dirname(dirname($b))));
        file_put_contents($b . '/002.php', '<?php class NewMigration {}');
        self::assertTrue(AutoUpdater::containsNewMigrations(dirname(dirname($a)), dirname(dirname($b))));
    }

    public function testCoreUpdaterDoesNotAcceptUnsafePortainerEndpoints(): void
    {
        self::assertFalse(CoreUpdater::validConfiguration('http://portainer.test', 'ptr_abcdefghijkl', '1', '2'));
        self::assertFalse(CoreUpdater::validConfiguration('https://portainer.test', 'bad', '1', '2'));
        self::assertFalse(CoreUpdater::validConfiguration('https://portainer.test', 'ptr_abcdefghijkl', '1/2', '2'));
        self::assertTrue(CoreUpdater::validConfiguration('https://portainer.test', 'ptr_abcdefghijkl', '1', '2'));
    }
}
