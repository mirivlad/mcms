<?php

declare(strict_types=1);

namespace MCMS\Modules\Seo\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddManualRedirectTargets extends Migration
{
    public function up()
    {
        $this->forge->addColumn('seo_redirects', [
            'target_path' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
        ]);
    }

    public function down()
    {
        $this->db->table('seo_redirects')->where('provider', '')->delete();
        $this->forge->dropColumn('seo_redirects', 'target_path');
    }
}
