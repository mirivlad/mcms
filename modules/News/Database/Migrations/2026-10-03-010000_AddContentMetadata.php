<?php

declare(strict_types=1);

namespace MCMS\Modules\News\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddContentMetadata extends Migration
{
    public function up()
    {
        $this->forge->addColumn('news_articles', [
            'tags' => ['type' => 'VARCHAR', 'constraint' => 1000, 'null' => true],
            'seo_keywords' => ['type' => 'VARCHAR', 'constraint' => 1000, 'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('news_articles', ['tags', 'seo_keywords']);
    }
}
