<?php

declare(strict_types=1);

namespace MCMS\Modules\Pages\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddContentMetadata extends Migration
{
    public function up()
    {
        $this->forge->addColumn('pages', [
            'tags' => ['type' => 'VARCHAR', 'constraint' => 1000, 'null' => true],
            'seo_keywords' => ['type' => 'VARCHAR', 'constraint' => 1000, 'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('pages', ['tags', 'seo_keywords']);
    }
}
