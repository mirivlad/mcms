## Public accounts

mCMS uses CodeIgniter Shield for all accounts: administrators, commenters and shop customers share one identity database. New registrations always enter the non-administrative user group.

### Activate registration

1. Enable the first-party Captcha module under Admin / Modules.
2. Enable Allow user registration under Admin / Settings (Auth.allowRegistration).
3. Test /register and /login. Both are linked from all four bundled themes. The sign-in redirects to /account; administrators get an additional link to /admin.

A server-side single-use, session-bound CAPTCHA is required for registration. Without the module, registration fails closed and the visitor sees an explanation. Signup attempts are also rate-limited. Shield handles password validation, user creation, group assignment, login session and activation.

### Public routes

- /login — email/password login with optional remember-me.
- /register — email, username, password confirmation and CAPTCHA.
- /account — update username, contact name and phone; change password after checking current password.
- /account/orders — latest 100 orders belonging to the signed-in customer, if Orders is enabled.
- /account/orders/{id} — status, amounts and order lines. SQL enforces the customer_user_id owner condition, so guessing an order ID cannot expose another customer's order.
- POST /logout — CSRF-protected public logout.

Core migration account_profiles stores optional full name and phone. Email identity remains under Shield management. There is no email/password recovery flow or avatar upload in this stage. Future work: verified email change/reset, saved delivery addresses and paginated history.

### Modules and themes

All public account pages (login, registration, profile, order list and
individual order details) are rendered via ThemeManager inside the **active site
theme**, including its normal header, menus, branding, typography, footer, and
theme appearance settings. Shield still provides the POST login flow and session
security. Core account views are shared across all themes: they do not duplicate
the theme's layout or introduce a separate site shell.

An installed theme may optionally add account.login, account.register,
account.account, account.orders or account.order mappings under templates
in theme.json to override an individual account page. Otherwise mCMS renders
the matching Core auth view inside the theme's conventional Views/layout.php.
Themes without that conventional layout use the minimal standalone layout
as a compatibility fallback.

The built-in themes mcms-default, mcms-depth, mcms-focus and mcms-signature include shared auth/nav account links. Third-party themes can include that view and public/assets/css/account-nav.css.

Comments and Ratings use Shield user IDs as before. Guest comments (when allowed), guest carts and guest checkouts remain available. Logged-in purchasers' orders are linked to their identity through customer_user_id, and checkout pre-populates stored name, phone and email. Previously placed guest orders are not claimed by email, to prevent unauthorized account linking.

The account UI is distinct from the administration UI: ordinary users have no core.admin permission. CSRF applies to all public account mutations.

### Administrator user directory (0.2.0 development)

The Core-owned `/admin/users` supports live server-side filtered searches by
username, email, optional global query, and group; page sizes of 10/25/50/100,
with stable descending IDs and filters kept when paginating. Search results are
HTML-rendered on the server and refreshed via a small debounced progressive-enhancement
script. Without JavaScript the GET filter form and page links work normally.

The list shows registration and last activity (unrecorded activity is `—`).
`core.users` permits read/profile edit, while `core.access` is required for
sensitive delete/activate/deactivate, manual password changes and random-password
resets. These use CSRF-protected POST operations and require explicit selection.
Access/group editing remains on its separate `core.access` page. Non-superadmins
cannot change superadmin profiles; self-deactivation/deletion/password reset
are blocked. At least one active superadmin must remain.

Deletion is Shield soft deletion to retain history and foreign references; it
is not personal-data erasure. Random password resets show a newly generated
password **once** in the administrator's authenticated session; the owner must
transmit it securely. Remember-me tokens are revoked, but existing logged-in
sessions may not all be forcibly terminated yet; session invalidation and
verified email recovery require further hardening before release.

Account deactivation uses Shield's native ban/unban state, not the unrelated
optional email-activation flag. A Core guard blocks already authenticated banned
sessions; Shield also rejects their new login attempts.
This is development work under 0.2.0, **not a published release**.
