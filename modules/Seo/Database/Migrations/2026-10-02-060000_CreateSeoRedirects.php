<?php

declare(strict_types=1);

namespace MCMS\Modules\Seo\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateSeoRedirects extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'source_path' => ['type' => 'VARCHAR', 'constraint' => 500],
            'provider' => ['type' => 'VARCHAR', 'constraint' => 32],
            'resource_id' => ['type' => 'VARCHAR', 'constraint' => 190],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('source_path');
        $this->forge->createTable('seo_redirects', true);
    }

    public function down()
    {
        $this->forge->dropTable('seo_redirects', true);
    }
}
