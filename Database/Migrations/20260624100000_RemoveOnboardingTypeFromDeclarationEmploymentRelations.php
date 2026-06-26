<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveOnboardingTypeFromDeclarationEmploymentRelations extends Migration
{
    public function up(): void
    {
        if (!$this->db->fieldExists('onboarding_type', 'declaration_employment_relations')) {
            return;
        }

        $this->forge->dropColumn('declaration_employment_relations', 'onboarding_type');
    }

    public function down(): void
    {
        if ($this->db->fieldExists('onboarding_type', 'declaration_employment_relations')) {
            return;
        }

        $this->forge->addColumn('declaration_employment_relations', [
            'onboarding_type' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'candidate',
                'after' => 'primary_recruiter_user_id',
            ],
        ]);
    }
}
