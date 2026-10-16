<?php

declare(strict_types=1);

namespace MCMS\Modules\Analytics\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateAnalytics extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 64],
            'visitor' => ['type' => 'CHAR', 'constraint' => 64],
            'source' => ['type' => 'VARCHAR', 'constraint' => 253],
            'device' => ['type' => 'VARCHAR', 'constraint' => 16],
            'last_seen' => ['type' => 'BIGINT'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('last_seen');
        $this->forge->createTable('analytics_visits', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'visit_id' => ['type' => 'CHAR', 'constraint' => 64],
            'viewed_at' => ['type' => 'BIGINT'],
            'day' => ['type' => 'CHAR', 'constraint' => 10],
            'path' => ['type' => 'VARCHAR', 'constraint' => 500],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('visit_id');
        $this->forge->addKey('viewed_at');
        $this->forge->addKey('day');
        $this->forge->addForeignKey('visit_id', 'analytics_visits', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('analytics_pageviews', true);

        $this->forge->addField([
            'id' => ['type' => 'INT'],
            'cleaned_day' => ['type' => 'CHAR', 'constraint' => 10, 'default' => ''],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('analytics_state', true);
        $this->db->table('analytics_state')->ignore(true)->insert(['id' => 1, 'cleaned_day' => '']);
    }

    public function down()
    {
        $this->forge->dropTable('analytics_pageviews', true);
        $this->forge->dropTable('analytics_visits', true);
        $this->forge->dropTable('analytics_state', true);
    }
}
