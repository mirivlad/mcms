# mCMS Depth / Глубина

Panoramic first screen, deep blue surfaces, amber accents and local Manrope typography.
Activate in **Administration → Themes** and edit **Home page** under **Configure**.
Heading, description, cover from Media, button, introductory text and News/Galleries
visibility are saved independently for this theme. An empty button URL hides the button.
Only published current content is displayed. All public templates, gallery shortcodes,
search, native mobile/submenu navigation and the gallery viewer are supported.

A published Home selected in Pages takes precedence. Choose **Built-in Home page** in
Pages settings to display this theme's opening screen. Theme activation does not change
that choice, page content, another theme's settings or menu assignments.

The bundled cover remains available when Media is disabled or a selected image is deleted.
Media deletion confirmation includes Home cover references, including inactive themes.
No database migration or external font/image service is needed. Deploy updated PHP and
nginx images together so the new assets are available.

`assets/images/hero.webp` was generated with the built-in ImageGen tool and optimized to
WebP. Prompt: a detailed cinematic mountain lake at blue hour, rugged cliffs and mist,
deep cobalt water and a vivid copper sunset, quiet left side for white text, realistic
textures, no text, logo or watermark. It is an illustrative generated scene, not a
documentary photograph of a named place.

`assets/fonts/manrope.woff2` is a local conversion of the upstream Manrope variable font;
see `assets/fonts/OFL.txt` and the root third-party notices for its license.
