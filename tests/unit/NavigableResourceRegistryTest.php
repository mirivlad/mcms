<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use MCMS\Core\Navigation\NavigableResourceProviderInterface;
use MCMS\Core\Navigation\NavigableResourceRegistry;

final class NavigableResourceRegistryTest extends CIUnitTestCase
{
    public function testRegistryListsSearchesAndResolvesProviders(): void
    {
        $provider = new class implements NavigableResourceProviderInterface {
            public function id(): string
            {
                return 'test.article';
            }

            public function label(): string
            {
                return 'Articles';
            }

            public function search(string $query = '', int $limit = 50): array
            {
                return $query === 'missing'
                    ? []
                    : [['id' => '42', 'label' => 'Hello', 'url' => '/hello']];
            }

            public function resolve(string $resourceId): ?array
            {
                return $resourceId === '42'
                    ? ['id' => '42', 'label' => 'Hello', 'url' => '/hello']
                    : null;
            }
        };

        $registry = new NavigableResourceRegistry();
        $registry->register($provider);

        self::assertTrue($registry->has('test.article'));
        self::assertSame([
            ['id' => 'test.article', 'label' => 'Articles'],
        ], $registry->choices());
        self::assertSame('/hello', $registry->resolve('test.article', '42')['url']);
        self::assertNull($registry->resolve('test.article', '404'));
        self::assertNull($registry->resolve('missing.provider', '42'));
    }

    public function testRegistryRejectsDuplicateProviderIds(): void
    {
        $provider = new class implements NavigableResourceProviderInterface {
            public function id(): string { return 'test.page'; }
            public function label(): string { return 'Pages'; }
            public function search(string $query = '', int $limit = 50): array { return []; }
            public function resolve(string $resourceId): ?array { return null; }
        };

        $registry = new NavigableResourceRegistry();
        $registry->register($provider);

        $this->expectException(RuntimeException::class);
        $registry->register($provider);
    }
}
