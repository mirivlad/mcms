<?php foreach ($items as $item): ?>
    <?php
    $url = trim((string) ($item['url'] ?? ''));
    $url = $url === '' ? null : (str_starts_with($url, '/') ? site_url(ltrim($url, '/')) : $url);
    $children = $item['children'] ?? [];
    $current = $url !== null && parse_url($url, PHP_URL_PATH) === service('request')->getUri()->getPath();
    $target = ! empty($item['open_in_new']) ? ' target="_blank" rel="noopener noreferrer"' : '';
    ?>
    <li>
        <?php if ($children !== []): ?>
            <details class="site-subnav"><summary><?= esc($item['label']) ?><span aria-hidden="true">⌄</span></summary><ul>
                <?php if ($url !== null): ?><li><a href="<?= esc($url, 'attr') ?>"<?= $target ?><?= $current ? ' aria-current="page"' : '' ?>><?= esc($item['label']) ?><?php if ($target !== ''): ?><span class="visually-hidden"> — <?= esc(lang('Depth.newWindow')) ?></span><?php endif ?></a></li><?php endif ?>
                <?= view('MCMS\\Themes\\Depth\\Views\\_navigation', ['items' => $children]) ?>
            </ul></details>
        <?php elseif ($url !== null): ?>
            <a href="<?= esc($url, 'attr') ?>"<?= $target ?><?= $current ? ' aria-current="page"' : '' ?>><?= esc($item['label']) ?><?php if ($target !== ''): ?><span class="visually-hidden"> — <?= esc(lang('Depth.newWindow')) ?></span><?php endif ?></a>
        <?php else: ?><span class="site-unavailable"><?= esc($item['label']) ?></span><?php endif ?>
    </li>
<?php endforeach ?>
