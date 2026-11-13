<?php

declare(strict_types=1);

namespace MCMS\Modules\Catalogue\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCategoryPosition extends Migration
{
    public function up()
    {
        $this->forge->addColumn('catalogue_categories', [
            'position' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
        ]);
        // Zero preserves the old name-based order until the admin first reorders.
    }

    public function down()
    {
        $this->forge->dropColumn('catalogue_categories', 'position');
    }
}
