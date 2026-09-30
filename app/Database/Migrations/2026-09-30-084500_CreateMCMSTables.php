<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateMCMSTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'module_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            'version' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'enabled' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'installed_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);
        $this->forge->addPrimaryKey('module_id');
        $this->forge->createTable('mcms_modules', true);
    }

    public function down()
    {
        $this->forge->dropTable('mcms_modules', true);
    }
}
