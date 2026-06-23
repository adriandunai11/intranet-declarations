<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLocationIdToDeclarationEmploymentRelations extends Migration
{
    public function up()
    {
        $fields = [
            'location_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'location',
            ],
        ];

        $this->forge->addColumn('declaration_employment_relations', $fields);
        $this->forge->addKey('location_id', false, false, 'declaration_employment_relations_location_id');
    }

    public function down()
    {
        $this->forge->dropColumn('declaration_employment_relations', 'location_id');
    }
}