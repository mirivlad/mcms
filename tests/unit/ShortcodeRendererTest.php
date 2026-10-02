<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use MCMS\Core\Content\ShortcodeProviderInterface;
use MCMS\Core\Content\ShortcodeRenderer;
use MCMS\Core\Modules\ModuleServiceRegistry;

final class ShortcodeRendererTest extends CIUnitTestCase
{
    public function testOnlyTextExpandsAndRepeatedEmbedsAreCached(): void
    {
        $services = new ModuleServiceRegistry();
        $provider = new class implements ShortcodeProviderInterface {
            public int $calls = 0;
            public function tag(): string { return 'gallery'; }
            public function render(array $attributes): string {
                $this->calls++;
                return '<span class="embedded">' . esc($attributes['id']) . '</span>';
            }
        };
        $services->register('test.shortcode', $provider);
        $renderer = new ShortcodeRenderer($services);
        $html = $renderer->render('<p>До [gallery id="12"] после [gallery id="12"]</p><pre>[gallery id="12"]</pre><code>[gallery id="12"]</code><a title=\'[gallery id="12"]\' href="/example">[gallery id="12"]</a>');
        self::assertSame(1, $provider->calls);
        self::assertSame(2, substr_count($html, 'class="embedded"'));
        self::assertStringContainsString('До ', $html);
        self::assertStringContainsString('<pre>[gallery id="12"]</pre>', $html);
        self::assertStringContainsString('<code>[gallery id="12"]</code>', $html);
        self::assertStringContainsString('title=', $html);
        self::assertStringContainsString('>[gallery id="12"]</a>', $html);
        self::assertStringContainsString('&lt;unsafe&gt;', $renderer->render('[gallery id="&lt;unsafe&gt;"]'));
    }

    public function testDisabledUnknownAndDuplicateAttributeCodesDoNotLeakToPublicText(): void
    {
        $renderer = new ShortcodeRenderer(new ModuleServiceRegistry());
        self::assertSame('<p>Before  after [literal]</p>', $renderer->render('<p>Before [gallery id=12] after [literal]</p>'));
        self::assertSame('', $renderer->render('[gallery id=12 id=13]'));
        self::assertSame('<p>Ordinary content</p>', $renderer->render('<p>Ordinary content</p>'));
    }
}
