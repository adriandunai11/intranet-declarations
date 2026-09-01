<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveDeclarationEmploymentRelations extends Migration
{
    public function up(): void
    {
        $this->addUnsignedIntColumnIfMissing('declaration_packets', 'primary_recruiter_user_id', true, 'company_id');
        $this->backfillPrimaryRecruiterFromRelations();

        $this->dropColumnIfExists('declaration_packets', 'employment_relation_id');
        $this->dropColumnIfExists('declaration_invitations', 'employment_relation_id');
        $this->dropColumnIfExists('declaration_submissions', 'employment_relation_id');
        $this->dropColumnIfExists('declaration_audit_logs', 'employment_relation_id');

        if ($this->db->tableExists('declaration_employment_relations')) {
            $this->forge->dropTable('declaration_employment_relations', true);
        }
    }

    public function down(): void
    {
        if (!$this->db->tableExists('declaration_employment_relations')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'person_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'company_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'primary_recruiter_user_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 30,
                    'default' => 'onboarding',
                ],
                'start_date' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'end_date' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'created_by_user_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('person_id');
            $this->forge->addKey('company_id');
            $this->forge->addKey('primary_recruiter_user_id');
            $this->forge->addKey('status');
            $this->forge->createTable('declaration_employment_relations', true);
        }

        $this->addUnsignedIntColumnIfMissing('declaration_packets', 'employment_relation_id', true, 'person_id');
        $this->addUnsignedIntColumnIfMissing('declaration_invitations', 'employment_relation_id', true, 'person_id');
        $this->addUnsignedIntColumnIfMissing('declaration_submissions', 'employment_relation_id', true, 'person_id');
        $this->addUnsignedIntColumnIfMissing('declaration_audit_logs', 'employment_relation_id', true, 'person_id');
    }

    private function backfillPrimaryRecruiterFromRelations(): void
    {
        if (!$this->db->tableExists('declaration_employment_relations')
            || !$this->db->fieldExists('employment_relation_id', 'declaration_packets')
            || !$this->db->fieldExists('primary_recruiter_user_id', 'declaration_packets')
            || !$this->db->fieldExists('primary_recruiter_user_id', 'declaration_employment_relations')
        ) {
            return;
        }

        $this->db->query(
            'UPDATE declaration_packets p
             INNER JOIN declaration_employment_relations r ON r.id = p.employment_relation_id
             SET p.primary_recruiter_user_id = r.primary_recruiter_user_id
             WHERE (p.primary_recruiter_user_id IS NULL OR p.primary_recruiter_user_id = 0)
             AND r.primary_recruiter_user_id IS NOT NULL'
        );
    }

    private function addUnsignedIntColumnIfMissing(string $table, string $column, bool $nullable, ?string $after = null): void
    {
        if (!$this->db->tableExists($table) || $this->db->fieldExists($column, $table)) {
            return;
        }

        $definition = [
            'type' => 'INT',
            'constraint' => 11,
            'unsigned' => true,
            'null' => $nullable,
        ];

        if ($after !== null && $this->db->fieldExists($after, $table)) {
            $definition['after'] = $after;
        }

        $this->forge->addColumn($table, [
            $column => $definition,
        ]);
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        if (!$this->db->tableExists($table) || !$this->db->fieldExists($column, $table)) {
            return;
        }

        $this->forge->dropColumn($table, $column);
    }
}
