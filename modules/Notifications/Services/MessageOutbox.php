<?php

declare(strict_types=1);

namespace MCMS\Modules\Notifications\Services;

use Closure;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/** Non-order messages share the transport, claim lease and retry semantics. */
final class MessageOutbox
{
    public function __construct(private readonly BaseConnection $db, private readonly Closure $send) {}

    public function record(string $key, string $event, string $recipient, string $subject, string $body): void
    {
        if (! $this->db->table('notifications_messages')->ignore(true)->insert([
            'event_key' => $key, 'event' => $event, 'recipient' => $recipient,
            'subject' => $subject, 'body' => $body, 'status' => 'pending',
            'attempts' => 0, 'created_at' => date('Y-m-d H:i:s'),
        ])) {
            throw new RuntimeException('Cannot save notification.');
        }
    }

    public function sendPending(int $limit): array
    {
        $result = ['sent' => 0, 'failed' => 0];
        if ($this->db->transDepth !== 0) {
            return $result;
        }
        $this->db->table('notifications_messages')->where('status', 'sending')
            ->where('claimed_at <', date('Y-m-d H:i:s', time() - max(300, (int) config('Email')->SMTPTimeout * 10)))
            ->update(['status' => 'pending']);
        $rows = $this->db->table('notifications_messages')->where('status', 'pending')->orderBy('id')->limit(max(1, min(100, $limit)))->get()->getResultArray();
        foreach ($rows as $row) {
            $source = null;
            foreach (service('mcmsModuleServices')->all() as $provider) {
                if ($provider instanceof \MCMS\Core\Events\NotificationEventSourceInterface && $provider->supportsNotification($row['event'])) {
                    $source = $provider;
                    break;
                }
            }
            if ($source === null) {
                continue; // Disabled providers preserve their pending queue.
            }
            if (! $source->notificationCurrent($row['event_key'])) {
                $this->db->table('notifications_messages')->where('id', $row['id'])->where('status', 'pending')->update(['status' => 'cancelled']);
                continue;
            }
            $this->db->table('notifications_messages')->where('id', $row['id'])->where('status', 'pending')
                ->set('attempts', 'attempts + 1', false)->update(['status' => 'sending', 'claimed_at' => date('Y-m-d H:i:s')]);
            if ($this->db->affectedRows() !== 1) {
                continue;
            }
            $sent = ($this->send)($row['recipient'], $row['subject'], $row['body']);
            $this->db->table('notifications_messages')->where('id', $row['id'])->update([
                'status' => $sent ? 'sent' : 'pending', 'claimed_at' => null,
                'sent_at' => $sent ? date('Y-m-d H:i:s') : null,
                'last_error' => $sent ? null : lang('Notifications.sendFailed'),
            ]);
            $result[$sent ? 'sent' : 'failed']++;
        }
        return $result;
    }
}
