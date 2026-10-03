# mCMS Focus / Фокус

Magazine layout, red masthead and accents, cinematic cover and a clear editorial grid.
Activate in **Administration → Themes** and edit **Home page** under **Configure**.
Heading, description, cover from Media, button, additional text and News/Galleries
visibility are saved independently for this theme. An empty button URL hides the button.
The first current published News article supplies the feature headline; the next two
appear beside it. Without News, the configured opening section remains usable.
All public templates, gallery shortcodes, search, native mobile/submenu navigation and
the gallery viewer are supported.

A published Home selected in Pages takes precedence. Choose **Built-in Home page** in
Pages settings to display this theme's opening screen. Theme activation does not change
that choice, page content, another theme's settings or menu assignments.

The bundled cover remains available when Media is disabled or a selected image is deleted.
Media deletion confirmation includes Home cover references, including inactive themes.
No database migration or external font/image service is needed. Deploy updated PHP and
nginx images together so the new assets are available.

`assets/images/hero.webp` was generated with the built-in ImageGen tool and optimized to
WebP. Prompt: a detailed riverside city at night after rain, wet cobblestones, layered
historic facades, warm street lamps, amber and vermilion reflections against charcoal
and deep blue, cinematic photographic texture, no captions, logo or watermark. It is
an illustrative generated scene, not a documentary photograph of a named place.

`assets/fonts/manrope.woff2` is a local conversion of the upstream Manrope variable font;
see `assets/fonts/OFL.txt` and the root third-party notices for its license.
