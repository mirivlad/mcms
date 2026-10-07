<?php

declare(strict_types=1);

namespace MCMS\Modules\Orders\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddFulfillmentSnapshot extends Migration
{
    public function up()
    {
        $this->forge->addColumn('orders', [
            'fulfillment_method_id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'null' => true,
                'after' => 'comment',
            ],
            'fulfillment_type' => [
                'type' => 'VARCHAR',
                'constraint' => 16,
                'null' => true,
                'after' => 'fulfillment_method_id',
            ],
            'fulfillment_name' => [
                'type' => 'VARCHAR',
                'constraint' => 190,
                'null' => true,
                'after' => 'fulfillment_type',
            ],
            'delivery_minor' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'default' => 0,
                'after' => 'subtotal_minor',
            ],
            'total_minor' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'default' => 0,
                'after' => 'delivery_minor',
            ],
        ]);

        $this->db->table('orders')->set('total_minor', 'subtotal_minor', false)->update();
    }

    public function down()
    {
        $this->forge->dropColumn('orders', [
            'fulfillment_method_id',
            'fulfillment_type',
            'fulfillment_name',
            'delivery_minor',
            'total_minor',
        ]);
    }
}
