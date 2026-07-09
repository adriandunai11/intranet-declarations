<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSubmissionEvidenceFields extends Migration
{
    public function up(): void
    {
        $fields = [];

        if (!$this->db->fieldExists('submitter_type', 'declaration_submissions')) {
            $fields['submitter_type'] = [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
                'after' => 'status',
            ];
        }

        if (!$this->db->fieldExists('submitter_user_id', 'declaration_submissions')) {
            $fields['submitter_user_id'] = [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
                'after' => 'submitter_type',
            ];
        }

        if (!$this->db->fieldExists('submitter_label', 'declaration_submissions')) {
            $fields['submitter_label'] = [
                'type' => 'VARCHAR',
                'constraint' => 190,
                'null' => true,
                'after' => 'submitter_user_id',
            ];
        }

        if (!$this->db->fieldExists('submitter_email', 'declaration_submissions')) {
            $fields['submitter_email'] = [
                'type' => 'VARCHAR',
                'constraint' => 190,
                'null' => true,
                'after' => 'submitter_label',
            ];
        }

        if (!$this->db->fieldExists('submitter_ip_address', 'declaration_submissions')) {
            $fields['submitter_ip_address'] = [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'null' => true,
                'after' => 'submitter_email',
            ];
        }

        if (!$this->db->fieldExists('submitter_user_agent', 'declaration_submissions')) {
            $fields['submitter_user_agent'] = [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'submitter_ip_address',
            ];
        }

        if (!$this->db->fieldExists('submission_hash', 'declaration_submissions')) {
            $fields['submission_hash'] = [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'null' => true,
                'after' => 'data_json',
            ];
        }

        if (!$this->db->fieldExists('hash_algorithm', 'declaration_submissions')) {
            $fields['hash_algorithm'] = [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
                'after' => 'submission_hash',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('declaration_submissions', $fields);
        }
    }

    public function down(): void
    {
        foreach ([
            'hash_algorithm',
            'submission_hash',
            'submitter_user_agent',
            'submitter_ip_address',
            'submitter_email',
            'submitter_label',
            'submitter_user_id',
            'submitter_type',
        ] as $column) {
            if ($this->db->fieldExists($column, 'declaration_submissions')) {
                $this->forge->dropColumn('declaration_submissions', $column);
            }
        }
    }
}
