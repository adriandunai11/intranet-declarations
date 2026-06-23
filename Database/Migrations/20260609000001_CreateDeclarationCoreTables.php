<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDeclarationCoreTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'intranet_user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'antra_id' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'lastname' => ['type' => 'VARCHAR', 'constraint' => 100],
            'firstname' => ['type' => 'VARCHAR', 'constraint' => 100],
            'birth_name' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'mother_name' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'birth_place' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'birth_date' => ['type' => 'DATE', 'null' => true],
            'tax_number' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'taj_number' => ['type' => 'VARCHAR', 'constraint' => 9, 'null' => true],
            'email' => ['type' => 'VARCHAR', 'constraint' => 190],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'draft'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email', 'declaration_persons_email');
        $this->forge->addUniqueKey('antra_id', 'declaration_persons_antra_id');
        $this->forge->addUniqueKey('tax_number', 'declaration_persons_tax_number');
        $this->forge->addUniqueKey('taj_number', 'declaration_persons_taj_number');
        $this->forge->createTable('declaration_persons', true);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'person_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'intranet_user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'draft'],
            'employment_type' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'position' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'location' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'start_date' => ['type' => 'DATE', 'null' => true],
            'end_date' => ['type' => 'DATE', 'null' => true],
            'previous_relation_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_by_user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('person_id');
        $this->forge->addKey('company_id');
        $this->forge->addKey('intranet_user_id');
        $this->forge->addKey('previous_relation_id');
        $this->forge->createTable('declaration_employment_relations', true);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'person_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 190],
            'tax_number' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'birth_date' => ['type' => 'DATE', 'null' => true],
            'relationship_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'is_dependent' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'is_disabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'valid_from' => ['type' => 'DATE', 'null' => true],
            'valid_to' => ['type' => 'DATE', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('person_id');
        $this->forge->createTable('declaration_person_children', true);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'person_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'employment_relation_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'email' => ['type' => 'VARCHAR', 'constraint' => 190],
            'token_hash' => ['type' => 'VARCHAR', 'constraint' => 64],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'created'],
            'expires_at' => ['type' => 'DATETIME', 'null' => true],
            'sent_at' => ['type' => 'DATETIME', 'null' => true],
            'opened_at' => ['type' => 'DATETIME', 'null' => true],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('person_id');
        $this->forge->addKey('employment_relation_id');
        $this->forge->addUniqueKey('token_hash', 'declaration_invitations_token_hash');
        $this->forge->createTable('declaration_invitations', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('declaration_invitations', true);
        $this->forge->dropTable('declaration_person_children', true);
        $this->forge->dropTable('declaration_employment_relations', true);
        $this->forge->dropTable('declaration_persons', true);
    }
}
