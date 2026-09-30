<?php

declare(strict_types=1);

namespace Tests\Support\FixtureModules\Alpha\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateAlphaRecords extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INTEGER',
                'auto_increment' => true,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('alpha_records', true);
    }

    public function down()
    {
        $this->forge->dropTable('alpha_records', true);
    }
}
