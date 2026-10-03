<?php

declare(strict_types=1);

namespace MCMS\Modules\Catalogue\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateCatalogue extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 255],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 190],
            'category' => ['type' => 'VARCHAR', 'constraint' => 120],
            'summary' => ['type' => 'TEXT'], 'body' => ['type' => 'TEXT'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20],
            'image_set_id' => ['type' => 'VARCHAR', 'constraint' => 32],
            'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->addUniqueKey('image_set_id');
        $this->forge->addKey('status');
        $this->forge->createTable('catalogue_products', true);
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'product_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'sku' => ['type' => 'VARCHAR', 'constraint' => 100],
            'label' => ['type' => 'VARCHAR', 'constraint' => 120],
            'price_minor' => ['type' => 'BIGINT', 'unsigned' => true],
            'currency' => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'RUB'],
            'position' => ['type' => 'INT'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('sku');
        $this->forge->addKey('product_id');
        $this->forge->createTable('catalogue_variants', true);
    }

    public function down()
    {
        $this->forge->dropTable('catalogue_variants', true);
        $this->forge->dropTable('catalogue_products', true);
    }
}
