<?php

declare(strict_types=1);

namespace MCMS\Modules\News\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddNewsCategoryPosition extends Migration
{
    public function up()
    {
        $this->forge->addColumn('news_categories', [
            'position' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('news_categories', 'position');
    }
}
