<?php

namespace App\Modules\Declarations\Database\Migrations;

use App\Modules\Declarations\Services\DeclarationTemplateCatalogService;
use CodeIgniter\Database\Migration;

class SyncUnder3ChildWorkScheduleStatementTemplate extends Migration
{
    private const TEMPLATE_CODE = 'under_3_child_work_schedule_statement';

    public function up(): void
    {
        if (!$this->db->tableExists('declaration_templates')) {
            return;
        }

        (new DeclarationTemplateCatalogService())->sync();
    }

    public function down(): void
    {
        if (!$this->db->tableExists('declaration_templates')) {
            return;
        }

        $this->db->table('declaration_templates')
            ->where('code', self::TEMPLATE_CODE)
            ->update(['is_active' => 0]);
    }
}
