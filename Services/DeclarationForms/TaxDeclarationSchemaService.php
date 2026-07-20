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
                'title' => 'Családi kedvezmény nyilatkozat',
                'intro' => 'Az alap személyes adatokat a rendszer automatikusan tölti. Itt csak a kedvezményhez szükséges döntéseket és az eltartottakat kell megadni.',
                'sections' => [
                    [
                        'title' => 'Igénylés módja',
                        'note' => 'Válassza ki, hogyan kéri a családi kedvezmény érvényesítését.',
                        'fields' => [
                            $this->select('claim_scope', 'Érvényesítés módja', [
                                'alone' => 'Egyedül érvényesítem',
                                'shared' => 'Jogosult házastárssal vagy élettárssal közösen',
                            ], true),
                            $this->select('calculation_mode', 'Kedvezmény kitöltése', [
                                'amount' => 'Havi forintösszeget adok meg',
                                'dependents' => 'Kedvezményezett eltartottak száma alapján kérem',
                            ], true),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->number('monthly_amount', 'Havi összeg forintban', false, 'Csak akkor töltse, ha konkrét havi összeget szeretne megadni.'),
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
                            $this->checkbox('foreign_discount_taken', 'Külföldi jövedelem után azonos vagy hasonló kedvezményt veszek igénybe'),
                            $this->checkbox('skip_contribution_discount', 'Nem kérem a családi járulékkedvezmény havi érvényesítését'),
                        ],
                    ],
                    $this->spouseSection(false),
                ],
                'repeaters' => [
                    $this->dependentRepeater(1, 12, 'Eltartottak adatai', 'Ha több gyermek van, adjon hozzá új sort. A nyilatkozatba az összes sor mentésre kerül.'),
                ],
            ],
            'first_marriage_discount' => [
                'eyebrow' => 'Első házasok',
                'title' => 'Első házasok kedvezménye',
                'intro' => 'A saját név és adóazonosító automatikusan kerül a nyilatkozatra. Itt a házastárs és az igénylés adatai szükségesek.',
                'sections' => [
                    [
                        'title' => 'Házasság és igénylés',
                        'note' => 'A kedvezményhez a házastárs adatai és a házasságkötés időpontja szükséges.',
                        'fields' => [
                            $this->date('marriage_date', 'Házasságkötés dátuma', true),
                            $this->select('claim_scope', 'Érvényesítés módja', [
                                'alone' => 'Egyedül kérem',
                                'shared' => 'Házastárssal megosztva kérem',
                            ], true),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->number('monthly_amount', 'Havi megosztott összeg forintban', false),
                                    'claim_scope',
                                    'shared'
                                ),
                                'claim_scope',
                                'shared'
                            ),
                        ],
                    ],
                    $this->spouseSection(true),
                ],
                'repeaters' => [],
            ],
            'personal_discount' => [
                'eyebrow' => 'Személyi kedvezmény',
                'title' => 'Személyi kedvezmény nyilatkozat',
                'intro' => 'A kedvezményhez a jogosultság időszakát és szükség esetén a havi összeget kell megadni.',
                'sections' => [
                    [
                        'title' => 'Jogosultság',
                        'note' => 'A munkáltató a megadott időszak alapján veszi figyelembe a kedvezményt.',
                        'fields' => [
                            $this->date('eligibility_start', 'Jogosultság kezdete', true),
                            $this->date('eligibility_end', 'Jogosultság vége', false),
                            $this->number('monthly_amount', 'Havi kedvezmény összege', false),
                            $this->textarea('eligibility_note', 'Megjegyzés vagy igazolás adatai', false),
                        ],
                    ],
                ],
                'repeaters' => [],
            ],
            'under_25_tax_discount_waiver' => [
                'eyebrow' => '25 év alatti kedvezmény',
                'title' => '25 év alatti fiatalok kedvezményének mellőzése',
                'intro' => 'Ezt akkor töltse ki, ha a kedvezményt nem vagy csak részben szeretné igénybe venni.',
                'sections' => [
                    [
                        'title' => 'Mellőzés módja',
                        'note' => 'Teljes mellőzésnél nem kell összeget megadni. Részleges mellőzésnél adja meg a havi összeghatárt.',
                        'fields' => [
                            $this->select('waiver_scope', 'Mit kér?', [
                                'full' => 'A kedvezmény teljes mellőzését kérem',
                                'partial' => 'Csak egy megadott összeg felett kérem a mellőzést',
                            ], true),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->number('monthly_limit', 'Havi összeghatár forintban', false),
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
                'eyebrow' => '30 év alatti anyák',
                'title' => '30 év alatti anyák kedvezménye',
                'intro' => 'A jogosultság alapját adó gyermek adatait és az érvényesítés módját kell megadni.',
                'sections' => [
                    [
                        'title' => 'Igénylés',
                        'note' => 'A kedvezmény érvényesítéséhez adja meg a jogosultság kezdetét.',
                        'fields' => [
                            $this->date('eligibility_start', 'Jogosultság kezdete', true),
                            $this->date('eligibility_end', 'Jogosultság vége', false),
                            $this->checkbox('modified_statement', 'Módosító nyilatkozat'),
                        ],
                    ],
                ],
                'repeaters' => [
                    $this->childRepeater(1, 6, 'Jogosultságot megalapozó gyermek adatai'),
                ],
            ],
            'mothers_of_four_discount' => [
                'eyebrow' => 'Anyák kedvezménye',
                'title' => 'Két, három, illetve négy vagy több gyermeket nevelő anyák kedvezménye',
                'intro' => 'Adja meg azokat a gyermekeket, akik alapján a kedvezményre jogosult. Négy vagy több gyermek esetén további sorokat lehet hozzáadni.',
                'sections' => [
                    [
                        'title' => 'Igénylés',
                        'note' => 'A kedvezmény érvényesítéséhez legalább két gyermeket rögzíteni kell.',
                        'fields' => [
                            $this->date('eligibility_start', 'Jogosultság kezdete', true),
                            $this->date('eligibility_end', 'Jogosultság vége', false),
                            $this->checkbox('modified_statement', 'Módosító nyilatkozat'),
                        ],
                    ],
                ],
                'repeaters' => [
                    $this->childRepeater(2, 12, 'Gyermekek adatai'),
                ],
            ],
            'combined_family_mothers_discount' => [
                'eyebrow' => 'Összevont adónyilatkozat',
                'title' => 'Családi és anyák kedvezménye',
                'intro' => 'Az összevont nyilatkozatban a családi kedvezmény és az anyák kedvezménye egy űrlapon adható meg.',
                'sections' => [
                    [
                        'title' => 'Igénylés módja',
                        'note' => 'A kitöltött adatokból az online nyilatkozati összesítő automatikusan készül.',
                        'fields' => [
                            $this->select('claim_scope', 'Családi kedvezmény érvényesítése', [
                                'alone' => 'Egyedül érvényesítem',
                                'shared' => 'Jogosult házastárssal vagy élettárssal közösen',
                            ], true),
                            $this->select('calculation_mode', 'Családi kedvezmény kitöltése', [
                                'amount' => 'Havi forintösszeget adok meg',
                                'dependents' => 'Kedvezményezett eltartottak száma alapján kérem',
                            ], true),
                            $this->visibleWhen(
                                $this->requiredWhen(
                                    $this->number('monthly_amount', 'Havi családi kedvezmény forintban', false),
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
                            $this->date('mother_discount_start', 'Anyák kedvezményének kezdete', false),
                            $this->checkbox('skip_contribution_discount', 'Nem kérem a családi járulékkedvezmény havi érvényesítését'),
                        ],
                    ],
                    $this->spouseSection(false),
                ],
                'repeaters' => [
                    $this->dependentRepeater(1, 12, 'Gyermekek és eltartottak adatai', 'Négy vagy több gyermek esetén adjon hozzá további sort.'),
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
    private function number(string $key, string $label, bool $required = false, string $help = ''): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'number',
            'required' => $required,
            'help' => $help,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function date(string $key, string $label, bool $required = false): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'date',
            'required' => $required,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkbox(string $key, string $label): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'checkbox',
            'required' => false,
        ];
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

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    private function requiredWhen(array $field, string $fieldKey, string $value): array
    {
        $field['required_when'] = [
            'field' => $fieldKey,
            'value' => $value,
        ];

        return $field;
    }

    /**
     * @return array<string, mixed>
     */
    private function textarea(string $key, string $label, bool $required = false): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'textarea',
            'required' => $required,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function spouseSection(bool $required): array
    {
        $section = [
            'title' => 'Másik jogosult adatai',
            'note' => $required
                ? 'Az első házasok kedvezményéhez a házastárs adatai kötelezőek.'
                : 'Csak akkor töltse, ha a kedvezményt másik jogosulttal közösen érvényesíti.',
            'fields' => [
                $this->spouseField($this->text('spouse_name', 'Házastárs vagy élettárs neve', $required), $required, true),
                $this->spouseField($this->text('spouse_tax_number', 'Házastárs vagy élettárs adóazonosító jele', $required, 'tax_number', '10 számjegy.'), $required, true),
                $this->spouseField($this->text('spouse_employer_name', 'Másik jogosult munkáltatója', false), $required, false),
                $this->spouseField($this->text('spouse_employer_tax_number', 'Másik jogosult munkáltatójának adószáma', false, 'company_tax_number', '8 jegyű törzsszám vagy teljes adószám, pl. 12345676-1-42.'), $required, false),
            ],
        ];

        if (!$required) {
            $section['visible_when'] = [
                'field' => 'claim_scope',
                'value' => 'shared',
            ];
        }

        return $section;
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    private function spouseField(array $field, bool $alwaysVisible, bool $requiredWhenShared): array
    {
        if ($alwaysVisible) {
            return $field;
        }

        if ($requiredWhenShared) {
            $field = $this->requiredWhen($field, 'claim_scope', 'shared');
        }

        return $this->visibleWhen($field, 'claim_scope', 'shared');
    }

    /**
     * @return array<string, mixed>
     */
    private function dependentRepeater(int $min, int $max, string $title, string $note = ''): array
    {
        return [
            'key' => 'dependents',
            'title' => $title,
            'note' => $note,
            'min' => $min,
            'max' => $max,
            'add_label' => 'Eltartott hozzáadása',
            'columns' => [
                $this->text('tax_number', 'Adóazonosító jel vagy magzat', true, 'tax_number_or_fetus', 'Magzat esetén írja be: magzat. Egyébként 10 számjegy.'),
                $this->text('name', 'Név', true),
                $this->date('change_date', 'Változás időpontja', false),
                $this->select('em_code', 'EM* kód', $this->dependentQualityOptions(), true, 'Eltartotti minőség kódja.'),
                $this->requiredUnlessRowValues(
                    $this->select('jj_code', 'JJ** jogcím', $this->eligibilityTitleOptions(), false, 'EM 0 vagy 2 esetén nem kell kitölteni. Egyéb EM kódnál kötelező.'),
                    'em_code',
                    ['0', '2']
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
        $field['required_unless_row_values'] = [
            'field' => $fieldKey,
            'values' => $values,
        ];

        return $field;
    }

    /**
     * @return array<string, mixed>
     */
    private function childRepeater(int $min, int $max, string $title): array
    {
        return [
            'key' => 'children',
            'title' => $title,
            'note' => 'Ha több gyermek szerepel a nyilatkozaton, adjon hozzá további sort.',
            'min' => $min,
            'max' => $max,
            'add_label' => 'Gyermek hozzáadása',
            'columns' => [
                $this->text('name', 'Gyermek neve', true),
                $this->text('tax_number', 'Gyermek adóazonosító jele', true, 'tax_number'),
                $this->date('birth_date', 'Születési dátum', false),
                $this->text('birth_place', 'Születési hely', false),
            ],
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
            '0' => '0 - Kedvezménybe nem számító',
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
