<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRecruiterAndTemplateWorkflowFields extends Migration
{
    public function up()
    {
        $this->forge->addColumn('declaration_employment_relations', [
            'primary_recruiter_user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'intranet_user_id',
            ],
            'onboarding_type' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'candidate',
                'after' => 'primary_recruiter_user_id',
            ],
        ]);

        $this->db->query('ALTER TABLE declaration_employment_relations ADD INDEX declaration_employment_relations_primary_recruiter_user_id (primary_recruiter_user_id)');
        $this->db->query('ALTER TABLE declaration_employment_relations ADD INDEX declaration_employment_relations_onboarding_type (onboarding_type)');

        $this->forge->addColumn('declaration_templates', [
            'declaration_group' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'employment',
                'after' => 'category',
            ],
            'review_role' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'recruiter',
                'after' => 'required_policy',
            ],
            'needs_signature' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
                'after' => 'review_role',
            ],
            'is_candidate_selectable' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
                'after' => 'needs_signature',
            ],
        ]);

        $this->db->query('ALTER TABLE declaration_templates ADD INDEX declaration_templates_declaration_group (declaration_group)');
        $this->db->query('ALTER TABLE declaration_templates ADD INDEX declaration_templates_review_role (review_role)');
    }

    public function down()
    {
        foreach ([
            'primary_recruiter_user_id',
            'onboarding_type',
        ] as $column) {
            if ($this->db->fieldExists($column, 'declaration_employment_relations')) {
                $this->forge->dropColumn('declaration_employment_relations', $column);
            }
        }

        foreach ([
            'declaration_group',
            'review_role',
            'needs_signature',
            'is_candidate_selectable',
        ] as $column) {
            if ($this->db->fieldExists($column, 'declaration_templates')) {
                $this->forge->dropColumn('declaration_templates', $column);
            }
        }
    }
}
