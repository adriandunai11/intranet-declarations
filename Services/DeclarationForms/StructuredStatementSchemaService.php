<?php

namespace App\Modules\Declarations\Services\DeclarationForms;

class StructuredStatementSchemaService
{
    public function supports(string $templateCode): bool
    {
        return isset($this->schemas()[$templateCode]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schemaFor(string $templateCode): array
    {
        return $this->schemas()[$templateCode] ?? [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function schemas(): array
    {
        return [
            'child_extra_leave_statement' => [
                'eyebrow' => 'Munkaügy',
                'title' => 'Gyermek után járó pótszabadság',
                'intro' => 'A gyermek után járó pótszabadság igényléséhez adja meg, kéri-e a pótszabadság figyelembevételét.',
                'helper_title' => 'Igen válasz esetén',
                'helper_items' => [
                    'legalább egy gyermek adata szükséges',
                    'a gyermek neve és születési dátuma kötelező',
                    'a megjegyzésben rögzíthető minden speciális körülmény',
                ],
                'sections' => [
                    [
                        'title' => 'Igénylés',
                        'note' => 'A pótszabadságot csak kifejezett igénylés esetén vesszük figyelembe.',
                        'fields' => [
                            $this->select('claim_extra_leave', 'Kéri a gyermek után járó pótszabadság figyelembevételét?', [
                                'yes' => 'Igen, kérem',
                                'no' => 'Nem kérem',
                            ], true),
                            $this->visibleWhen(
                                $this->textarea('extra_leave_note', 'Megjegyzés', false, 'Például felváltva gondozás, fogyatékosság, évközi változás.'),
                                'claim_extra_leave',
                                'yes'
                            ),
                        ],
                    ],
                ],
                'repeaters' => [
                    $this->repeater(
                        'children',
                        'Gyermekek adatai',
                        'Gyermek hozzáadása',
                        [
                            $this->text('child_name', 'Gyermek neve', true),
                            $this->date('birth_date', 'Születési dátum', true),
                            $this->text('tax_number', 'Adóazonosító jel', false, 'tax_number', 'Ha már ismert, 10 számjeggyel adja meg.'),
                            $this->select('custody_type', 'Jogosultság alapja', [
                                'own_household' => 'Saját háztartásban nevelt gyermek',
                                'shared_custody' => 'Felváltva gondozott gyermek',
                                'disabled' => 'Fogyatékossággal élő gyermek',
                                'other' => 'Egyéb jogosultsági ok',
                            ], true),
                        ],
                        'Igen válasz esetén legalább egy gyermeket adjon meg.',
                        0,
                        12,
                        ['field' => 'claim_extra_leave', 'value' => 'yes'],
                        ['field' => 'claim_extra_leave', 'value' => 'yes', 'min' => 1]
                    ),
                ],
            ],
        ];
    }

    /**
     * @param array<string, string> $options
     * @return array<string, mixed>
     */
    private function select(string $key, string $label, array $options, bool $required = false, string $help = ''): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'select',
            'required' => $required,
            'options' => $options,
            'help' => $help,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function text(string $key, string $label, bool $required = false, string $validation = '', string $help = ''): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'text',
            'required' => $required,
            'validation' => $validation,
            'help' => $help,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function textarea(string $key, string $label, bool $required = false, string $help = ''): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'textarea',
            'required' => $required,
            'help' => $help,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function date(string $key, string $label, bool $required = false, string $help = ''): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'date',
            'required' => $required,
            'help' => $help,
        ];
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @param array<string, mixed>|null $visibleWhen
     * @param array<string, mixed>|null $requiredWhen
     * @return array<string, mixed>
     */
    private function repeater(
        string $key,
        string $title,
        string $addLabel,
        array $columns,
        string $note = '',
        int $min = 0,
        int $max = 20,
        ?array $visibleWhen = null,
        ?array $requiredWhen = null
    ): array {
        $repeater = [
            'key' => $key,
            'title' => $title,
            'note' => $note,
            'min' => $min,
            'max' => $max,
            'add_label' => $addLabel,
            'columns' => $columns,
        ];

        if ($visibleWhen !== null) {
            $repeater['visible_when'] = $visibleWhen;
        }

        if ($requiredWhen !== null) {
            $repeater['required_when'] = $requiredWhen;
        }

        return $repeater;
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    private function visibleWhen(array $field, string $fieldKey, string $value): array
    {
        $field['visible_when'] = [
            'field' => $fieldKey,
            'value' => $value,
        ];

        return $field;
    }

}
