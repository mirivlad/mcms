<?php

declare(strict_types=1);

namespace MCMS\Modules\Notifications\Services;

use Closure;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Email\Email;
use InvalidArgumentException;
use MCMS\Modules\Orders\Api\OrdersInterface;
use RuntimeException;
use Throwable;

final class Notifications
{
    public const EVENTS = ['new', 'processing', 'ready', 'completed', 'cancelled'];
    public const TOKENS = ['order_id', 'status', 'customer_name', 'phone', 'total', 'delivery', 'items', 'site_name'];

    public function __construct(
        private readonly BaseConnection $db,
        private readonly OrdersInterface $orders,
        private readonly ?Closure $sender = null,
    ) {
    }

    public function preferences(): array
    {
        $settings = service('settings');
        return [
            'enabled' => (bool) $settings->get('notifications.enabled'),
            'recipient' => (string) $settings->get('notifications.recipient'),
            'events' => (array) $settings->get('notifications.events'),
            'subject' => (string) ($settings->get('notifications.subject') ?: lang('Notifications.defaultSubject')),
            'body' => (string) ($settings->get('notifications.body') ?: lang('Notifications.defaultBody')),
        ];
    }

    public function save(array $input): void
    {
        foreach (['recipient', 'subject', 'body'] as $key) {
            if (isset($input[$key]) && ! is_string($input[$key])) {
                throw new InvalidArgumentException(lang('Notifications.invalid'));
            }
        }
        if (isset($input['events']) && (! is_array($input['events'])
            || array_filter($input['events'], static fn ($event): bool => ! is_string($event)) !== [])) {
            throw new InvalidArgumentException(lang('Notifications.invalid'));
        }
        $enabled = ! empty($input['enabled']);
        $recipient = trim((string) ($input['recipient'] ?? ''));
        $events = array_values(array_unique((array) ($input['events'] ?? [])));
        $subject = trim((string) ($input['subject'] ?? ''));
        $body = trim((string) ($input['body'] ?? ''));
        if (($recipient !== '' && (strlen($recipient) > 254 || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false))
            || ($enabled && ($recipient === '' || $events === []))
            || array_diff($events, self::EVENTS) !== []
            || $subject === '' || mb_strlen($subject) > 255 || preg_match('/[\r\n]/', $subject)
            || $body === '' || strlen($body) > 10000) {
            throw new InvalidArgumentException(lang('Notifications.invalid'));
        }
        foreach ([$subject, $body] as $template) {
            preg_match_all('/\{([^{}\s]+)\}/u', $template, $matches);
            if (array_diff($matches[1], self::TOKENS) !== []) {
                throw new InvalidArgumentException(lang('Notifications.invalidTokens'));
            }
        }
        foreach (compact('enabled', 'recipient', 'events', 'subject', 'body') as $key => $value) {
            service('settings')->set('notifications.' . $key, $value);
        }
    }

    public function commentPreferences(): array
    {
        return (array) (setting('notifications.comments') ?? ['enabled' => false, 'recipient' => '', 'events' => []]);
    }

    public function saveCommentPreferences(array $input): void
    {
        $enabled = ($input['enabled'] ?? null) === '1';
        $recipient = is_string($input['recipient'] ?? null) ? trim($input['recipient']) : '';
        $events = $input['events'] ?? [];
        if (! is_array($events) || array_filter($events, static fn ($value): bool => ! is_string($value)) !== []
            || array_diff($events, ['pending', 'published']) !== []
            || ($recipient !== '' && (strlen($recipient) > 254 || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false))
            || ($enabled && ($recipient === '' || $events === []))) {
            throw new InvalidArgumentException(lang('Notifications.invalid'));
        }
        service('settings')->set('notifications.comments', compact('enabled', 'recipient', 'events'));
    }

    public function recordComment(array $snapshot): void
    {
        $config = $this->commentPreferences();
        if (! $config['enabled'] || ! in_array($snapshot['event'], $config['events'], true)) {
            return;
        }
        $event = lang('Notifications.commentEvents.' . $snapshot['event']);
        $this->messageOutbox()->record($snapshot['key'], 'comments.' . $snapshot['event'], $config['recipient'],
            mb_substr($event . ' #' . $snapshot['id'], 0, 255),
            $event . "\n" . $snapshot['author'] . "\n\n" . $snapshot['body'] . "\n\n" . $snapshot['url']);
    }

    private function messageOutbox(): MessageOutbox
    {
        return new MessageOutbox($this->db, $this->send(...));
    }

