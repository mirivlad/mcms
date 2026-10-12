<?php

declare(strict_types=1);

namespace MCMS\Modules\Comments\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateComments extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'target_type' => ['type' => 'VARCHAR', 'constraint' => 32],
            'target_id' => ['type' => 'VARCHAR', 'constraint' => 128],
            'parent_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'depth' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'user_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'author_name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'author_email' => ['type' => 'VARCHAR', 'constraint' => 254, 'null' => true],
            'body' => ['type' => 'TEXT'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 16],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['target_type', 'target_id', 'status']);
        $this->forge->addKey(['status', 'created_at']);
        $this->forge->addKey('parent_id');
        $this->forge->createTable('comments', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'comment_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'actor_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'reason' => ['type' => 'VARCHAR', 'constraint' => 250],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['comment_id', 'actor_hash']);
        $this->forge->addKey('comment_id');
        $this->forge->createTable('comments_reports', true);
    }

    public function down()
    {
        $this->forge->dropTable('comments_reports', true);
        $this->forge->dropTable('comments', true);
    }
}
