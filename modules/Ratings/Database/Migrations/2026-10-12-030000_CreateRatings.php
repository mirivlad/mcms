<?php

declare(strict_types=1);

namespace MCMS\Modules\Ratings\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateRatings extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'target_type' => ['type' => 'VARCHAR', 'constraint' => 32],
            'target_id' => ['type' => 'VARCHAR', 'constraint' => 128],
            'voter_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'user_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'stars' => ['type' => 'TINYINT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['target_type', 'target_id', 'voter_hash']);
        $this->forge->addKey(['target_type', 'target_id']);
        $this->forge->addKey('updated_at');
        $this->forge->createTable('rating_votes', true);
    }

    public function down()
    {
        $this->forge->dropTable('rating_votes', true);
    }
}
