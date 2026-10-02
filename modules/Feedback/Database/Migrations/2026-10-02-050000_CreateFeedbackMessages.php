<?php

declare(strict_types=1);

namespace MCMS\Modules\Feedback\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateFeedbackMessages extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'email' => ['type' => 'VARCHAR', 'constraint' => 254],
            'message' => ['type' => 'TEXT'],
            'created_at' => ['type' => 'DATETIME'],
            'read_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('created_at');
        $this->forge->createTable('feedback_messages', true);
    }

    public function down()
    {
        $this->forge->dropTable('feedback_messages', true);
    }
}
