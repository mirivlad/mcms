<?php

declare(strict_types=1);

namespace MCMS\Modules\Media\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateMediaFiles extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'original_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'storage_path' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
            ],
            'mime_type' => [
                'type' => 'VARCHAR',
                'constraint' => 128,
            ],
            'extension' => [
                'type' => 'VARCHAR',
                'constraint' => 16,
            ],
            'size_bytes' => [
                'type' => 'BIGINT',
                'unsigned' => true,
            ],
            'width' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
            ],
            'height' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
            ],
            'sha256' => [
                'type' => 'CHAR',
                'constraint' => 64,
            ],
            'title' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'alt_text' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'uploaded_by' => [
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
        $this->forge->addUniqueKey('storage_path');
        $this->forge->addKey('created_at');
        $this->forge->addKey('mime_type');
        $this->forge->createTable('media_files', true);
    }

    public function down()
    {
        $this->forge->dropTable('media_files', true);
    }
}
