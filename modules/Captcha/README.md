# Captcha module

First-party challenge service; no external APIs, cookies or tracking scripts. Enable
**Captcha** under Admin → Modules. It registers **captcha.verifier** implementing
`MCMS\Modules\Captcha\Api\CaptchaInterface`.

An integrating form calls:

```php
$captcha = service('mcmsModuleServices')->get('captcha.verifier');
$challenge = $captcha->issue('comments.create', session_id());
echo view('MCMS\\Modules\\Captcha\\Views\\widget', compact('challenge'));
```

On POST, after CSRF validation, enforce a per-IP submission limit, then call:

```php
$valid = $captcha->verify(
    'comments.create',
    session_id(),
    (string) $request->getPost('captcha_token'),
    (string) $request->getPost('captcha_answer'),
);
```

Challenges expire after five minutes and are consumed **once, even on an incorrect
answer**. An error must issue a **fresh** challenge. The token is bound to the
form purpose and PHP session, stored hashed; answers are stored as salted hashes
and verified server-side. The image endpoint returns dynamically drawn PNGs.
Only generated geometry, not the correct answer, is present in the image.
Challenges use MySQL (multi-worker safe); expired/used rows are pruned at
issuance. The service caps issuance per session, and image GETs are throttled.

Visual puzzles and rate limiting are **not** a guarantee against advanced vision
models or human-assisted spam. Keep moderation, anti-abuse throttles and CSRF.
A nonvisual accessible alternative and configuration/metrics are planned before
requiring Captcha on all public forms; do not treat this visual widget as the
final accessibility solution.
