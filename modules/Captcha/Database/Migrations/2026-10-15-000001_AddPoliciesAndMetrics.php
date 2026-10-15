<?php

declare(strict_types=1);

namespace MCMS\Modules\Captcha\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddPoliciesAndMetrics extends Migration
{
    public function up()
    {
        $this->forge->addColumn('captcha_challenges', ['outcome' => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true]]);
        $this->forge->addField([
            'day' => ['type' => 'VARCHAR', 'constraint' => 10],
            'purpose' => ['type' => 'VARCHAR', 'constraint' => 64],
            'outcome' => ['type' => 'VARCHAR', 'constraint' => 16],
            'total' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
        ]);
        $this->forge->addPrimaryKey(['day', 'purpose', 'outcome']);
        $this->forge->createTable('captcha_metrics', true);
        $this->forge->addField([
            'scope_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'window_start' => ['type' => 'INT', 'unsigned' => true],
            'hits' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
        ]);
        $this->forge->addPrimaryKey(['scope_hash', 'window_start']);
        $this->forge->createTable('captcha_limits', true);
    }

    public function down()
    {
        $this->forge->dropTable('captcha_limits', true);
        $this->forge->dropTable('captcha_metrics', true);
        $this->forge->dropColumn('captcha_challenges', 'outcome');
    }
}
