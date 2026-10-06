<?php

declare(strict_types=1);

namespace MCMS\Modules\Stock\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateStock extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'variant_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'on_hand' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'reserved' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'updated_at' => ['type' => 'DATETIME'],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('variant_id');
        $this->forge->addForeignKey('variant_id', 'catalogue_variants', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('stock_levels', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'reservation_key' => ['type' => 'VARCHAR', 'constraint' => 80],
            'variant_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'quantity' => ['type' => 'INT', 'unsigned' => true],
            'expires_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['reservation_key', 'variant_id']);
        $this->forge->addKey('expires_at');
        $this->forge->addForeignKey('variant_id', 'catalogue_variants', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('stock_reservations', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'variant_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'change_qty' => ['type' => 'INT'],
            'before_qty' => ['type' => 'INT', 'unsigned' => true],
            'after_qty' => ['type' => 'INT', 'unsigned' => true],
            'user_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'note' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('variant_id');
        $this->forge->addKey('created_at');
        $this->forge->createTable('stock_movements', true);
    }

    public function down()
    {
        $this->forge->dropTable('stock_movements', true);
        $this->forge->dropTable('stock_reservations', true);
        $this->forge->dropTable('stock_levels', true);
    }
}
