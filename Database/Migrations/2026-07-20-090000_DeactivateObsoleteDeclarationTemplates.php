<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class DeactivateObsoleteDeclarationTemplates extends Migration
{
    private const TEMPLATE_CODES = [
        'absence_statement',
        'tb_booklet_statement',
        'employment_history_statement',
        'deduction_statement',
    ];

    public function up(): void
    {
        if (!$this->db->tableExists('declaration_templates')) {
            return;
        }

        $this->db->table('declaration_templates')
            ->whereIn('code', self::TEMPLATE_CODES)
            ->update(['is_active' => 0]);
    }

    public function down(): void
    {
        if (!$this->db->tableExists('declaration_templates')) {
            return;
        }

        $this->db->table('declaration_templates')
            ->whereIn('code', self::TEMPLATE_CODES)
            ->update(['is_active' => 1]);
    }
}
