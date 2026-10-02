# Galleries

Reusable image albums, independent of Pages and News. Requires Media ^0.2.

- Draft/published albums with title, slug and plain-text description.
- Up to 250 distinct Media images per album, with captions and explicit ordering.
- Optional cover from the selected images; otherwise the first available image.
- Searchable, paginated Media picker; administration album pagination.
- Public `/galleries` archive and `/galleries/{slug}` album.
- Logical `galleries.index` and `galleries.album` templates, with module fallbacks.
- Generic `galleries.archive` and `galleries.album` navigation providers.
- Public `galleries.repository` API.
- Read-only `media.usage` data provider, including drafts and disabled albums.

Galleries owns `galleries` and `gallery_images`. It consumes only Media's public
API. Removing an album/image association preserves Media files. Disabling the
module preserves albums and image associations. Confirmed deletion of a used
Media file preserves album captions and displays an unavailable-image placeholder;
cover rendering falls back to another available image.
