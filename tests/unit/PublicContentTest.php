<?php

declare(strict_types=1);

use MCMS\Core\Content\PublicContent;
use PHPUnit\Framework\TestCase;

final class PublicContentTest extends TestCase
{
    public function testOnlyMatchingLeadingH1IsRemovedFromPublicRender(): void
    {
        $original = '<h1 class="big">Новости&nbsp;сегодня</h1><p>Текст записи.</p>';
        self::assertSame('<p>Текст записи.</p>', PublicContent::body($original, 'Новости сегодня'));
        self::assertSame('<h1>Another title</h1><p>Keep me</p>',
            PublicContent::body('<h1>Another title</h1><p>Keep me</p>', 'Новости сегодня'));
        self::assertSame('<p>Intro</p><h1>Новости сегодня</h1>',
            PublicContent::body('<p>Intro</p><h1>Новости сегодня</h1>', 'Новости сегодня'));
        self::assertSame($original, '<h1 class="big">Новости&nbsp;сегодня</h1><p>Текст записи.</p>');
    }

    public function testMatchingFirstParagraphIsNotDuplicatedByExcerpt(): void
    {
        $body = '<h1>Заголовок</h1><p>Это краткое вступление.</p><p>Дальше статья.</p>';
        self::assertSame('', PublicContent::excerpt('Это краткое вступление.', $body, 'Заголовок'));
        self::assertSame('Другое описание', PublicContent::excerpt('Другое описание', $body, 'Заголовок'));
        self::assertSame('', PublicContent::excerpt('', $body, 'Заголовок'));
    }
}
