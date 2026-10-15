<?php

declare(strict_types=1);

use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Content\ResourceEditor;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;

final class ResourceInteractionOptionsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\Settings'];
    private ModuleServiceRegistry $services;
    private ModuleManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->services = new ModuleServiceRegistry();
        \Config\Services::injectMock('mcmsModuleServices', $this->services);
        \Config\Services::injectMock('mcmsResourceEditor', new ResourceEditor($this->services));
        $this->manager = new ModuleManager($this->db, new AdminRegistry(), $this->services, ROOTPATH . 'modules', new NavigableResourceRegistry());
        foreach (['comments', 'ratings', 'pages', 'news', 'catalogue'] as $id) {
            $this->manager->enable($id);
        }
        $this->manager->bootEnabled();
    }

    protected function tearDown(): void
    {
        Events::removeAllListeners('mcms.comments.recorded');
        Events::removeAllListeners('mcms.comments.committed');
        foreach (['Comments', 'Ratings', 'News', 'Pages', 'Media', 'Catalogue', 'ProductImages', 'Captcha'] as $module) {
            $this->migrations->setNamespace('MCMS\\Modules\\' . $module)->regress(0, 'tests');
        }
        \Config\Services::resetSingle('mcmsModuleServices');
        \Config\Services::resetSingle('mcmsResourceEditor');
        parent::tearDown();
    }

    private function page(array $options = []): array
    {
        return $this->services->get('pages.repository')->create([
            'title' => 'Policy', 'slug' => 'policy', 'body' => '<p>Policy body</p>',
            'status' => 'published', 'resource_options' => $options,
        ]);
    }

    public function testIndependentOverridesEnforcedByWritesAndSurviveModuleDisable(): void
    {
        $page = $this->page(['comments' => 'deny', 'ratings' => 'allow']);
        $id = (string) $page['id'];
        $comments = $this->services->get('comments.repository');
        $ratings = $this->services->get('ratings.repository');
        self::assertNull($comments->publicTarget('pages.page', $id));
        try {
            $comments->create('pages.page', $id, null, 1, 'Editor', '', 'Denied');
            self::fail('Direct comment write bypassed policy');
        } catch (InvalidArgumentException) {
        }
        service('settings')->set('ratings.enabled_resources', '');
        $ratings->vote('pages.page', $id, 1, 'session', 5);
        self::assertSame(1, $ratings->summary('pages.page', $id)['count']);
        $editor = new ResourceEditor($this->services);
        $editor->save('pages.page', $id, ['ratings' => 'deny', 'comments' => 'allow']);
        try {
            $ratings->vote('pages.page', $id, 2, 'session', 4);
            self::fail('Direct vote bypassed policy');
        } catch (InvalidArgumentException) {
        }
        $this->manager->disable('comments');
        $empty = new ModuleServiceRegistry();
        self::assertSame([], (new ResourceEditor($empty))->fields());
        (new ResourceEditor($empty))->save('pages.page', $id, ['comments' => 'deny']);
        $this->manager->enable('comments');
        self::assertSame('allow', $this->services->get('comments.resource-policy')->value('pages.page', $id));
        self::assertNotNull($comments->publicTarget('pages.page', $id));
        $this->services->get('pages.repository')->update((int) $id, [...$page, 'status' => 'draft']);
        self::assertNull($comments->publicTarget('pages.page', $id));
        self::assertNull($ratings->publicTarget('pages.page', $id));
    }

    public function testAllResourceOwnersSavePoliciesAndInvalidPolicyRollsBackContent(): void
    {
        $page = $this->page(['comments' => 'deny']);
        $pages = $this->services->get('pages.repository');
        try {
            $pages->update($page['id'], [...$page, 'title' => 'Must roll back', 'resource_options' => ['comments' => 'wrong']]);
            self::fail('Invalid mode accepted');
        } catch (InvalidArgumentException) {
        }
        self::assertSame('Policy', $pages->find($page['id'])['title']);
        self::assertSame('deny', $this->services->get('comments.resource-policy')->value('pages.page', (string) $page['id']));
        $news = $this->services->get('news.repository');
        $article = $news->create(['title' => 'News', 'slug' => 'options-news', 'body' => 'News body', 'status' => 'published',
            'published_at' => date('Y-m-d H:i:s', time() - 60), 'resource_options' => ['ratings' => 'deny']]);
        $news->update($article['id'], [...$article, 'resource_options' => ['ratings' => 'allow']]);
        self::assertSame('allow', $this->services->get('ratings.resource-policy')->value('news.article', (string) $article['id']));
        $set = $this->services->get('product-images.library')->createSet(1);
        $catalogue = $this->services->get('catalogue.repository');
        $product = $catalogue->save(null, ['title' => 'Product', 'slug' => 'options-product', 'body' => 'Product body', 'status' => 'published',
            'image_set_id' => $set, 'variants' => [['sku' => 'OPTIONS-1', 'label' => 'One', 'price' => '100']],
            'resource_options' => ['comments' => 'deny', 'ratings' => 'inherit']], 1);
        self::assertSame('deny', $this->services->get('comments.resource-policy')->value('catalogue.product', (string) $product['id']));
    }

    public function testModerationEmitsDistinctTransactionalAndCommittedEvents(): void
    {
        $page = $this->page();
        $recorded = [];
        $depths = [];
        Events::on('mcms.comments.recorded', function (array $snapshot) use (&$recorded): void {
            self::assertGreaterThan(0, $this->db->transDepth);
            $recorded[] = $snapshot;
        });
        Events::on('mcms.comments.committed', function () use (&$depths): void { $depths[] = $this->db->transDepth; });
        $repo = $this->services->get('comments.repository');
        $row = $repo->create('pages.page', (string) $page['id'], null, 1, 'Author', '', 'Pending text');
        self::assertSame('pending', $recorded[0]['event']);
        self::assertStringContainsString('/admin/comments', $recorded[0]['url']);
        $repo->setStatus($row['id'], 'rejected');
        self::assertCount(1, $recorded);
        $repo->setStatus($row['id'], 'published');
        self::assertSame('published', $recorded[1]['event']);
        self::assertStringContainsString('#comment-', $recorded[1]['url']);
        $repo->setStatus($row['id'], 'published');
        self::assertCount(2, $recorded);
        self::assertSame([0], array_values(array_unique($depths)));
    }

    public function testRepliesFollowParentsAndDoNotLeakRejectedAncestorText(): void
    {
        $page = $this->page();
        $repo = $this->services->get('comments.repository');
        service('settings')->set('comments.auto_approve_users', true);
        $root = $repo->create('pages.page', (string) $page['id'], null, 1, 'A', '', 'First root');
        $second = $repo->create('pages.page', (string) $page['id'], null, 1, 'B', '', 'Second root');
        $reply = $repo->create('pages.page', (string) $page['id'], $root['id'], 1, 'C', '', 'Reply to first');
        $rows = $repo->published('pages.page', (string) $page['id']);
        self::assertSame([$root['id'], $reply['id'], $second['id']], array_map('intval', array_column($rows, 'id')));
        self::assertSame([0, 1, 0], array_column($rows, 'display_depth'));
        $repo->setStatus($root['id'], 'rejected');
        self::assertNotContains('First root', array_column($repo->published('pages.page', (string) $page['id']), 'body'));
        self::assertFalse($repo->notificationCurrent('comment:' . $root['id'] . ':published'));
    }
}
