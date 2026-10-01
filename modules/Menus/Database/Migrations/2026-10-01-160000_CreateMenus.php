<?php

declare(strict_types=1);

namespace MCMS\Modules\Menus\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateMenus extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
            ],
            'handle' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
            'description' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
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
        $this->forge->addUniqueKey('handle');
        $this->forge->createTable('menus', true);

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'menu_id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
            ],
            'parent_id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'null' => true,
            ],
            'label' => [
                'type' => 'VARCHAR',
                'constraint' => 190,
            ],
            'target_type' => [
                'type' => 'VARCHAR',
                'constraint' => 32,
                'default' => 'custom',
            ],
            'target' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
                'null' => true,
            ],
            'position' => [
                'type' => 'INT',
                'unsigned' => true,
                'default' => 0,
            ],
            'enabled' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'unsigned' => true,
                'default' => 1,
            ],
            'open_in_new' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'unsigned' => true,
                'default' => 0,
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
        $this->forge->addKey('menu_id');
        $this->forge->addKey('parent_id');
        $this->forge->addKey(['menu_id', 'position']);
        $this->forge->addKey(['menu_id', 'enabled']);
        $this->forge->createTable('menu_items', true);
    }

    public function down()
    {
        $this->forge->dropTable('menu_items', true);
        $this->forge->dropTable('menus', true);
    }
}