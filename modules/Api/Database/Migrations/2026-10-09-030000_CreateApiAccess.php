<?php

declare(strict_types=1);

namespace MCMS\Modules\Api\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateApiAccess extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 190],
            'user_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'token_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'scopes' => ['type' => 'TEXT'],
            'created_at' => ['type' => 'DATETIME'],
            'expires_at' => ['type' => 'DATETIME', 'null' => true],
            'revoked_at' => ['type' => 'DATETIME', 'null' => true],
            'last_used_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('token_hash');
        // Shield's user IDs are INT rather than BIGINT; application checks owner deletion.
        $this->forge->addKey('user_id');
        $this->forge->createTable('api_keys', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'key_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'request_key_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'request_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'response_json' => ['type' => 'TEXT'],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['key_id', 'request_key_hash']);
        $this->forge->addForeignKey('key_id', 'api_keys', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('api_operations', true);
    }

    public function down()
    {
        $this->forge->dropTable('api_operations', true);
        $this->forge->dropTable('api_keys', true);
    }
}
