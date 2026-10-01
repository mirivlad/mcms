<?php

declare(strict_types=1);

namespace MCMS\Modules\News\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateNewsArticles extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'title' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'slug' => [
                'type' => 'VARCHAR',
                'constraint' => 190,
            ],
            'excerpt' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'body' => [
                'type' => 'MEDIUMTEXT',
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'draft',
            ],
            'featured_media_id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'null' => true,
            ],
            'seo_title' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'seo_description' => [
                'type' => 'VARCHAR',
                'constraint' => 320,
                'null' => true,
            ],
            'published_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_by' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'null' => true,
            ],
            'updated_by' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey(['status', 'published_at']);
        $this->forge->addKey('featured_media_id');
        $this->forge->createTable('news_articles', true);
    }

    public function down()
    {
        $this->forge->dropTable('news_articles', true);
    }
}
