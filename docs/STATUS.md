# Current baseline — 2026-10-01

This document records the known-good mCMS checkpoint immediately before work on
the Menus/navigation module begins.

## Core

- CodeIgniter 4.7.4, Shield 1.4.x and Settings 2.4.x.
- Database-backed module discovery, dependency resolution and lifecycle.
- Namespace-scoped module migrations; Core never runs `migrate --all`.
- Public module service registry and administration contribution registry.
- First-run browser installer plus CLI recovery bootstrap.
- Users, groups, permissions, Core settings and system diagnostics.
- English/Russian localization.
- Tabler 1.6.1 administration shell with responsive navigation.
- Account/logout controls live in the administration sidebar rather than a
  separate top bar.
- HTTPS reverse-proxy deployments are supported through trusted proxy
  configuration and `X-Forwarded-Proto`.
- Standard CodeIgniter production/development error views are present.

## First-party modules

### Media 0.1.0

- Persistent media library.
- Image/file upload and metadata editing.
- Configurable upload/image limits and MIME allow-list.
- Public module service: `media.library`.
- Persistent files survive module disable/re-enable and container redeploy.

### Pages 0.1.0

- Draft/published static pages.
- Slug generation and collision handling.
- Sanitized rich-text content editor.
- Media picker integration through the Media public API.
- SEO title/description fields.
- Public route rendering.
- Public module service: `pages.repository`.

## Deployment and verification

The supported development deployment is the repository-backed Portainer stack on
`main`, behind an HTTPS reverse proxy.

The baseline has been verified by:

- PHP lint and unit/integration checks in the `mCMS checks` workflow;
- the full clean-install Docker smoke workflow;
- browser login through the HTTPS reverse proxy;
- desktop and mobile administration rendering.

## Next sequence

1. Menus/navigation.
2. Theme API + default theme.
3. Theme-declared menu locations and menu assignment.
4. News.
5. Galleries.

Menus must remain independent of any concrete theme. Themes will later declare
the menu locations they support; the active theme will map menu instances to
those locations.
