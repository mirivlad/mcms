<?php

declare(strict_types=1);

use MCMS\Core\Packages\CatalogSchema;
use MCMS\Core\Packages\GitHubCatalogSource;
use PHPUnit\Framework\TestCase;

final class CatalogSchemaTest extends TestCase
{
    public static function unsafeUrls(): array
    {
        return array_map(fn ($url) => [$url], [
            'http://raw.githubusercontent.com/mirivlad/mcms/main/catalog/index.json',
            'https://127.0.0.1/catalog/index.json', 'https://localhost/index.json',
            'https://raw.githubusercontent.com@127.0.0.1/index.json',
            'https://raw.githubusercontent.com/mirivlad/other/main/catalog/index.json',
            'https://raw.githubusercontent.com/mirivlad/mcms/main/catalog/../secret',
            'https://raw.githubusercontent.com/mirivlad/mcms/main/catalog/%2e%2e/secret',
            'https://raw.githubusercontent.com/mirivlad/mcms/main/catalog/index.json?url=localhost',
            'https://github.com/other/project/releases/download/v1/file.zip',
            'https://release-assets.githubusercontent.com/fake.zip',
        ]);
    }

    /** @dataProvider unsafeUrls */
    public function testUnsafeNetworkSourcesRejected(string $url): void
    {
        $this->expectException(RuntimeException::class);
        GitHubCatalogSource::assertUrl($url, true);
    }

    public function testSameProjectReleaseArchivesAreAllowed(): void
    {
        GitHubCatalogSource::assertUrl('https://github.com/mirivlad/mcms/releases/download/packages-v1/demo-1.0.0.zip', true);
        self::assertTrue(true);
    }

    public function testDuplicateIdsAndUnsupportedSchemaAreRejected(): void
    {
        $index = json_decode(file_get_contents(ROOTPATH . 'catalog/index.json'), true);
        foreach (['duplicate', 'schema', 'version', 'screenshot', 'checksum'] as $case) {
            $candidate = $index;
            match ($case) {
                'duplicate' => $candidate['extensions'][] = $candidate['extensions'][0],
                'schema' => $candidate['schema'] = 2,
                'version' => $candidate['extensions'][0]['version'] = '9.9.9',
                'screenshot' => $candidate['extensions'][0]['screenshots'] = ['https://localhost/secret.png'],
                'checksum' => $candidate['extensions'][0]['versions'][0]['sha256'] = 'wrong',
            };
            try {
                CatalogSchema::index(json_encode($candidate));
                self::fail('Invalid catalog accepted: ' . $case);
            } catch (RuntimeException) {
            }
        }
        self::assertCount(2, $index['extensions']);
    }
}
