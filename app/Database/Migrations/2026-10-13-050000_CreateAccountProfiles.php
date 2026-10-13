<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateAccountProfiles extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'user_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'full_name' => ['type' => 'VARCHAR', 'constraint' => 190, 'default' => ''],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => ''],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('user_id');
        $this->forge->createTable('account_profiles', true);
    }

    public function down()
    {
        $this->forge->dropTable('account_profiles', true);
    }
}
