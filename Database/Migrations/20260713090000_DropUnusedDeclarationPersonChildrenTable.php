<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropUnusedDeclarationPersonChildrenTable extends Migration
{
    private const TABLE = 'declaration_person_children';

    public function up(): void
    {
        $table = $this->db->escapeIdentifiers($this->db->prefixTable(self::TABLE));

        $this->db->query('DROP TABLE IF EXISTS ' . $table);
    }

    public function down(): void
    {
        if ($this->db->tableExists(self::TABLE)) {
            return;
        }

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
        $this->forge->createTable(self::TABLE, true);
    }
}
