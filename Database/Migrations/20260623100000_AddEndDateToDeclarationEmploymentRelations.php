<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEndDateToDeclarationEmploymentRelations extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('end_date', 'declaration_employment_relations')) {
            return;
        }

        $this->forge->addColumn('declaration_employment_relations', [
            'end_date' => [
                'type' => 'DATE',
                'null' => true,
                'after' => 'start_date',
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->fieldExists('end_date', 'declaration_employment_relations')) {
            $this->forge->dropColumn('declaration_employment_relations', 'end_date');
        }
    }
}
