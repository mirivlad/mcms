<?php

declare(strict_types=1);

namespace MCMS\Themes\Atelier\Views;

/**
 * External ZIP packages cannot ship Language PHP directories.
 * Keep presentation-only RU/EN strings in the allowed Views directory.
 */
final class Text
{
    public static function get(string $key): string
    {
        static $strings = [
            'en' => [
                'skip' => 'Skip to content',
                'navigation' => 'Main navigation',
                'menu' => 'Menu',
                'home' => 'Home',
                'close' => 'Close image',
                'previous' => 'Previous image',
                'next' => 'Next image',
                'original' => 'View original',
                'viewer' => 'Image viewer',
                'loading' => 'Loading image…',
                'imageError' => 'Could not load this image. Open the original or choose another.',
                'newWindow' => 'Opens in a new tab',
                'galleryLead' => 'A collection of ideas, places and perspectives.',
                'newsLead' => 'Notes, voices and discoveries from our editorial desk.',
                'contactLead' => 'Have a question or an idea? We would love to hear from you.',
                'visual' => 'Visual notes',
                'footer' => 'A space for ideas worth keeping.',
                'imageCount' => 'Images',
                'page' => 'Page',
                'emptyTitle' => 'The next chapter is on its way.',
                'tagline' => "An independent space\nfor ideas and stories.",
                'feature' => 'Selected story',
                'coverTitle' => 'A different way of seeing',
            ],
            'ru' => [
                'skip' => 'Перейти к содержимому',
                'navigation' => 'Основная навигация',
                'menu' => 'Меню',
                'home' => 'Главная',
                'close' => 'Закрыть изображение',
                'previous' => 'Предыдущее изображение',
                'next' => 'Следующее изображение',
                'original' => 'Открыть оригинал',
                'viewer' => 'Просмотр изображений',
                'loading' => 'Загрузка изображения…',
                'imageError' => 'Не удалось загрузить изображение. Откройте оригинал или выберите другое.',
                'newWindow' => 'Открывается в новой вкладке',
                'galleryLead' => 'Коллекция идей, мест и новых взглядов.',
                'newsLead' => 'Заметки, встречи и открытия нашей редакции.',
                'contactLead' => 'Есть вопрос или идея? Напишите нам.',
                'visual' => 'Визуальные заметки',
                'footer' => 'Пространство для идей, к которым возвращаются.',
                'imageCount' => 'Изображений',
                'page' => 'Страница',
                'emptyTitle' => 'Следующая глава уже близко.',
                'tagline' => "Независимое пространство\nдля идей и историй.",
                'feature' => 'Выбор редакции',
                'coverTitle' => 'Искусство смотреть иначе',
            ],
        ];

        $locale = strtolower((string) service('request')->getLocale());
        $language = str_starts_with($locale, 'ru') ? 'ru' : 'en';

        return $strings[$language][$key] ?? $strings['en'][$key] ?? $key;
    }
}
