<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveRedundantRecruiterAndCandidateSnapshotFields extends Migration
{
    public function up()
    {
        foreach ([
            'primary_recruiter_name',
            'primary_recruiter_email',
            'candidate_identifier',
            'candidate_email',
        ] as $column) {
            if ($this->db->fieldExists($column, 'declaration_employment_relations')) {
                $this->forge->dropColumn('declaration_employment_relations', $column);
            }
        }
    }

    public function down()
    {
        $fields = [];

        if (!$this->db->fieldExists('primary_recruiter_name', 'declaration_employment_relations')) {
            $fields['primary_recruiter_name'] = [
                'type' => 'VARCHAR',
                'constraint' => 190,
                'null' => true,
                'after' => 'primary_recruiter_user_id',
            ];
        }

        if (!$this->db->fieldExists('primary_recruiter_email', 'declaration_employment_relations')) {
            $fields['primary_recruiter_email'] = [
                'type' => 'VARCHAR',
                'constraint' => 190,
                'null' => true,
                'after' => 'primary_recruiter_name',
            ];
        }

        if (!$this->db->fieldExists('candidate_identifier', 'declaration_employment_relations')) {
            $fields['candidate_identifier'] = [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
                'after' => 'primary_recruiter_email',
            ];
        }

        if (!$this->db->fieldExists('candidate_email', 'declaration_employment_relations')) {
            $fields['candidate_email'] = [
                'type' => 'VARCHAR',
                'constraint' => 190,
                'null' => true,
                'after' => 'candidate_identifier',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('declaration_employment_relations', $fields);
        }
    }
}
