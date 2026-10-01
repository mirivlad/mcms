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
- [x] Add browser setup wizard, CLI fallback and first superadmin creation.
- [x] Add Core user/group/permission administration.
- [x] Add Core settings UI.
- [x] Add diagnostics/system page.
- [x] Add English/Russian localization and site locale selection.
- [x] Add local/runtime packaging for Bootstrap and Tabler assets.
- [x] Enforce mCMS and module dependency version constraints.
- [x] Add module lifecycle and fresh-install integration tests.

## Stage 1 — first reusable modules and public presentation

- [x] Media.
- [x] Pages.
- [x] Menus/navigation.
- [x] Theme API and administration.
- [x] Default theme with declared menu locations.
- [ ] Menus ↔ theme-location assignment.
- [ ] News.
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
- [ ] Third-party theme packaging/install.
- [ ] Optional API module.
- [ ] Optional notification/integration modules.

## Reference policy

Ferma is a functional reference only.

When mCMS needs a feature already implemented there, first extract the user-facing requirement and behavior, then implement it against mCMS Core contracts. Do not copy legacy schema, customer assumptions or compatibility routes unless they are independently justified for a reusable module.
