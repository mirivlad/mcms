<?php

declare(strict_types=1);

namespace MCMS\Modules\Notifications\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateMessageOutbox extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'event_key' => ['type' => 'VARCHAR', 'constraint' => 128],
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
        $this->forge->addUniqueKey('event_key');
        $this->forge->addKey(['status', 'id']);
        $this->forge->createTable('notifications_messages', true);
    }

    public function down()
    {
        $this->forge->dropTable('notifications_messages', true);
    }
}
