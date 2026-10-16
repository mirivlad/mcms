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

## Difficulty and accessible alternatives (0.2.2)

Difficulty controls the **primary** task type:

| Level | Primary task | Alternative |
| --- | --- | --- |
| Easy | Addition or subtraction within 10 | Count matching colored shapes among 12 |
| Standard | Count one shape/color combination among 12 pieces | A two-step word problem |
| Hard | Count two different shape/color combinations in total among 20 pieces | A three-step word problem |

The image and text questions have **the same answer** and share a single-use token.
Standard and hard present the image first; their native, keyboard-operable
details control lets someone choose the text alternative without relying on
color perception, mouse input or image loading. Easy presents arithmetic first
with an optional visual puzzle. Neither representation includes the answer as
hidden fields or in JSON; the expected answer is a salted server-side hash.
The text alternative prioritizes accessibility, not immunity to automation.
The response retains the historical visual question and image_url for existing
integrations, and adds kind (selects the primary view), visual_question,
text_question, image_width and image_height.
Unique widget IDs accommodate Comments and Ratings on the same page.

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

## Deferred challenges for Comments and Ratings

The public Comments and Ratings widgets do not issue a token while rendering a page.
When a visitor first attempts to submit a protected form, the shared guard script
requests a fresh single-use challenge through `POST /captcha/challenge`. The endpoint
verifies the target and policy, applies the existing issuance limits, and renders
only the form challenge. Server-side `verify()` remains mandatory on final submission.
If JavaScript is disabled, the first POST redirects back with the same typed comment
or selected rating and triggers challenge issuance on that next GET. Wrong and expired
answers trigger a fresh challenge instead of accepting the unverified submission.
