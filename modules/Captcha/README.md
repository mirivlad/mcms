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

## Accessible verification and policies (0.2)

Every new task includes an ordinary-text arithmetic question, immediately visible
and readable by screen readers. A native keyboard-operable details control offers
the existing image puzzle. Both methods give the same answer and use one token;
the answer itself is never included in hidden fields or JSON. The labelled numeric
text input describes the question, one-attempt rule and expiry. IDs are unique when
Comments and Ratings appear together. No audio player, pointer, color recognition
or image loading is needed to use the text method. Light/dark theme colors inherit
from the surrounding form. Older integrations consuming image_url/question remain
compatible; text_question is an additive API value.

Administration → Captcha (also in Settings) requires `captcha.manage`. Configure:

- Easy/standard/hard puzzles and 120–900 second expiry (default 300).
- Challenges per session/IP per minute and verification attempts per IP/session.
- Independent off/guests/everyone policies for registration, Feedback, Comments
  and Ratings. The default is guests; signed-in users are not challenged unless
  configured. A missing CAPTCHA service still closes registration as before.

`required(purpose, authenticated)` is an additive policy helper; issue/verify/image
retain their public signatures. Form integrations recheck the saved policy on POST.
Changing the policy while a form is open may require a fresh challenge. Comments
and Ratings still require their own guest-mode permission independently of CAPTCHA.

Atomic DB minute buckets cap both issuance and verification across workers, including
rotating sessions sharing an IP. Incorrect answers consume tokens, parallel/replayed
claims fail, and purpose/session bindings remain mandatory. Used challenges expire
from storage; short-lived buckets retain only hashed scopes. Daily aggregated metrics
record issued/passed/failed/expired/limited outcomes by purpose, with no raw IPs or
answers. Expiration is counted once on subsequent issuance, verification or statistics
inspection. The UI shows seven days; aggregate rows are pruned after 90 days.

Arithmetic and visual puzzles are solvable by automation. This accessible first-party
check is an abuse-friction layer alongside CSRF, moderation and submission throttles,
not proof of human identity. It does not require external cloud services.
