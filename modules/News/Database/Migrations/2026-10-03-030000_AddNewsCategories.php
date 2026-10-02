<?php

declare(strict_types=1);

namespace MCMS\Modules\News\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddNewsCategories extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 190],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('news_categories', true);
        $this->forge->addColumn('news_articles', ['category_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true]]);
        $this->forge->addKey('category_id');
        $this->forge->addForeignKey('category_id', 'news_categories', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->processIndexes('news_articles');
    }

    public function down()
    {
        foreach ($this->db->getForeignKeyData('news_articles') as $name => $key) {
            if (in_array('category_id', $key->column_name, true)) {
                $this->forge->dropForeignKey('news_articles', $key->constraint_name ?? $name);
            }
        }
        $this->forge->dropColumn('news_articles', 'category_id');
        $this->forge->dropTable('news_categories', true);
    }
}
