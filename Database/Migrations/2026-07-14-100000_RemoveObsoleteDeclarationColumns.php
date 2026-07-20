<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveObsoleteDeclarationColumns extends Migration
{
    public function up(): void
    {
        $this->dropColumnIfExists('declaration_employment_relations', 'intranet_user_id');
        $this->dropColumnIfExists('declaration_employment_relations', 'previous_relation_id');
        $this->dropColumnIfExists('declaration_templates', 'needs_signature');
    }

    public function down(): void
    {
        $relationFields = [];

        if (!$this->db->fieldExists('intranet_user_id', 'declaration_employment_relations')) {
            $relationFields['intranet_user_id'] = [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'company_id',
            ];
        }

        if (!$this->db->fieldExists('previous_relation_id', 'declaration_employment_relations')) {
            $relationFields['previous_relation_id'] = [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'end_date',
            ];
        }

        if ($relationFields !== []) {
            $this->forge->addColumn('declaration_employment_relations', $relationFields);
        }

        if ($this->db->fieldExists('intranet_user_id', 'declaration_employment_relations')) {
            $this->db->query(
                'ALTER TABLE declaration_employment_relations ADD INDEX declaration_employment_relations_intranet_user_id (intranet_user_id)'
            );
        }

        if ($this->db->fieldExists('previous_relation_id', 'declaration_employment_relations')) {
            $this->db->query(
                'ALTER TABLE declaration_employment_relations ADD INDEX declaration_employment_relations_previous_relation_id (previous_relation_id)'
            );
        }

        if (!$this->db->fieldExists('needs_signature', 'declaration_templates')) {
            $this->forge->addColumn('declaration_templates', [
                'needs_signature' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0,
                    'after' => 'review_role',
                ],
            ]);
        }
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        if (!$this->db->fieldExists($column, $table)) {
            return;
        }

        $this->forge->dropColumn($table, $column);
    }
}
