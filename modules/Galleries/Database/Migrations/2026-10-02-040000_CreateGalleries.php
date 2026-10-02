<?php

declare(strict_types=1);

namespace MCMS\Modules\Galleries\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateGalleries extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 255],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 190],
            'description' => ['type' => 'TEXT'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
            'cover_media_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey('status');
        $this->forge->createTable('galleries', true);

        $this->forge->addField([
            'gallery_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'media_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'caption' => ['type' => 'VARCHAR', 'constraint' => 500],
            'position' => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey(['gallery_id', 'media_id']);
        $this->forge->addKey('media_id');
        $this->forge->createTable('gallery_images', true);
    }

    public function down()
    {
        $this->forge->dropTable('gallery_images', true);
        $this->forge->dropTable('galleries', true);
    }
}
