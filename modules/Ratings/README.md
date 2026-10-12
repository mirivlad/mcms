# Ratings module

Enable **Ratings** under Administration → Modules. Ratings depends on the in-house
Captcha module, but is **independent of Comments**. It registers
`ratings.repository` implementing `MCMS\Modules\Ratings\Api\RatingsInterface`.

## Behavior

The default is one vote by each signed-in user for each published resource.
A vote can be changed, never counted twice. Votes are stored individually and
the public widget shows the one-decimal average, total votes and distribution
for 1–5 stars. Content references use the Core public navigation registry's
stable provider ID and resource ID.

By default, ratings are available for published Pages, News articles and
Catalogue products. Four bundled themes add the optional rating widget to
their public content views; third-party themes can include it explicitly:

    <?php if (service('mcmsModuleServices')->has('ratings.repository')): ?>
        <?= view('MCMS\\Modules\\Ratings\\Views\\widget', [
            'targetType' => 'pages.page', 'targetId' => (string) $page['id']
        ]) ?>
    <?php endif ?>

Guest ratings can be enabled in Admin → Ratings → Settings. They require a
separate first-party CAPTCHA per submission and a session; however **clearing
cookies creates another guest identity**. Thus for high-value public ratings,
keep guest voting off (the default). Both modes are rate-limited by source IP.

## Administration

The admin list supports removing a vote. Settings configure guest mode and
which public resource providers accept ratings. Disabling the module preserves
votes. There is no dependency on News, Pages or Catalogue tables; references
to unavailable resources remain in storage but do not render on the public site.

Future possibilities: transaction-backed concurrent upserts, suspicious voting
analytics, verified-purchaser badges and guest abuse detection.
