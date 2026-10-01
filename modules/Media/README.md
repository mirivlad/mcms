# Media module

Media is the shared file library for mCMS.

It is intentionally separate from Core. A clean mCMS installation has no Media
schema until this module is enabled.

## Responsibilities

Media owns:

- uploaded public files;
- MIME validation and safe generated filenames;
- image dimension metadata;
- title and alt-text metadata;
- Media administration UI;
- module settings and permissions;
- the public `media.library` runtime API.

Media does not own:

- galleries or gallery ordering;
- page/news content;
- image cropping policy for a specific business module;
- rich-text editing;
- arbitrary legacy filesystem browsing.

Those concerns belong to dependent modules.

## Storage

Files are stored below:

```text
public/assets/uploads/media/YYYY/MM/<random>.<ext>
```

The Docker/Portainer deployment mounts `public/assets/uploads` as a persistent
volume shared by PHP-FPM and nginx.

The original client filename is metadata only and is never used as a filesystem
path.

Current supported MIME types are:

- `image/jpeg`;
- `image/png`;
- `image/webp`;
- `image/gif`;
- `application/pdf`.

The list can be reduced in Media settings. Adding a MIME type requires Media code
support for a safe extension; an administrator cannot enable arbitrary executable
types through settings.

## Public runtime API

During module boot, Media publishes:

```text
media.library
```

through the mCMS module service registry.

Dependent modules retrieve it with:

```php
$media = service('mcmsModuleServices')->get('media.library');
```

The service implements:

```text
MCMS\Modules\Media\Api\MediaLibraryInterface
```

Supported operations include:

- list/search;
- count;
- find by ID;
- store an uploaded file;
- update title/alt text;
- delete;
- resolve a public URL;
- inspect supported MIME types.

A dependent module must declare Media in its manifest before consuming this API:

```json
{
  "requires": [
    {"id": "media", "version": "^0.1"}
  ]
}
```

Core boots dependencies first, so a dependent provider can safely consume
`media.library` during its own `boot()`.

## Permissions

Media contributes:

- `media.view`;
- `media.upload`;
- `media.edit`;
- `media.delete`;
- `media.settings`.

The built-in `admin` group receives these permissions by default when Media is
installed. Superadmins receive all module permissions through the Core module
contract.

## Disable semantics

Disabling Media removes its routes, menu item and runtime API registration.

It does not delete:

- `media_files`;
- Media settings;
- uploaded files.

Re-enabling the module restores access to the existing library.
