<?php

declare(strict_types=1);

namespace MCMS\Modules\News\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateContentWorkflow extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'content_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'snapshot' => ['type' => 'MEDIUMTEXT'],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('content_id');
        $this->forge->createTable('news_revisions', true);
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'content_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'snapshot' => ['type' => 'MEDIUMTEXT'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'content_id']);
        $this->forge->createTable('news_drafts', true);
    }

    public function down()
    {
        $this->forge->dropTable('news_drafts', true);
        $this->forge->dropTable('news_revisions', true);
    }
}