    public function record(int $orderId, string $event, int $historyId): void
    {
        $config = $this->preferences();
        if (! $config['enabled'] || ! in_array($event, $config['events'], true)) {
            return;
        }
        $order = $this->orders->find($orderId) ?? throw new RuntimeException('Notification order snapshot is missing.');
        $values = [
            '{order_id}' => (string) $orderId,
            '{status}' => (string) $order['status_label'],
            '{customer_name}' => (string) $order['customer_name'],
            '{phone}' => (string) $order['phone'],
            '{total}' => $this->money((int) $order['total_minor'], (string) $order['currency']),
            '{delivery}' => (string) ($order['fulfillment_name'] ?? ''),
            '{items}' => implode("\n", array_map(fn (array $item): string => $item['product_title'] . ' / ' . $item['variant_label'] . ' × ' . $item['quantity'] . ' — ' . $this->money((int) $item['subtotal_minor'], (string) $item['currency']), $order['items'])),
            '{site_name}' => (string) setting('App.siteName'),
        ];
        $subject = mb_substr(preg_replace('/[\r\n]+/', ' ', strtr($config['subject'], $values)), 0, 255);
        if (! $this->db->table('notifications_outbox')->insert([
            'history_id' => $historyId, 'order_id' => $orderId, 'event' => $event,
            'recipient' => $config['recipient'], 'subject' => $subject, 'body' => strtr($config['body'], $values),
            'status' => 'pending', 'attempts' => 0, 'created_at' => date('Y-m-d H:i:s'),
        ])) {
            throw new RuntimeException('Could not persist the order notification.');
        }
    }

    /** @return array{sent:int,failed:int} */
    public function sendPending(?int $orderId = null, int $limit = 20): array
    {
        $result = ['sent' => 0, 'failed' => 0];
        $limit = max(1, min(100, $limit));
        if ($this->db->transDepth === 0 && $orderId === null && $this->commentPreferences()['enabled']) {
            $result = $this->messageOutbox()->sendPending($limit);
            $limit -= $result['sent'] + $result['failed'];
            if ($limit === 0) {
                return $result;
            }
        }
        if ($this->db->transDepth !== 0 || ! $this->preferences()['enabled']) {
            return $result;
        }
        // Allow at least five minutes, and longer for installations overriding
        // SMTPTimeout: a mail exchange has multiple blocking protocol steps.
        $lease = max(300, (int) config('Email')->SMTPTimeout * 10);
        $this->db->table('notifications_outbox')->where('status', 'sending')
            ->where('claimed_at <', date('Y-m-d H:i:s', time() - $lease))->update(['status' => 'pending']);
        $builder = $this->db->table('notifications_outbox')->where('status', 'pending')->orderBy('id')->limit(max(1, min(100, $limit)));
        if ($orderId !== null) {
            $builder->where('order_id', $orderId);
        }
        foreach ($builder->get()->getResultArray() as $row) {
            $this->db->table('notifications_outbox')->where('id', $row['id'])->where('status', 'pending')
                ->set('attempts', 'attempts + 1', false)->update(['status' => 'sending', 'claimed_at' => date('Y-m-d H:i:s')]);
            if ($this->db->affectedRows() !== 1) {
                continue;
            }
            $sent = $this->send((string) $row['recipient'], (string) $row['subject'], (string) $row['body']);
            $this->db->table('notifications_outbox')->where('id', $row['id'])->update([
                'status' => $sent ? 'sent' : 'pending', 'claimed_at' => null,
                'sent_at' => $sent ? date('Y-m-d H:i:s') : null,
                'last_error' => $sent ? null : lang('Notifications.sendFailed'),
            ]);
            $result[$sent ? 'sent' : 'failed']++;
        }
        return $result;
    }

    public function test(): bool
    {
        $recipient = $this->preferences()['recipient'];
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(lang('Notifications.recipientRequired'));
        }
        return $this->send($recipient, lang('Notifications.testSubject'), lang('Notifications.testBody'));
    }

    public function recent(): array
    {
        $rows = [...$this->db->table('notifications_outbox')->orderBy('id', 'DESC')->limit(20)->get()->getResultArray(),
            ...$this->db->table('notifications_messages')->orderBy('id', 'DESC')->limit(20)->get()->getResultArray()];
        usort($rows, static fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));
        return array_slice($rows, 0, 20);
    }

    public function pendingCount(): int
    {
        return $this->db->table('notifications_outbox')->whereIn('status', ['pending', 'sending'])->countAllResults()
            + $this->db->table('notifications_messages')->whereIn('status', ['pending', 'sending'])->countAllResults();
    }

    private function send(string $recipient, string $subject, string $body): bool
    {
        try {
            if ($this->sender !== null) {
                return (bool) ($this->sender)($recipient, $subject, $body);
            }
            $config = config('Email');
            $mail = new Email($config);
            $mail->setMailType('text');
            $mail->setFrom($config->fromEmail, $config->fromName);
            $mail->setTo($recipient);
            $mail->setSubject($subject);
            $mail->setMessage($body);
            return $mail->send();
        } catch (Throwable) {
            return false;
        }
    }

    private function money(int $minor, string $currency): string
    {
        return number_format($minor / 100, 2, '.', ' ') . ' ' . $currency;
    }
}
