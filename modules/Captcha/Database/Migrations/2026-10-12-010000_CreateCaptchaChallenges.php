<?php

declare(strict_types=1);

namespace MCMS\Modules\Captcha\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateCaptchaChallenges extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'token_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'owner_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'purpose' => ['type' => 'VARCHAR', 'constraint' => 64],
            'answer_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'salt' => ['type' => 'CHAR', 'constraint' => 32],
            'puzzle' => ['type' => 'TEXT'],
            'created_at' => ['type' => 'DATETIME'],
            'expires_at' => ['type' => 'DATETIME'],
            'used_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey('expires_at');
        $this->forge->addKey(['owner_hash', 'created_at']);
        $this->forge->createTable('captcha_challenges', true);
    }

    public function down()
    {
        $this->forge->dropTable('captcha_challenges', true);
    }
}
