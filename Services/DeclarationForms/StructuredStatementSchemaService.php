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
                'intro' => 'Jelezze, hogy kéri-e a gyermek után járó pótszabadságot. Ha nem kéri, gyermekadatokat sem kell megadnia.',
                'helper_title' => 'Igen válasz esetén',
                'helper_items' => [
                    'legalább egy gyermek nevét és születési dátumát adja meg',
                    'különleges körülményt a megjegyzésben jelezhet',
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
                            $this->select('custody_type', 'Hogyan neveli a gyermeket?', [
                                'own_household' => 'Saját háztartásban nevelt gyermek',
                                'shared_custody' => 'Felváltva gondozott gyermek',
                                'other' => 'Más élethelyzet',
                            ], true),
                            $this->checkbox(
                                'disabled_child',
                                'A gyermek után emelt összegű családi pótlék jár',
                                false,
                                'Ezt akkor jelölje, ha a gyermek tartósan beteg vagy súlyosan fogyatékos, és emiatt emelt összegű családi pótlék jár utána.'
                            ),
                        ],
                        'Igen válasz esetén legalább egy gyermeket adjon meg.',
                        0,
                        12,
                        ['field' => 'claim_extra_leave', 'value' => 'yes'],
                        ['field' => 'claim_extra_leave', 'value' => 'yes', 'min' => 1]
                    ),
                ],
            ],
            'under_3_child_work_schedule_statement' => [
                'eyebrow' => 'Munkaügy',
                'title' => 'Nyilatkozat 3 év alatti gyermek neveléséről',
                'intro' => 'Ezzel a nyilatkozattal jelzi, hogy nevel-e 3 év alatti gyermeket, és szükséges-e emiatt külön szabályokat figyelembe venni a munkaidő beosztásánál.',
                'helper_title' => 'Mit jelent ez?',
                'helper_items' => [
                    'kisgyermeket nevelő vagy gyermekét egyedül nevelő munkavállalónál egyes beosztások korlátozottak lehetnek',
                    'ilyen lehet például a túlóra, a készenlét vagy az éjszakai munka',
                    'a megadott adatok alapján a munkaügy állapítja meg a pontos szabályokat',
                ],
                'sections' => [
                    [
                        'title' => 'Gyermek nevelésére vonatkozó nyilatkozat',
                        'note' => 'A nyilatkozat a kitöltés napján fennálló állapotot rögzíti.',
                        'fields' => [
                            $this->select('raises_child_under_3', 'Nevel 3 év alatti gyermeket?', [
                                'no' => 'Nem nevelek 3 év alatti gyermeket',
                                'yes' => 'Nevelek 3 év alatti gyermeket',
                            ], true),
                            $this->visibleWhen(
                                $this->select('child_lives_same_household', 'A gyermek Önnel közös háztartásban él?', [
                                    'yes' => 'Igen',
                                    'no' => 'Nem',
                                ], true),
                                'raises_child_under_3',
                                'yes'
                            ),
                            $this->visibleWhen(
                                $this->select('raises_child_alone', 'Egyedül neveli a gyermeket?', [
                                    'no' => 'Nem, gyermekemet nem egyedül nevelem',
                                    'yes' => 'Igen, gyermekemet egyedül nevelem',
                                ], true),
                                'raises_child_under_3',
                                'yes'
                            ),
                        ],
                    ],
                    [
                        'title' => 'Munkaidő-beosztás',
                        'note' => 'Bizonyos élethelyzetekben a munkáltató nem rendelhet el, vagy csak az Ön hozzájárulásával rendelhet el éjszakai munkát, túlórát, készenlétet vagy egyenlőtlen munkaidő-beosztást.',
                        'visible_when' => ['field' => 'raises_child_under_3', 'value' => 'yes'],
                        'fields' => [
                            $this->select('mt_113_condition_exists', 'Fennáll Önnél olyan körülmény, amely miatt külön munkaidő-beosztási szabályokat kell alkalmazni?', [
                                'yes' => 'Igen, fennáll',
                                'no' => 'Nem áll fenn',
                            ], true),
                            $this->checkbox(
                                'change_reporting_acknowledged',
                                'Tudomásul veszem, hogy a nyilatkozatot befolyásoló változást haladéktalanul, de legkésőbb a változást követő 3 munkanapon belül írásban be kell jelentenem a munkáltatónak.',
                                true
                            ),
                        ],
                    ],
                ],
                'repeaters' => [],
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
    private function checkbox(string $key, string $label, bool $required = false, string $help = ''): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'checkbox',
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
            'not_future' => true,
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
            'row_label' => $key === 'children' ? 'gyermek' : 'adatlap',
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
