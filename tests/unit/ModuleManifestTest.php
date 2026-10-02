<?php

declare(strict_types=1);

use MCMS\Core\Modules\ModuleManifest;
use PHPUnit\Framework\TestCase;

final class ModuleManifestTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/mcms-module-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/module.json');
        @rmdir($this->dir);

        parent::tearDown();
    }

    public function testLoadsValidManifest(): void
    {
        file_put_contents($this->dir . '/module.json', json_encode([
            'id'          => 'example',
            'name'        => 'Example',
            'version'     => '1.2.3',
            'namespace'   => 'MCMS\\Modules\\Example',
            'provider'    => 'MCMS\\Modules\\Example\\Module',
            'mcms'        => '^0.1',
            'requires'    => [['id' => 'media', 'version' => '^1.0']],
            'settings'    => ['items_per_page' => 20],
            'permissions' => ['example.manage' => 'Manage Example'],
            'grants'      => ['admin' => ['example.manage']],
            'public_paths' => ['example'],
        ], JSON_THROW_ON_ERROR));

        $manifest = ModuleManifest::fromFile($this->dir . '/module.json');

        self::assertSame('example', $manifest->id);
        self::assertSame('1.2.3', $manifest->version);
        self::assertSame('^0.1', $manifest->mcms);
        self::assertSame(['items_per_page' => 20], $manifest->settings);
        self::assertSame(['admin' => ['example.manage']], $manifest->grants);
        self::assertSame($this->dir, $manifest->path);
        self::assertSame(['example'], $manifest->publicPaths);
    }

    public function testRejectsDynamicPublicPaths(): void
    {
        file_put_contents($this->dir . '/module.json', json_encode([
            'id' => 'example', 'name' => 'Example', 'version' => '1.0.0',
            'namespace' => 'MCMS\\Modules\\Example',
            'provider' => 'MCMS\\Modules\\Example\\Module', 'mcms' => '^0.1',
            'public_paths' => ['(:segment)'],
        ], JSON_THROW_ON_ERROR));

        $this->expectException(InvalidArgumentException::class);
        ModuleManifest::fromFile($this->dir . '/module.json');
    }

    public function testRejectsPermissionOutsideModuleNamespace(): void
    {
        file_put_contents($this->dir . '/module.json', json_encode([
            'id'          => 'example',
            'name'        => 'Example',
            'version'     => '1.0.0',
            'mcms'        => '^0.1',
            'namespace'   => 'MCMS\\\\Modules\\\\Example',
            'provider'    => 'MCMS\\\\Modules\\\\Example\\\\Module',
            'permissions' => ['core.system' => 'Nope'],
        ], JSON_THROW_ON_ERROR));

        $this->expectException(InvalidArgumentException::class);
        ModuleManifest::fromFile($this->dir . '/module.json');
    }

    public function testRejectsGrantForUndeclaredPermission(): void
    {
        file_put_contents($this->dir . '/module.json', json_encode([
            'id'          => 'example',
            'name'        => 'Example',
            'version'     => '1.0.0',
            'mcms'        => '^0.1',
            'namespace'   => 'MCMS\\\\Modules\\\\Example',
            'provider'    => 'MCMS\\\\Modules\\\\Example\\\\Module',
            'permissions' => ['example.view' => 'View Example'],
            'grants'      => ['admin' => ['example.manage']],
        ], JSON_THROW_ON_ERROR));

        $this->expectException(InvalidArgumentException::class);
        ModuleManifest::fromFile($this->dir . '/module.json');
    }

    public function testRejectsModuleIdWithDot(): void
    {
        file_put_contents($this->dir . '/module.json', json_encode([
            'id'        => 'vendor.example',
            'name'      => 'Bad',
            'version'   => '1.0.0',
            'namespace' => 'Bad',
            'provider'  => 'Bad\\\\Module',
        ], JSON_THROW_ON_ERROR));

        $this->expectException(InvalidArgumentException::class);
        ModuleManifest::fromFile($this->dir . '/module.json');
    }

    public function testRejectsUnsafeModuleId(): void
    {
        file_put_contents($this->dir . '/module.json', json_encode([
            'id'        => '../bad',
            'name'      => 'Bad',
            'version'   => '1.0.0',
            'namespace' => 'Bad',
            'provider'  => 'Bad\\Module',
        ], JSON_THROW_ON_ERROR));

        $this->expectException(InvalidArgumentException::class);
        ModuleManifest::fromFile($this->dir . '/module.json');
    }
}
