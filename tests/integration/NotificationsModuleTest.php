<?php

declare(strict_types=1);

use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use MCMS\Modules\Cart\Services\Cart;
use MCMS\Modules\Catalogue\Services\Catalogue;
use MCMS\Modules\Notifications\Services\Notifications;
use MCMS\Modules\Orders\Api\OrderEvents;
use MCMS\Modules\Orders\Services\Orders;
use MCMS\Modules\Stock\Services\Stock;

final class NotificationsModuleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings'];
    private Orders $orders;
    private Cart $cart;
    private Stock $stock;
    private Notifications $mail;
    private int $variant;
    private array $sent = [];
    private bool $deliver = true;

    protected function setUp(): void
    {
        parent::setUp();
        service('language')->setLocale('en');
        session()->remove('mcms_cart');
        $services = new ModuleServiceRegistry();
        $manager = new ModuleManager($this->db, new AdminRegistry(), $services, ROOTPATH . 'modules', new NavigableResourceRegistry());
        $manager->enable('notifications');
        $manager->bootEnabled();
        $catalogue = new Catalogue($this->db, $services->get('product-images.library'));
        $this->stock = new Stock($this->db, $catalogue);
        $this->cart = new Cart($catalogue, $this->stock);
        $this->orders = new Orders($this->db, $this->cart, $this->stock);
        $this->mail = new Notifications($this->db, $this->orders, function (string $to, string $subject, string $body): bool {
            self::assertSame(0, $this->db->transDepth, 'Network delivery must run after the outer transaction.');
            $this->sent[] = [$to, $subject, $body];
            return $this->deliver;
        });
        Events::removeAllListeners('mcms.comments.recorded');
        Events::removeAllListeners('mcms.comments.committed');
        Events::removeAllListeners(OrderEvents::RECORDED);
        Events::removeAllListeners(OrderEvents::COMMITTED);
        Events::on(OrderEvents::RECORDED, $this->mail->record(...));
        Events::on(OrderEvents::COMMITTED, fn (int $id): array => $this->mail->sendPending($id, 1));
        $now = date('Y-m-d H:i:s');
        $this->db->table('catalogue_products')->insert([
            'title' => 'Coffee', 'slug' => 'notification-coffee', 'category' => '', 'category_id' => null,
            'summary' => '', 'body' => '', 'status' => 'published', 'image_set_id' => bin2hex(random_bytes(16)), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $product = (int) $this->db->insertID();
        $this->db->table('catalogue_variants')->insert(['product_id' => $product, 'sku' => 'MAIL-250', 'label' => '250 g', 'price_minor' => 15000, 'currency' => 'RUB', 'position' => 0]);
        $this->variant = (int) $this->db->insertID();
        $this->stock->setOnHand($this->variant, 10, 0);
    }

    protected function tearDown(): void
    {
        Events::removeAllListeners('mcms.comments.recorded');
        Events::removeAllListeners('mcms.comments.committed');
        Events::removeAllListeners(OrderEvents::RECORDED);
        Events::removeAllListeners(OrderEvents::COMMITTED);
        session()->remove('mcms_cart');
        foreach (['Notifications', 'Orders', 'Stock', 'Catalogue', 'ProductImages'] as $module) {
            $this->migrations->setNamespace('MCMS\\Modules\\' . $module)->regress(0, 'tests');
        }
        parent::tearDown();
    }

    public function testCommentSnapshotsRetryAndWithdrawnPublicationIsNotDelivered(): void
    {
        $this->mail->saveCommentPreferences(['enabled' => '1', 'recipient' => 'moderator@example.test', 'events' => ['pending', 'published']]);
        $source = new class implements \MCMS\Core\Events\NotificationEventSourceInterface {
            public bool $current = true;
            public function supportsNotification(string $event): bool { return str_starts_with($event, 'comments.'); }
            public function notificationCurrent(string $key): bool { return $this->current; }
        };
        service('mcmsModuleServices')->register('test.comment-source', $source);
        $this->deliver = false;
        $snapshot = ['key' => 'comment:1:pending', 'id' => 1, 'event' => 'pending', 'author' => 'Guest', 'body' => 'Review me', 'url' => '/admin/comments'];
        $this->db->transBegin();
        $this->mail->recordComment($snapshot);
        self::assertSame(['sent' => 0, 'failed' => 0], $this->mail->sendPending());
        $this->db->transCommit();
        self::assertSame(['sent' => 0, 'failed' => 1], $this->mail->sendPending());
        self::assertStringContainsString('awaiting moderation', $this->sent[0][1]);
        $this->deliver = true;
        self::assertSame(['sent' => 1, 'failed' => 0], $this->mail->sendPending());
        $this->mail->recordComment($snapshot);
        self::assertSame(1, $this->db->table('notifications_messages')->countAllResults());
        $this->mail->recordComment([...$snapshot, 'key' => 'comment:1:published', 'event' => 'published']);
        $source->current = false;
        self::assertSame(['sent' => 0, 'failed' => 0], $this->mail->sendPending());
        self::assertSame('cancelled', $this->db->table('notifications_messages')->where('event', 'comments.published')->get()->getRowArray()['status']);
    }

    private function configure(array $changes = []): void
    {
        $this->mail->save(array_replace([
            'enabled' => '1', 'recipient' => 'manager@example.test', 'events' => ['new', 'completed'],
            'subject' => 'Order {order_id}: {status} / {customer_name}',
            'body' => "{customer_name}\n{phone}\n{delivery}\n{items}\n{total}",
        ], $changes));
    }

    private function checkout(): array
    {
        $this->cart->add($this->variant, 2);
        return $this->orders->createFromCart(['name' => 'Alice', 'phone' => '123', 'email' => 'customer@example.test'], ['id' => 5, 'type' => 'pickup', 'name' => 'Store pickup', 'price_minor' => 1000]);
    }

    public function testSelectedEventsUseCommittedSnapshotAndNoopDoesNotSendAgain(): void
    {
        $this->configure();
        $order = $this->checkout();
        self::assertCount(1, $this->sent);
        self::assertSame('manager@example.test', $this->sent[0][0]);
        self::assertStringContainsString('Coffee / 250 g × 2', $this->sent[0][2]);
        self::assertStringContainsString('310.00 RUB', $this->sent[0][2]);
        self::assertStringContainsString('Store pickup', $this->sent[0][2]);
        $this->orders->transition($order['id'], 'processing', 0);
        self::assertCount(1, $this->sent);
        $this->orders->transition($order['id'], 'completed', 0);
        self::assertCount(2, $this->sent);
        self::assertSame(8, $this->stock->level($this->variant)['on_hand']);
        $this->orders->transition($order['id'], 'completed', 0);
        self::assertSame(['sent' => 0, 'failed' => 0], $this->mail->sendPending());
        self::assertCount(2, $this->sent);
    }

    public function testTransportFailureKeepsOrderAndRetryKeepsOriginalRecipientAndTemplate(): void
    {
        $this->configure();
        $this->deliver = false;
        $order = $this->checkout();
        self::assertSame('new', $this->orders->find($order['id'])['status']);
        self::assertSame(2, $this->stock->level($this->variant)['reserved']);
        self::assertSame(1, $this->mail->pendingCount());
        $this->configure(['recipient' => 'other@example.test', 'body' => 'Changed {order_id}']);
        $this->deliver = true;
        self::assertSame(['sent' => 1, 'failed' => 0], $this->mail->sendPending());
        self::assertSame($this->sent[0], $this->sent[1]);
        self::assertSame(0, $this->mail->pendingCount());
        self::assertSame(2, (int) $this->mail->recent()[0]['attempts']);
    }

    public function testRollbackRemovesQueuedEmailAndReservationWithoutNetworkDelivery(): void
    {
        $this->configure();
        Events::on(OrderEvents::RECORDED, static function (): void { throw new RuntimeException('Abort checkout'); });
        try {
            $this->checkout();
            self::fail('Checkout must roll back.');
        } catch (RuntimeException $e) {
            self::assertSame('Abort checkout', $e->getMessage());
        }
        self::assertSame(0, $this->db->table('orders')->countAllResults());
        self::assertSame(0, $this->mail->pendingCount());
        self::assertSame(0, $this->stock->level($this->variant)['reserved']);
        self::assertCount(0, $this->sent);
        self::assertSame(2, $this->cart->count());
    }

    public function testOuterTransactionCannotDeliverEmailBeforeCommit(): void
    {
        $this->configure();
        $this->db->transBegin();
        $this->checkout();
        self::assertSame(['sent' => 0, 'failed' => 0], $this->mail->sendPending());
        self::assertCount(0, $this->sent);
        $this->db->transRollback();
        self::assertSame(0, $this->mail->pendingCount());
        self::assertSame(0, $this->db->table('orders')->countAllResults());
    }

    public function testDisabledNotificationsAndValidationPreservePreferences(): void
    {
        $this->checkout();
        self::assertSame(0, $this->mail->pendingCount());
        $this->configure();
        $before = $this->mail->preferences();
        foreach ([['recipient' => 'bad'], ['recipient' => ['invalid']], ['events' => ['unknown']], ['events' => [['new']]], ['events' => []], ['subject' => "Bad\r\nHeader"], ['body' => '{secret}'], ['body' => str_repeat('x', 10001)]] as $input) {
            try {
                $this->configure($input);
                self::fail('Invalid settings must be rejected.');
            } catch (InvalidArgumentException) {
                self::assertSame($before, $this->mail->preferences());
            }
        }
        $this->configure(['enabled' => false]);
        self::assertTrue($this->mail->test(), 'Test mail works while order notifications are off.');
        self::assertCount(1, $this->sent);
    }

    public function testLiveClaimsExcludeOtherSendersAndAbandonedClaimsCanRecover(): void
    {
        $this->configure();
        $this->deliver = false;
        $order = $this->checkout();
        $this->db->table('notifications_outbox')->where('order_id', $order['id'])->update(['status' => 'sending', 'claimed_at' => date('Y-m-d H:i:s')]);
        self::assertSame(['sent' => 0, 'failed' => 0], $this->mail->sendPending());
        self::assertCount(1, $this->sent);
        $this->db->table('notifications_outbox')->where('order_id', $order['id'])->update(['claimed_at' => date('Y-m-d H:i:s', time() - 600)]);
        $this->deliver = true;
        self::assertSame(['sent' => 1, 'failed' => 0], $this->mail->sendPending());
        self::assertCount(2, $this->sent);
        self::assertSame('sent', $this->mail->recent()[0]['status']);
    }
}
