<?php

declare(strict_types=1);

namespace Tests\Support\FixtureModules\Beta\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateBetaRecords extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INTEGER',
                'auto_increment' => true,
            ],
            'alpha_id' => [
                'type' => 'INTEGER',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('beta_records', true);
    }

    public function down()
    {
        $this->forge->dropTable('beta_records', true);
    }
}
