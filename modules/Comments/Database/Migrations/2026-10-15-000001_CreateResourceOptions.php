<?php

declare(strict_types=1);

namespace MCMS\Modules\Comments\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateResourceOptions extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'target_type' => ['type' => 'VARCHAR', 'constraint' => 32],
            'target_id' => ['type' => 'VARCHAR', 'constraint' => 128],
            'mode' => ['type' => 'VARCHAR', 'constraint' => 8, 'default' => 'inherit'],
        ]);
        $this->forge->addPrimaryKey(['target_type', 'target_id']);
        $this->forge->createTable('comments_resource_options', true);
    }

    public function down()
    {
        $this->forge->dropTable('comments_resource_options', true);
    }
}
