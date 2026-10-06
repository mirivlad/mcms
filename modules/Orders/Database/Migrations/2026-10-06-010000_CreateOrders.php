<?php

declare(strict_types=1);

namespace MCMS\Modules\Orders\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateOrders extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'new'],
            'customer_name' => ['type' => 'VARCHAR', 'constraint' => 190],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 64],
            'email' => ['type' => 'VARCHAR', 'constraint' => 254, 'null' => true],
            'comment' => ['type' => 'TEXT', 'null' => true],
            'subtotal_minor' => ['type' => 'BIGINT', 'unsigned' => true],
            'currency' => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'RUB'],
            'reservation_key' => ['type' => 'VARCHAR', 'constraint' => 80],
            'customer_user_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'manager_user_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
            'cancelled_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('status');
        $this->forge->addKey('created_at');
        $this->forge->addUniqueKey('reservation_key');
        $this->forge->createTable('orders', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'order_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'variant_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'product_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'product_title' => ['type' => 'VARCHAR', 'constraint' => 255],
            'product_slug' => ['type' => 'VARCHAR', 'constraint' => 190],
            'sku' => ['type' => 'VARCHAR', 'constraint' => 120],
            'variant_label' => ['type' => 'VARCHAR', 'constraint' => 190, 'default' => ''],
            'unit_price_minor' => ['type' => 'BIGINT', 'unsigned' => true],
            'quantity' => ['type' => 'INT', 'unsigned' => true],
            'subtotal_minor' => ['type' => 'BIGINT', 'unsigned' => true],
            'currency' => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'RUB'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('order_id');
        $this->forge->addKey('variant_id');
        $this->forge->addForeignKey('order_id', 'orders', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('order_items', true);

        $this->forge->addField([
            'order_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'variant_id' => ['type' => 'BIGINT', 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey(['order_id', 'variant_id']);
        $this->forge->addKey('variant_id');
        $this->forge->addForeignKey('order_id', 'orders', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('variant_id', 'catalogue_variants', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('order_variant_locks', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'order_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'from_status' => ['type' => 'VARCHAR', 'constraint' => 24, 'null' => true],
            'to_status' => ['type' => 'VARCHAR', 'constraint' => 24],
            'user_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'note' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('order_id');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('order_id', 'orders', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('order_status_history', true);
    }

    public function down()
    {
        $this->forge->dropTable('order_status_history', true);
        $this->forge->dropTable('order_variant_locks', true);
        $this->forge->dropTable('order_items', true);
        $this->forge->dropTable('orders', true);
    }
}
