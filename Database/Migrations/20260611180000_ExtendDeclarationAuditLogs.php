<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class ExtendDeclarationAuditLogs extends Migration
{
    public function up()
    {
        $fields = [];

        if (!$this->db->fieldExists('actor_type', 'declaration_audit_logs')) {
            $fields['actor_type'] = [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
                'after' => 'actor_user_id',
            ];
        }

        if (!$this->db->fieldExists('actor_label', 'declaration_audit_logs')) {
            $fields['actor_label'] = [
                'type' => 'VARCHAR',
                'constraint' => 190,
                'null' => true,
                'after' => 'actor_type',
            ];
        }

        if (!$this->db->fieldExists('person_id', 'declaration_audit_logs')) {
            $fields['person_id'] = [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
                'after' => 'entity_id',
            ];
        }

        if (!$this->db->fieldExists('employment_relation_id', 'declaration_audit_logs')) {
            $fields['employment_relation_id'] = [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
                'after' => 'person_id',
            ];
        }

        if (!$this->db->fieldExists('submission_id', 'declaration_audit_logs')) {
            $fields['submission_id'] = [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
                'after' => 'packet_item_id',
            ];
        }

        if (!$this->db->fieldExists('invitation_id', 'declaration_audit_logs')) {
            $fields['invitation_id'] = [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
                'after' => 'submission_id',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('declaration_audit_logs', $fields);
        }
    }

    public function down()
    {
        foreach ([
            'invitation_id',
            'submission_id',
            'employment_relation_id',
            'person_id',
            'actor_label',
            'actor_type',
        ] as $column) {
            if ($this->db->fieldExists($column, 'declaration_audit_logs')) {
                $this->forge->dropColumn('declaration_audit_logs', $column);
            }
        }
    }
}