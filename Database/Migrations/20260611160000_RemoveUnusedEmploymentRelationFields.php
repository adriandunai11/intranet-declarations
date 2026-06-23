<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveUnusedEmploymentRelationFields extends Migration
{
    public function up()
    {
        foreach (['employment_type', 'position', 'end_date'] as $column) {
            if ($this->db->fieldExists($column, 'declaration_employment_relations')) {
                $this->forge->dropColumn('declaration_employment_relations', $column);
            }
        }
    }

    public function down()
    {
        if (!$this->db->fieldExists('employment_type', 'declaration_employment_relations')) {
            $this->forge->addColumn('declaration_employment_relations', [
                'employment_type' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                    'after' => 'status',
                ],
            ]);
        }

        if (!$this->db->fieldExists('position', 'declaration_employment_relations')) {
            $this->forge->addColumn('declaration_employment_relations', [
                'position' => [
                    'type' => 'VARCHAR',
                    'constraint' => 190,
                    'null' => true,
                    'after' => 'employment_type',
                ],
            ]);
        }

        if (!$this->db->fieldExists('end_date', 'declaration_employment_relations')) {
            $this->forge->addColumn('declaration_employment_relations', [
                'end_date' => [
                    'type' => 'DATE',
                    'null' => true,
                    'after' => 'start_date',
                ],
            ]);
        }
    }
}