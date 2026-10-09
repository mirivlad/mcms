<?php

declare(strict_types=1);

namespace MCMS\Modules\Notifications\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateNotificationOutbox extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'history_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'order_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'event' => ['type' => 'VARCHAR', 'constraint' => 32],
            'recipient' => ['type' => 'VARCHAR', 'constraint' => 254],
            'subject' => ['type' => 'VARCHAR', 'constraint' => 255],
            'body' => ['type' => 'TEXT'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 16],
            'attempts' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'last_error' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'claimed_at' => ['type' => 'DATETIME', 'null' => true],
            'sent_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('history_id');
        $this->forge->addKey(['status', 'id']);
        $this->forge->createTable('notifications_outbox', true);
    }

    public function down()
    {
        $this->forge->dropTable('notifications_outbox', true);
    }
}
