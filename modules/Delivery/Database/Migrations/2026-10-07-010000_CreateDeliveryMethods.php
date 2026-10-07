<?php

declare(strict_types=1);

namespace MCMS\Modules\Delivery\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateDeliveryMethods extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 190],
            'type' => ['type' => 'VARCHAR', 'constraint' => 16],
            'price_minor' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'enabled' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 1],
            'position' => ['type' => 'INT', 'unsigned' => true, 'default' => 100],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['enabled', 'position']);
        $this->forge->createTable('delivery_methods', true);
    }

    public function down()
    {
        $this->forge->dropTable('delivery_methods', true);
    }
}
