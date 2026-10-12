<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use MCMS\Modules\Ratings\Api\RatingsInterface;

final class RatingsModuleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\Settings'];

    protected function tearDown(): void
    {
        foreach (['Ratings', 'Pages', 'Media', 'Captcha'] as $module) {
            $this->migrations->setNamespace('MCMS\Modules\\' . $module)->regress(0, 'tests');
        }
        parent::tearDown();
    }

    private function ready(): array
    {
        $services = new ModuleServiceRegistry();
        $navigation = new NavigableResourceRegistry();
        $manager = new ModuleManager($this->db, new AdminRegistry(), $services, ROOTPATH . 'modules', $navigation);
        $manager->enable('ratings');
        $manager->enable('pages');
        $manager->bootEnabled();
        self::assertTrue($services->has('captcha.verifier'));
        $page = $services->get('pages.repository')->create([
            'title' => 'Voting page', 'slug' => 'voting-page',
            'body' => '<p>Rate this</p>', 'status' => 'published',
        ]);
        $repo = $services->get('ratings.repository');
        self::assertInstanceOf(RatingsInterface::class, $repo);
        return [$manager, $repo, (string) $page['id']];
    }

    public function testOneVotePerUserAndDistributionChangeWhenVoteUpdated(): void
    {
        [$manager, $repo, $id] = $this->ready();
        self::assertSame(['average' => null, 'count' => 0, 'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]], $repo->summary('pages.page', $id));

        $repo->vote('pages.page', $id, 1, 'whatever', 5);
        $repo->vote('pages.page', $id, 1, 'other-device', 3);
        $repo->vote('pages.page', $id, 2, 'whatever', 1);
        self::assertSame(3, $repo->userRating('pages.page', $id, 1, 'different-session'));
        self::assertSame(['average' => 2.0, 'count' => 2, 'distribution' => [1 => 1, 2 => 0, 3 => 1, 4 => 0, 5 => 0]], $repo->summary('pages.page', $id));
        self::assertCount(2, $repo->list());

        $voteId = (int) $repo->list()[0]['id'];
        $repo->delete($voteId);
        self::assertSame(1, $repo->summary('pages.page', $id)['count']);
        $manager->disable('ratings');
        self::assertTrue($this->db->tableExists('rating_votes'));
    }

    public function testGuestModeOffByDefaultAndCanBeEnabledWithSessionIdentity(): void
    {
        [, $repo, $id] = $this->ready();
        try {
            $repo->vote('pages.page', $id, null, 'guest-session', 5);
            self::fail('Guest should be rejected');
        } catch (InvalidArgumentException) {
        }
        service('settings')->set('ratings.allow_guests', true);
        $repo->vote('pages.page', $id, null, 'guest-session', 4);
        $repo->vote('pages.page', $id, null, 'guest-session', 5);
        self::assertSame(5, $repo->userRating('pages.page', $id, null, 'guest-session'));
        self::assertNull($repo->userRating('pages.page', $id, null, 'other-session'));
        self::assertSame(1, $repo->summary('pages.page', $id)['count']);
    }

    public function testRefusesUnknownContentAndOutOfRangeStars(): void
    {
        [, $repo, $id] = $this->ready();
        foreach ([
            ['pages.page', $id, 0],
            ['pages.page', '999999', 3],
            ['bogus.type', $id, 2],
        ] as [$type, $target, $stars]) {
            try {
                $repo->vote($type, $target, 1, 'session', $stars);
                self::fail('Invalid vote accepted');
            } catch (InvalidArgumentException) {
            }
        }
    }
}
