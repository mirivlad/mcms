<?php

declare(strict_types=1);

namespace MCMS\Modules\Catalogue\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateCatalogueCategories extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'parent_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 190],
            'icon_type' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'tabler'],
            'icon' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'folder'],
            'icon_color' => ['type' => 'VARCHAR', 'constraint' => 7, 'default' => '#206bc4'],
            'image' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->addForeignKey('parent_id', 'catalogue_categories', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('catalogue_categories', true);
        $this->forge->addColumn('catalogue_products', ['category_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true]]);
        $this->forge->addKey('category_id');
        $this->forge->addForeignKey('category_id', 'catalogue_categories', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->processIndexes('catalogue_products');

        // Preserve existing labels and associations, including non-Latin names.
        $names = $this->db->table('catalogue_products')->select('category')->where('category !=', '')->distinct()->orderBy('category')->get()->getResultArray();
        foreach ($names as $index => $row) {
            $slug = 'category-' . ($index + 1);
            $this->db->table('catalogue_categories')->insert([
                'name' => $row['category'], 'slug' => $slug, 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->table('catalogue_products')->where('category', $row['category'])->update(['category_id' => (int) $this->db->insertID()]);
        }
    }

    public function down()
    {
        foreach ($this->db->getForeignKeyData('catalogue_products') as $name => $key) {
            if (in_array('category_id', $key->column_name, true)) {
                $this->forge->dropForeignKey('catalogue_products', $key->constraint_name ?? $name);
            }
        }
        $this->forge->dropColumn('catalogue_products', 'category_id');
        $this->forge->dropTable('catalogue_categories', true);
    }
}
