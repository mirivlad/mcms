<?php

declare(strict_types=1);

namespace MCMS\Modules\Media\Api;

final class ThemeUsageProvider implements MediaUsageProviderInterface
{
    public function findMediaUsage(int $mediaId, string $mediaUrl): array
    {
        $manager = service('mcmsThemes');
        $usages = [];
        foreach ($manager->catalog() as $theme) {
            $appearance = $manager->appearance($theme);
            foreach (['logo_media_id' => 'logo', 'favicon_media_id' => 'favicon'] as $key => $label) {
                if ((int) $appearance[$key] === $mediaId) {
                    $usages[] = [
                        'resource_id' => $theme->id . ':' . $label,
                        'label' => $manager->displayName($theme) . ' — ' . lang('MCMS.themes.' . $label),
                        'edit_url' => '/admin/themes/' . $theme->id,
                    ];
                }
            }
            if ((int) $manager->homeContent($theme)['image_media_id'] === $mediaId) {
                $usages[] = [
                    'resource_id' => $theme->id . ':home',
                    'label' => $manager->displayName($theme) . ' — ' . lang('MCMS.themes.homeCover'),
                    'edit_url' => '/admin/themes/' . $theme->id . '#theme-home-form',
                ];
            }
        }
        return $usages;
    }
}
