<?php

declare(strict_types=1);

namespace MCMS\Modules\ProductImages\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateProductImages extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 32],
            'owner_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'attached' => ['type' => 'INT', 'default' => 0],
            'main_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('product_image_sets', true);
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'set_id' => ['type' => 'VARCHAR', 'constraint' => 32],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'original' => ['type' => 'VARCHAR', 'constraint' => 64],
            'mime' => ['type' => 'VARCHAR', 'constraint' => 40],
            'width' => ['type' => 'INT'], 'height' => ['type' => 'INT'],
            'recipe' => ['type' => 'TEXT'],
            'revision' => ['type' => 'VARCHAR', 'constraint' => 32],
            'position' => ['type' => 'INT'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('set_id');
        $this->forge->createTable('product_images', true);
    }

    public function down()
    {
        $this->forge->dropTable('product_images', true);
        $this->forge->dropTable('product_image_sets', true);
    }
}
