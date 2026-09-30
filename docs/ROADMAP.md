# mCMS roadmap

## Stage 0 — modular Core

- [x] Initialize independent repository with no inherited Git history.
- [x] Move CodeIgniter application layout to repository root.
- [x] Remove customer-specific runtime/deployment configuration.
- [x] Remove dependency on an imported legacy database.
- [x] Adopt CodeIgniter Shield for authentication/authorization.
- [x] Adopt CodeIgniter Settings for persisted configuration.
- [x] Define module manifest/provider lifecycle.
- [x] Add database-backed module registry.
- [x] Ensure module migrations are namespace-scoped and opt-in.
- [x] Add initial Tabler-based admin shell.
- [ ] Add initial setup wizard/CLI and first superadmin creation.
- [ ] Add Core user/role/permission administration.
- [ ] Add Core settings UI.
- [ ] Add diagnostics/system page.
- [ ] Add local/vendor packaging for Bootstrap and Tabler assets.
- [x] Enforce mCMS and module dependency version constraints.
- [ ] Add module lifecycle and fresh-install integration tests.

## Stage 1 — first reusable modules

- [ ] Media.
- [ ] Pages.
- [ ] News.
- [ ] Menus/navigation.
- [ ] Galleries.

## Stage 2 — commerce modules

- [ ] Catalogue.
- [ ] Cart.
- [ ] Orders.
- [ ] Stock.
- [ ] Delivery.
- [ ] Export/report adapters.

## Stage 3 — ecosystem

- [ ] Module packaging/install from archive or repository.
- [ ] Module compatibility/version constraints.
- [ ] Safe module update workflow.
- [ ] Theme packaging.
- [ ] Optional API module.
- [ ] Optional notification/integration modules.

## Reference policy

Ferma is a functional reference only.

When mCMS needs a feature already implemented there, first extract the user-facing requirement and behavior, then implement it against mCMS Core contracts. Do not copy legacy schema, customer assumptions or compatibility routes unless they are independently justified for a reusable module.
