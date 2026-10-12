<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use MCMS\Modules\Comments\Api\CommentsInterface;

final class CommentsModuleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\Settings'];

    protected function tearDown(): void
    {
        foreach (['Comments', 'Pages', 'Media', 'Captcha'] as $module) {
            $this->migrations->setNamespace('MCMS\Modules\\' . $module)->regress(0, 'tests');
        }
        parent::tearDown();
    }

    private function ready(): array
    {
        $services = new ModuleServiceRegistry();
        $navigation = new NavigableResourceRegistry();
        $manager = new ModuleManager($this->db, new AdminRegistry(), $services, ROOTPATH . 'modules', $navigation);
        $manager->enable('comments');
        $manager->enable('pages');
        $manager->bootEnabled();
        self::assertTrue($this->db->tableExists('comments'));
        self::assertTrue($this->db->tableExists('comments_reports'));
        self::assertTrue($services->has('captcha.verifier'));
        self::assertSame(1, (int) $manager->states()['captcha']['enabled']);
        $page = $services->get('pages.repository')->create([
            'title' => 'Comments post', 'slug' => 'comments-post', 'body' => '<p>Hi</p>', 'status' => 'published',
        ]);
        $repo = $services->get('comments.repository');
        self::assertInstanceOf(CommentsInterface::class, $repo);
        return [$manager, $repo, (string) $page['id']];
    }

    public function testPublishedResourcesModerationRepliesReportsAndDeletion(): void
    {
        [$manager, $repo, $pageId] = $this->ready();

        $guest = $repo->create('pages.page', $pageId, null, null, 'Visitor', 'guest@example.com', 'Hello everyone!');
        self::assertSame('pending', $guest['status']);
        self::assertSame([], $repo->published('pages.page', $pageId));
        self::assertSame(1, $repo->count('pending'));

        $repo->setStatus($guest['id'], 'published');
        self::assertCount(1, $repo->published('pages.page', $pageId));
        $reply = $repo->create('pages.page', $pageId, $guest['id'], 11, 'Author', '', 'Reply here');
        self::assertSame(1, $reply['depth']);
        self::assertSame('pending', $reply['status']);
        self::assertSame(1, $repo->count('pending'));

        self::assertTrue($repo->report($guest['id'], 'session-1', 'Suspicious content'));
        self::assertFalse($repo->report($guest['id'], 'session-1', 'Duplicate report'));
        self::assertCount(1, $repo->reports($guest['id']));

        $repo->delete($guest['id']);
        self::assertSame([], $repo->reports($guest['id']));
        self::assertSame('[deleted]', $repo->published('pages.page', $pageId)[0]['body']);
        $row = $this->db->table('comments')->where('id', $guest['id'])->get()->getRowArray();
        self::assertNull($row['author_email']);
        self::assertNull($row['user_id']);

        $manager->disable('comments');
        self::assertSame(2, $this->db->table('comments')->countAllResults());
        self::assertTrue($this->db->tableExists('comments'));
    }

    public function testCannotCommentOnUnknownOrUnpublishedContentOrReplyAcrossResources(): void
    {
        [, $repo, $pageId] = $this->ready();
        try {
            $repo->create('pages.page', '9999999', null, null, 'Visitor', '', 'Not found');
            self::fail('Unknown content accepted');
        } catch (InvalidArgumentException) {
        }
        try {
            $repo->create('bogus.item', $pageId, null, null, 'Visitor', '', 'Invalid source');
            self::fail('Unknown provider accepted');
        } catch (InvalidArgumentException) {
        }
        $root = $repo->create('pages.page', $pageId, null, 1, 'Registered', '', 'First post');
        try {
            $repo->create('pages.page', $pageId, $root['id'], 2, 'User', '', 'Pending parent');
            self::fail('Reply to pending comment accepted');
        } catch (InvalidArgumentException) {
        }
    }

    public function testCaptchaDependencyCannotBeDisabledWhileCommentsEnabled(): void
    {
        [$manager] = $this->ready();
        $this->expectException(RuntimeException::class);
        $manager->disable('captcha');
    }
}
