<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDeclarationTemplatesTable extends Migration
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
            'code' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 190,
            ],
            'category' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
            ],
            'tax_year' => [
                'type' => 'INT',
                'constraint' => 4,
                'null' => true,
            ],
            'version' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'default' => 'v1',
            ],
            'renewal_policy' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'per_relation',
            ],
            'required_policy' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'optional',
            ],
            'company_scope' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'global',
            ],
            'class_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'sort_order' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'is_active' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
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
        $this->forge->addUniqueKey(['code', 'tax_year', 'version'], 'declaration_templates_code_year_version');
        $this->forge->addKey('category', false, false, 'declaration_templates_category');
        $this->forge->addKey('is_active', false, false, 'declaration_templates_is_active');

        $this->forge->createTable('declaration_templates', true);
    }

    public function down()
    {
        $this->forge->dropTable('declaration_templates', true);
    }
}