<?php

namespace App\Modules\Declarations\Services\DeclarationForms;

class TaxDeclarationSchemaService
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
            'family_tax_discount' => [
                'eyebrow' => 'Családi kedvezmény',
                'title' => 'Családi kedvezmény',
                'intro' => 'Adja meg, hogyan kéri a családi kedvezményt, majd rögzítse azokat a gyermekeket vagy más eltartottakat, akik alapján jogosult rá.',
                'sections' => [
                    [
                        'title' => 'A kedvezmény igénylése',
                        'note' => 'A két megadási mód közül csak az egyiket kell választania.',
                        'fields' => [
                            $this->checkbox('modified_statement', 'Korábban leadott nyilatkozatot módosítok'),
                            $this->select('claim_scope', 'Hogyan érvényesíti a kedvezményt?', [
                                'alone' => 'Egyedül érvényesítem',
                                'shared' => 'Másik jogosulttal közösen érvényesítem',
                            ], true),
                            $this->select('calculation_mode', 'Hogyan adja meg a kért kedvezményt?', [
                                'amount' => 'Havi forintösszeget adok meg',
                                'dependents' => 'A kedvezményezett eltartottak számát adom meg',
                            ], true),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->number('monthly_amount', 'Kért havi összeg (Ft)', false, 'A munkáltatónál érvényesítendő havi összeget adja meg.'),
                                    'calculation_mode',
                                    'amount'
                                ),
                                'calculation_mode',
                                'amount'
                            ),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->number('beneficiary_dependents_count', 'Kedvezményezett eltartottak száma', false),
                                    'calculation_mode',
                                    'dependents'
                                ),
                                'calculation_mode',
                                'dependents'
                            ),
                            $this->checkbox(
                                'foreign_eligibility_confirmed',
                                'Kijelentem, hogy a kedvezményt Magyarországon jogosult vagyok érvényesíteni, és ugyanarra az időszakra külföldön nem veszek igénybe azonos vagy hasonló kedvezményt.',
                                true
                            ),
                            $this->checkbox(
                                'skip_contribution_discount',
                                'Nem kérem, hogy a munkáltató a fel nem használt részt családi járulékkedvezményként érvényesítse'
                            ),
                        ],
                    ],
                    $this->spouseSection(false),
                ],
                'repeaters' => [
                    $this->dependentRepeater(
                        1,
                        12,
                        'Gyermekek és eltartottak',
                        'Minden gyermekhez vagy eltartotthoz külön adatlap tartozik. Magzat esetén nem kérünk nevet és adóazonosító jelet.'
                    ),
                ],
            ],
            'first_marriage_discount' => [
                'eyebrow' => 'Első házasok kedvezménye',
                'title' => 'Első házasok kedvezménye',
                'intro' => 'A kedvezményt a házastársak közösen érvényesítik. Itt azt az összeget adja meg, amelyet ennél a munkáltatónál szeretne figyelembe venni.',
                'sections' => [
                    [
                        'title' => 'Igénylés adatai',
                        'note' => 'A kedvezmény legfeljebb 24 jogosultsági hónapra jár.',
                        'fields' => [
                            $this->requiredWhen(
                                $this->checkbox('modified_statement', 'Korábban leadott nyilatkozatot módosítok'),
                                'declaration_action',
                                'stop'
                            ),
                            $this->select('declaration_action', 'Mit szeretne tenni?', [
                                'claim' => 'Kérem a kedvezmény figyelembevételét',
                                'stop' => 'A továbbiakban nem kérem a kedvezményt',
                            ], true),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->notFuture(
                                        $this->date('marriage_date', 'Házasságkötés dátuma vagy a jogosultság kezdőnapja')
                                    ),
                                    'declaration_action',
                                    'claim'
                                ),
                                'declaration_action',
                                'claim'
                            ),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->month('claim_from_month', 'Melyik hónaptól kéri?'),
                                    'declaration_action',
                                    'claim'
                                ),
                                'declaration_action',
                                'claim'
                            ),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->number('monthly_amount', 'Ennél a munkáltatónál kért havi összeg (Ft)'),
                                    'declaration_action',
                                    'claim'
                                ),
                                'declaration_action',
                                'claim'
                            ),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->select('duration_mode', 'Meddig vegyék figyelembe?', [
                                        'tax_year' => 'Csak ebben az adóévben',
                                        'continuous' => 'Visszavonásig, legfeljebb a jogosultsági időszak végéig',
                                    ]),
                                    'declaration_action',
                                    'claim'
                                ),
                                'declaration_action',
                                'claim'
                            ),
                        ],
                    ],
                    $this->visibleWhenSection($this->spouseSection(true), 'declaration_action', 'claim'),
                ],
                'repeaters' => [],
            ],
            'personal_discount' => [
                'eyebrow' => 'Személyi kedvezmény',
                'title' => 'Személyi kedvezmény',
                'intro' => 'Először válassza ki, milyen igazolás vagy ellátás alapján jogosult. Ezután csak az ehhez szükséges adatokat kérjük.',
                'sections' => [
                    [
                        'title' => 'Nyilatkozat célja',
                        'fields' => [
                            $this->requiredWhen(
                                $this->checkbox('modified_statement', 'Korábban leadott nyilatkozatot módosítok'),
                                'declaration_action',
                                'stop'
                            ),
                            $this->select('declaration_action', 'Mit szeretne tenni?', [
                                'claim' => 'Kérem a személyi kedvezmény figyelembevételét',
                                'stop' => 'A továbbiakban nem kérem a kedvezményt',
                            ], true),
                        ],
                    ],
                    [
                        'title' => 'A jogosultság alapja',
                        'note' => 'A kiválasztás után csak a szükséges mezők jelennek meg.',
                        'visible_when' => ['field' => 'declaration_action', 'value' => 'claim'],
                        'fields' => [
                            $this->requiredWhen(
                                $this->select('eligibility_basis', 'Mi alapján jogosult a kedvezményre?', [
                                    'medical_certificate' => 'Orvosi igazolás alapján',
                                    'disability_annuity' => 'Rokkantsági járadékban részesülök',
                                    'disability_support' => 'Fogyatékossági támogatásban részesülök',
                                ]),
                                'declaration_action',
                                'claim'
                            ),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->notFuture(
                                        $this->date('eligibility_start', 'Az állapot kezdőnapja')
                                    ),
                                    'eligibility_basis',
                                    'medical_certificate'
                                ),
                                'eligibility_basis',
                                'medical_certificate'
                            ),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->select('condition_duration', 'Meddig áll fenn az állapot?', [
                                        'permanent' => 'Az állapot végleges',
                                        'until_date' => 'Az állapotnak van befejező dátuma',
                                    ]),
                                    'eligibility_basis',
                                    'medical_certificate'
                                ),
                                'eligibility_basis',
                                'medical_certificate'
                            ),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->date('eligibility_end', 'Az állapot utolsó napja'),
                                    'condition_duration',
                                    'until_date'
                                ),
                                'condition_duration',
                                'until_date'
                            ),
                            $this->visibleWhenValues(
                                $this->requiredWhenValues(
                                    $this->text('decision_number', 'Határozat száma'),
                                    'eligibility_basis',
                                    ['disability_annuity', 'disability_support']
                                ),
                                'eligibility_basis',
                                ['disability_annuity', 'disability_support']
                            ),
                            $this->checkbox('continuous_statement', 'A nyilatkozatot visszavonásig kérem figyelembe venni'),
                            $this->requiredWhen(
                                $this->checkbox(
                                    'foreign_eligibility_confirmed',
                                    'Kijelentem, hogy a kedvezményt Magyarországon jogosult vagyok érvényesíteni, és külföldön nem veszek igénybe azonos vagy hasonló kedvezményt.'
                                ),
                                'declaration_action',
                                'claim'
                            ),
                        ],
                    ],
                ],
                'repeaters' => [],
            ],
            'under_25_tax_discount_waiver' => [
                'eyebrow' => '25 év alattiak kedvezménye',
                'title' => 'A 25 év alattiak kedvezményének mellőzése',
                'intro' => 'Ezt csak akkor töltse ki, ha az automatikusan járó kedvezményt egyáltalán nem, vagy csak részben szeretné igénybe venni.',
                'sections' => [
                    [
                        'title' => 'Mit kér a munkáltatótól?',
                        'note' => 'Teljes mellőzésnél nincs szükség összegre.',
                        'fields' => [
                            $this->select('waiver_scope', 'A mellőzés módja', [
                                'full' => 'Egyáltalán ne vegyék figyelembe a kedvezményt',
                                'partial' => 'Csak egy megadott havi összeg felett ne vegyék figyelembe',
                            ], true),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->number('monthly_limit', 'Havi összeghatár (Ft)', false, 'Az ezt meghaladó jövedelemrészre nem kéri a kedvezményt.'),
                                    'waiver_scope',
                                    'partial'
                                ),
                                'waiver_scope',
                                'partial'
                            ),
                        ],
                    ],
                ],
                'repeaters' => [],
            ],
            'under_30_mothers_discount' => [
                'eyebrow' => '30 év alatti anyák kedvezménye',
                'title' => '30 év alatti anyák kedvezménye',
                'intro' => 'A jogosultság alapja lehet megszületett vagy örökbefogadott gyermek, illetve magzat. A választás után csak az ahhoz szükséges adatokat kérjük.',
                'sections' => [
                    [
                        'title' => 'Nyilatkozat célja',
                        'fields' => [
                            $this->requiredWhen(
                                $this->checkbox('modified_statement', 'Korábban leadott nyilatkozatot módosítok'),
                                'declaration_action',
                                'stop'
                            ),
                            $this->select('declaration_action', 'Mit szeretne tenni?', [
                                'claim' => 'Kérem a kedvezmény figyelembevételét',
                                'stop' => 'Egy megadott hónaptól nem kérem a kedvezményt',
                            ], true),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->month('stop_from_month', 'Melyik hónaptól nem kéri?'),
                                    'declaration_action',
                                    'stop'
                                ),
                                'declaration_action',
                                'stop'
                            ),
                        ],
                    ],
                    [
                        'title' => 'A jogosultság alapja',
                        'visible_when' => ['field' => 'declaration_action', 'value' => 'claim'],
                        'fields' => [
                            $this->requiredWhen(
                                $this->select('eligibility_basis', 'Ki után jogosult a kedvezményre?', [
                                    'child' => 'Megszületett vagy örökbefogadott gyermek után',
                                    'fetus' => 'Magzat után',
                                ]),
                                'declaration_action',
                                'claim'
                            ),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->text('child_name', 'Gyermek neve'),
                                    'eligibility_basis',
                                    'child'
                                ),
                                'eligibility_basis',
                                'child'
                            ),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->text('child_tax_number', 'Gyermek adóazonosító jele', false, 'tax_number', '10 számjegy'),
                                    'eligibility_basis',
                                    'child'
                                ),
                                'eligibility_basis',
                                'child'
                            ),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->month('fetus_91st_day_month', 'A várandósság 91. napjának hónapja'),
                                    'eligibility_basis',
                                    'fetus'
                                ),
                                'eligibility_basis',
                                'fetus'
                            ),
                        ],
                    ],
                ],
                'repeaters' => [],
            ],
            'mothers_of_four_discount' => [
                'eyebrow' => 'Többgyermekes anyák kedvezménye',
                'title' => 'Két, három, illetve négy vagy több gyermeket nevelő anyák kedvezménye',
                'intro' => 'Válassza ki, melyik kedvezményre jogosult. A gyermekeket külön adatlapokon lehet megadni.',
                'sections' => [
                    [
                        'title' => 'Nyilatkozat célja',
                        'fields' => [
                            $this->requiredWhen(
                                $this->checkbox('modified_statement', 'Korábban leadott nyilatkozatot módosítok'),
                                'declaration_action',
                                'stop'
                            ),
                            $this->select('declaration_action', 'Mit szeretne tenni?', [
                                'claim' => 'Kérem a kedvezmény figyelembevételét',
                                'stop' => 'Egy megadott hónaptól nem kérem a kedvezményt',
                            ], true),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->select('mother_discount_type', 'Melyik kedvezményre jogosult?', $this->motherDiscountOptions()),
                                    'declaration_action',
                                    'claim'
                                ),
                                'declaration_action',
                                'claim'
                            ),
                            $this->visibleWhen(
                                $this->checkbox('continuous_statement', 'A nyilatkozatot visszavonásig kérem figyelembe venni'),
                                'declaration_action',
                                'claim'
                            ),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->month('stop_from_month', 'Melyik hónaptól nem kéri?'),
                                    'declaration_action',
                                    'stop'
                                ),
                                'declaration_action',
                                'stop'
                            ),
                        ],
                    ],
                ],
                'repeaters' => [
                    $this->motherChildrenRepeater('Gyermekek adatai', ['field' => 'declaration_action', 'value' => 'claim']),
                ],
            ],
            'combined_family_mothers_discount' => [
                'eyebrow' => 'Összevont adónyilatkozat',
                'title' => 'Családi és többgyermekes anyák kedvezménye',
                'intro' => 'Ezen az űrlapon együtt kérheti a családi kedvezményt és a két, három, illetve négy vagy több gyermeket nevelő anyák kedvezményét.',
                'sections' => [
                    [
                        'title' => 'Anyakedvezmény',
                        'fields' => [
                            $this->select('mother_discount_type', 'Melyik anyakedvezményre jogosult?', $this->motherDiscountOptions(), true),
                            $this->checkbox(
                                'foreign_eligibility_confirmed',
                                'Kijelentem, hogy a kedvezményeket Magyarországon jogosult vagyok érvényesíteni, és ugyanarra az időszakra külföldön nem veszek igénybe azonos vagy hasonló kedvezményt.',
                                true
                            ),
                        ],
                    ],
                    [
                        'title' => 'Családi kedvezmény',
                        'fields' => [
                            $this->select('claim_scope', 'Hogyan érvényesíti a családi kedvezményt?', [
                                'alone' => 'Egyedül érvényesítem',
                                'shared' => 'Másik jogosulttal közösen érvényesítem',
                            ], true),
                            $this->select('calculation_mode', 'Hogyan adja meg a kért kedvezményt?', [
                                'amount' => 'Havi forintösszeget adok meg',
                                'dependents' => 'A kedvezményezett eltartottak számát adom meg',
                            ], true),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->number('monthly_amount', 'Kért havi összeg (Ft)'),
                                    'calculation_mode',
                                    'amount'
                                ),
                                'calculation_mode',
                                'amount'
                            ),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->number('beneficiary_dependents_count', 'Kedvezményezett eltartottak száma'),
                                    'calculation_mode',
                                    'dependents'
                                ),
                                'calculation_mode',
                                'dependents'
                            ),
                            $this->checkbox(
                                'skip_contribution_discount',
                                'Nem kérem, hogy a munkáltató a fel nem használt részt családi járulékkedvezményként érvényesítse'
                            ),
                        ],
                    ],
                    $this->spouseSection(false),
                ],
                'repeaters' => [
                    $this->combinedDependentRepeater(),
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
        return compact('key', 'label', 'options', 'required', 'help') + ['type' => 'select'];
    }

    /**
     * @return array<string, mixed>
     */
    private function text(string $key, string $label, bool $required = false, string $validation = '', string $help = ''): array
    {
        return compact('key', 'label', 'required', 'validation', 'help') + ['type' => 'text'];
    }

    /**
     * @return array<string, mixed>
     */
    private function number(string $key, string $label, bool $required = false, string $help = ''): array
    {
        return compact('key', 'label', 'required', 'help') + [
            'type' => 'number',
            'min_value' => 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function date(string $key, string $label, bool $required = false, string $help = ''): array
    {
        return compact('key', 'label', 'required', 'help') + ['type' => 'date'];
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    private function notFuture(array $field): array
    {
        $field['not_future'] = true;

        return $field;
    }

    /**
     * @return array<string, mixed>
     */
    private function month(string $key, string $label, bool $required = false, string $help = ''): array
    {
        return compact('key', 'label', 'required', 'help') + ['type' => 'month'];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkbox(string $key, string $label, bool $required = false, string $help = ''): array
    {
        return compact('key', 'label', 'required', 'help') + ['type' => 'checkbox'];
    }

    /**
     * @return array<string, mixed>
     */
    private function textarea(string $key, string $label, bool $required = false, string $help = ''): array
    {
        return compact('key', 'label', 'required', 'help') + ['type' => 'textarea'];
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    private function visibleWhen(array $field, string $fieldKey, string $value): array
    {
        $field['visible_when'] = ['field' => $fieldKey, 'value' => $value];

        return $field;
    }

    /**
     * @param array<string, mixed> $field
     * @param list<string> $values
     * @return array<string, mixed>
     */
    private function visibleWhenValues(array $field, string $fieldKey, array $values): array
    {
        $field['visible_when'] = ['field' => $fieldKey, 'values' => $values];

        return $field;
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    private function requiredWhen(array $field, string $fieldKey, string $value): array
    {
        $field['required_when'] = ['field' => $fieldKey, 'value' => $value];

        return $field;
    }

    /**
     * @param array<string, mixed> $field
     * @param list<string> $values
     * @return array<string, mixed>
     */
    private function requiredWhenValues(array $field, string $fieldKey, array $values): array
    {
        $field['required_when'] = ['field' => $fieldKey, 'values' => $values];

        return $field;
    }

    /**
     * @param array<string, mixed> $section
     * @return array<string, mixed>
     */
    private function visibleWhenSection(array $section, string $fieldKey, string $value): array
    {
        $section['visible_when'] = ['field' => $fieldKey, 'value' => $value];

        return $section;
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    private function visibleWhenRow(array $field, string $fieldKey, string $value): array
    {
        $field['visible_when_row'] = ['field' => $fieldKey, 'value' => $value];

        return $field;
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    private function requiredWhenRow(array $field, string $fieldKey, string $value): array
    {
        $field['required_when_row'] = ['field' => $fieldKey, 'value' => $value];

        return $field;
    }

    /**
     * @return array<string, mixed>
     */
    private function spouseSection(bool $required): array
    {
        $section = [
            'title' => $required ? 'Házastárs adatai' : 'Másik jogosult adatai',
            'note' => $required
                ? 'A házastárs neve és adóazonosító jele kötelező. A munkáltatói adatok akkor szükségesek, ha rendelkezésre állnak.'
                : 'Ezt a részt csak közös érvényesítés esetén kell kitölteni.',
            'fields' => [
                $this->text('spouse_name', $required ? 'Házastárs neve' : 'Házastárs vagy élettárs neve', true),
                $this->text('spouse_tax_number', $required ? 'Házastárs adóazonosító jele' : 'Házastárs vagy élettárs adóazonosító jele', true, 'tax_number', '10 számjegy'),
                $this->text('spouse_employer_name', 'Másik jogosult munkáltatójának neve'),
                $this->text('spouse_employer_tax_number', 'Másik jogosult munkáltatójának adószáma', false, 'company_tax_number', '8 jegyű törzsszám vagy teljes adószám'),
            ],
        ];

        if (!$required) {
            $section['visible_when'] = ['field' => 'claim_scope', 'value' => 'shared'];
            $section['fields'][0]['required_when'] = ['field' => 'claim_scope', 'value' => 'shared'];
            $section['fields'][1]['required_when'] = ['field' => 'claim_scope', 'value' => 'shared'];
            $section['fields'][0]['required'] = false;
            $section['fields'][1]['required'] = false;
        }

        return $section;
    }

    /**
     * @return array<string, mixed>
     */
    private function dependentRepeater(int $min, int $max, string $title, string $note): array
    {
        return [
            'key' => 'dependents',
            'title' => $title,
            'note' => $note,
            'min' => $min,
            'max' => $max,
            'add_label' => 'Új gyermek vagy eltartott',
            'row_label' => 'Gyermek vagy eltartott',
            'columns' => $this->dependentColumns(false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function combinedDependentRepeater(): array
    {
        return [
            'key' => 'dependents',
            'title' => 'Gyermekek és eltartottak',
            'note' => 'A kiválasztott anyakedvezménytől függően legalább 2, 3 vagy 4 adatlap szükséges.',
            'min' => 0,
            'max' => 12,
            'min_by_field' => [
                'field' => 'mother_discount_type',
                'values' => ['two' => 2, 'three' => 3, 'four_plus' => 4],
            ],
            'qualified_min_by_field' => [
                'field' => 'mother_discount_type',
                'values' => ['two' => 2, 'three' => 3, 'four_plus' => 4],
                'row_field' => 'mother_child_type',
                'accepted_values' => ['biological', 'adopted'],
            ],
            'qualified_min_message' => 'A kiválasztott anyakedvezményhez legalább %d vér szerinti vagy örökbefogadott gyermek adata szükséges. A magzat és a „nem az anyakedvezmény alapja” választás ebbe nem számít bele.',
            'add_label' => 'Új gyermek vagy eltartott',
            'row_label' => 'Gyermek vagy eltartott',
            'columns' => $this->dependentColumns(true),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dependentColumns(bool $withMotherChildType): array
    {
        $columns = [
            $this->select('dependent_type', 'Kit rögzít?', [
                'person' => 'Megszületett gyermek vagy más eltartott',
                'fetus' => 'Magzat',
            ], true),
            $this->visibleWhenRow(
                $this->requiredWhenRow($this->text('name', 'Név'), 'dependent_type', 'person'),
                'dependent_type',
                'person'
            ),
        ];

        if ($withMotherChildType) {
            $columns[] = $this->visibleWhenRow(
                $this->requiredWhenRow(
                    $this->select('identification_method', 'Hogyan azonosítja?', [
                        'tax_number' => 'Adóazonosító jellel',
                        'birth_data' => 'Születési adatokkal',
                    ]),
                    'dependent_type',
                    'person'
                ),
                'dependent_type',
                'person'
            );
            $columns[] = $this->visibleWhenRow(
                $this->requiredWhenRow($this->text('tax_number', 'Adóazonosító jel', false, 'tax_number', '10 számjegy'), 'identification_method', 'tax_number'),
                'identification_method',
                'tax_number'
            );
            $columns[] = $this->visibleWhenRow(
                $this->requiredWhenRow($this->text('birth_place', 'Születési hely'), 'identification_method', 'birth_data'),
                'identification_method',
                'birth_data'
            );
            $columns[] = $this->visibleWhenRow(
                $this->requiredWhenRow(
                    $this->notFuture($this->date('birth_date', 'Születési dátum')),
                    'identification_method',
                    'birth_data'
                ),
                'identification_method',
                'birth_data'
            );
        } else {
            $columns[] = $this->visibleWhenRow(
                $this->requiredWhenRow($this->text('tax_number', 'Adóazonosító jel', false, 'tax_number', '10 számjegy'), 'dependent_type', 'person'),
                'dependent_type',
                'person'
            );
        }

        $columns[] = $this->visibleWhenRow(
            $this->requiredWhenRow($this->select('em_code', 'Eltartotti minőség (EM)', $this->dependentQualityOptions()), 'dependent_type', 'person'),
            'dependent_type',
            'person'
        );
        $columns[] = $this->visibleWhenRow(
            $this->requiredUnlessRowValues(
                $this->select('jj_code', 'Jogosultság jogcíme (JJ)', $this->eligibilityTitleOptions(), false, 'EM 0 vagy 2 esetén nem kell kitölteni.'),
                'em_code',
                ['0', '2']
            ),
            'dependent_type',
            'person'
        );

        if ($withMotherChildType) {
            $columns[] = $this->visibleWhenRow(
                $this->select('mother_child_type', 'Anyakedvezmény alapja', [
                    'biological' => 'Vér szerinti gyermek',
                    'adopted' => 'Örökbefogadott gyermek',
                    'not_applicable' => 'Nem az anyakedvezmény alapja',
                ], true),
                'dependent_type',
                'person'
            );
        } else {
            $columns[] = $this->date('change_date', 'Változás időpontja', false, 'Csak akkor adja meg, ha a jogosultság év közben változik.');
        }

        return $columns;
    }

    /**
     * @return array<string, mixed>
     */
    private function motherChildrenRepeater(string $title, array $visibleWhen): array
    {
        return [
            'key' => 'children',
            'title' => $title,
            'note' => 'Ha a gyermeknek nincs adóazonosító jele, válassza a születési adatokkal történő azonosítást.',
            'min' => 0,
            'max' => 12,
            'visible_when' => $visibleWhen,
            'min_by_field' => [
                'field' => 'mother_discount_type',
                'values' => ['two' => 2, 'three' => 3, 'four_plus' => 4],
            ],
            'add_label' => 'Új gyermek',
            'row_label' => 'Gyermek',
            'columns' => [
                $this->text('name', 'Gyermek neve', true),
                $this->select('identification_method', 'Hogyan azonosítja a gyermeket?', [
                    'tax_number' => 'Adóazonosító jellel',
                    'birth_data' => 'Születési adatokkal',
                ], true),
                $this->visibleWhenRow(
                    $this->requiredWhenRow($this->text('tax_number', 'Adóazonosító jel', false, 'tax_number', '10 számjegy'), 'identification_method', 'tax_number'),
                    'identification_method',
                    'tax_number'
                ),
                $this->visibleWhenRow(
                    $this->requiredWhenRow(
                        $this->notFuture($this->date('birth_date', 'Születési dátum')),
                        'identification_method',
                        'birth_data'
                    ),
                    'identification_method',
                    'birth_data'
                ),
                $this->visibleWhenRow(
                    $this->requiredWhenRow($this->text('birth_place', 'Születési hely'), 'identification_method', 'birth_data'),
                    'identification_method',
                    'birth_data'
                ),
                $this->visibleWhenRow(
                    $this->requiredWhenRow($this->text('mother_name', 'Gyermek anyjának születési neve'), 'identification_method', 'birth_data'),
                    'identification_method',
                    'birth_data'
                ),
            ],
        ];
    }

    /**
     * @param list<string> $values
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    private function requiredUnlessRowValues(array $field, string $fieldKey, array $values): array
    {
        $field['required_unless_row_values'] = ['field' => $fieldKey, 'values' => $values];

        return $field;
    }

    /**
     * @return array<string, string>
     */
    private function motherDiscountOptions(): array
    {
        return [
            'two' => 'Két gyermeket nevelő anyák kedvezménye',
            'three' => 'Három gyermeket nevelő anyák kedvezménye',
            'four_plus' => 'Négy vagy több gyermeket nevelő anyák kedvezménye',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function dependentQualityOptions(): array
    {
        return [
            '1' => '1 - Kedvezményezett eltartott',
            '2' => '2 - Eltartott',
            '3' => '3 - Felváltva gondozott gyermek',
            '4' => '4 - Tartósan beteg vagy súlyosan fogyatékos személy',
            '5' => '5 - Felváltva gondozott tartósan beteg vagy súlyosan fogyatékos személy',
            '0' => '0 - A kedvezménybe nem számító személy',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function eligibilityTitleOptions(): array
    {
        return [
            'a' => 'a - Családi pótlékra jogosult vagy vele közös háztartásban élő házastárs',
            'b' => 'b - Várandós nő vagy vele közös háztartásban élő házastárs',
            'c' => 'c - Saját jogon családi pótlékra jogosult vagy vele közös háztartásban élő hozzátartozó',
            'd' => 'd - Rokkantsági járadékban részesülő vagy vele közös háztartásban élő hozzátartozó',
        ];
    }
}
