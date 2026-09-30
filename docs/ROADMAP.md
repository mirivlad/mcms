# Extraction roadmap

## Stage 0 — clean baseline

- [x] Initialize independent repository with no inherited Git history.
- [x] Move CodeIgniter application layout to repository root.
- [x] Remove customer-specific runtime/deployment configuration.
- [x] Remove dependency on an imported legacy database at container startup.
- [ ] Add complete clean-install database migrations.
- [ ] Add synthetic demo seeder.
- [ ] Add fresh-install integration test.

## Stage 1 — identity

- [ ] Generalize users, authentication, roles and permissions.
- [ ] Add corresponding migrations and tests.

## Stage 2 — content and media

- [ ] Generalize pages/news/categories.
- [ ] Generalize galleries and media management.
- [ ] Replace legacy file-manager compatibility component.

## Stage 3 — commerce

- [ ] Generalize catalogue, variants and stock.
- [ ] Generalize cart and orders.
- [ ] Generalize delivery rules.
- [ ] Move external delivery systems behind optional adapters.

## Stage 4 — productization

- [ ] Installation/bootstrap flow.
- [ ] Default theme.
- [ ] Portainer deployment.
- [ ] Backup/restore.
- [ ] Documentation and first release.
