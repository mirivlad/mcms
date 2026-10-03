<?php
$appearance = $themeAppearance ?? [];
$accent = $appearance['accent'] ?? '';
if ($accent !== '') {
    $rgb = array_map('hexdec', str_split(substr($accent, 1), 2));
    $luminance = static function (array $rgb): float {
        $linear = array_map(static fn ($v) => $v / 255 <= .04045 ? $v / 255 / 12.92 : (($v / 255 + .055) / 1.055) ** 2.4, $rgb);
        return .2126 * $linear[0] + .7152 * $linear[1] + .0722 * $linear[2];
    };
    $foreground = $luminance($rgb) > .179 ? '#000000' : '#ffffff';
    $linkRgb = $rgb;
    while ($luminance($linkRgb) > .15) {
        $linkRgb = array_map(static fn ($v) => (int) ($v * .9), $linkRgb);
    }
    $hex = static fn (array $rgb): string => sprintf('#%02x%02x%02x', ...$rgb);
    $link = $hex($linkRgb);
    $hover = $hex(array_map(static fn ($v) => (int) ($v * .8), $linkRgb));
}
?>
<?php if (! empty($appearance['favicon'])): ?>
<link rel="icon" href="<?= esc(site_url(ltrim($appearance['favicon']['url'], '/')), 'attr') ?>">
<?php endif ?>
<style>
.mcms-brand-logo { width:auto; max-width:140px; height:40px; object-fit:contain; flex-shrink:0; }
.mcms-contacts { display:flex; flex-wrap:wrap; gap:12px 24px; margin:24px 0; font-style:normal; overflow-wrap:anywhere; }
<?php if ($accent !== ''): ?>
:root { --sig-accent:<?= esc($link) ?>; --site-accent:<?= esc($link) ?>; --site-button-accent:<?= esc($accent) ?>; --site-on-accent:<?= esc($foreground) ?>; --mcms-accent:<?= esc($link) ?>; --mcms-accent-strong:<?= esc($hover) ?>; --bs-primary:<?= esc($accent) ?>; --bs-link-color:<?= esc($link) ?>; --bs-link-hover-color:<?= esc($hover) ?>; }
body .btn-primary { --bs-btn-color:<?= esc($foreground) ?>; --bs-btn-bg:<?= esc($accent) ?>; --bs-btn-border-color:<?= esc($accent) ?>; --bs-btn-hover-bg:<?= esc($accent) ?>; --bs-btn-hover-color:<?= esc($foreground) ?>; --bs-btn-hover-border-color:<?= esc($accent) ?>; --bs-btn-active-bg:<?= esc($accent) ?>; --bs-btn-active-color:<?= esc($foreground) ?>; --bs-btn-active-border-color:<?= esc($accent) ?>; }
<?php endif ?>
@media(max-width:575px) { .mcms-brand-logo {max-width:90px;height:32px;} }
</style>
