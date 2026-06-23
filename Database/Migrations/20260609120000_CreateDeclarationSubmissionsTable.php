<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDeclarationSubmissionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'packet_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'packet_item_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'template_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'person_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'employment_relation_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'default' => 'submitted',
            ],
            'data_json' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'submitted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'accepted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'rejected_at' => [
                'type' => 'DATETIME',
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
        $this->forge->addKey('packet_id', false, false, 'declaration_submissions_packet_id');
        $this->forge->addKey('packet_item_id', false, false, 'declaration_submissions_packet_item_id');
        $this->forge->addKey('template_id', false, false, 'declaration_submissions_template_id');
        $this->forge->addKey('person_id', false, false, 'declaration_submissions_person_id');
        $this->forge->addKey('employment_relation_id', false, false, 'declaration_submissions_relation_id');
        $this->forge->addUniqueKey('packet_item_id', 'declaration_submissions_packet_item_unique');

        $this->forge->createTable('declaration_submissions', true);
    }

    public function down()
    {
        $this->forge->dropTable('declaration_submissions', true);
    }
}