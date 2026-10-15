<?php

declare(strict_types=1);

namespace MCMS\Modules\LifecycleDemo\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateDemoNotes extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'note' => ['type' => 'VARCHAR', 'constraint' => 200],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('lifecycle_demo_notes');
        $this->db->table('lifecycle_demo_notes')->insert(['note' => 'A persistent demo note']);
    }

    public function down()
    {
        $this->forge->dropTable('lifecycle_demo_notes', true);
    }
}
