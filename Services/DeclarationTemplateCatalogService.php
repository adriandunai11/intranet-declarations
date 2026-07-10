<?php

namespace App\Modules\Declarations\Services;

use App\Modules\Declarations\Entities\DeclarationTemplate;
use App\Modules\Declarations\Models\DeclarationTemplateModel;
use RuntimeException;

class DeclarationTemplateCatalogService
{
    protected DeclarationTemplateModel $templateModel;

    public function __construct(?DeclarationTemplateModel $templateModel = null)
    {
        $this->templateModel = $templateModel ?? new DeclarationTemplateModel();
    }

    /**
     * @return array{created:int,updated:int,skipped:int}
     */
    public function sync(): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        foreach ($this->catalog() as $row) {
            $existing = $this->templateModel->findByCode((string) $row['code'], isset($row['tax_year']) ? (int) $row['tax_year'] : null);
            $payload = $this->payload($row);

            if ($existing) {
                $before = array_intersect_key($existing->toRawArray(), $payload);

                if ($before == $payload) {
                    $result['skipped']++;
                    continue;
                }

                if (!$this->templateModel->update((int) $existing->id, $payload)) {
                    throw new RuntimeException($this->errors('A nyilatkozat frissítése sikertelen.'));
                }

                $result['updated']++;
                continue;
            }

            if (!$this->templateModel->insert($payload, true)) {
                throw new RuntimeException($this->errors('A nyilatkozat létrehozása sikertelen.'));
            }

            $result['created']++;
        }

        return $result;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function catalog(): array
    {
        return [
            $this->tax('under_30_mothers_discount', 'ANYA30', '30 év alatti anyák kedvezménye', 'Adóelőleg-nyilatkozat a 30 év alatti anyák kedvezményének érvényesítéséről', 'A nyilatkozatot azoknak a 25 évet betöltött 30 év alatti anyáknak kell benyújtaniuk, akik azt szeretnék, hogy a kifizetőjük, munkáltatójuk a 30 év alatti anyák kedvezményének (vérszerinti, örökbefogadott gyermek vagy 91. napot betöltött magzat után) figyelembevételével vonja le az adóelőleget. Adóelőleg-nyilatkozat munkáltatónak, és olyan kifizetőnek adható, aki összevonás alá eső rendszeres jövedelmet juttat (például havi vagy heti bért, munkadíjat, tiszteletdíjat, személyes közreműködés ellenértékét, egyéb juttatást, adóköteles társadalombiztosítási ellátást).', 10, ['30 év alatti anyák', 'adóelőleg', 'ANYA30']),
            $this->tax('combined_family_mothers_discount', 'ANYACSKA', 'Összevont adóelőleg-nyilatkozat anyáknak', 'Összevont adóelőleg-nyilatkozat anyáknak a családi kedvezmény (és járulékkedvezmény) és a két, három, négy vagy több gyermeket nevelő anyák kedvezményének érvényesítéséről', 'A családi kedvezményre (ideértve a családi járulékkedvezményt) és a két gyermeket nevelő anyák kedvezményére, vagy a három gyermeket nevelő anyák kedvezményére, vagy a négy vagy több gyermeket nevelő anyák kedvezményére jogosult anya ezen az adóelőleg-nyilatkozaton kérheti, hogy a munkáltató, kifizető az adóelőleg megállapításakor a családi kedvezmény adóéven belüli érvényesítése mellett a két, vagy három, vagy négy vagy több gyermeket nevelő anyák kedvezményének folytatólagos érvényesítését is vegye figyelembe.', 20, ['családi kedvezmény', 'anyák kedvezménye', 'ANYACSKA']),
            $this->tax('mothers_of_four_discount', 'ANYNTA', 'Két, három és négy vagy több gyermekes anyák', 'Adóelőleg-nyilatkozat a két gyermeket nevelő anyák, a három gyermeket nevelő anyák és a négy vagy több gyermeket nevelő anyák kedvezményének érvényesítéséről', 'A nyilatkozatot azoknak a három és négy vagy több gyermeket nevelő anyáknak kell benyújtaniuk, akik azt szeretnék, hogy a kifizetőjük, munkáltatójuk – a törvényben meghatározott jövedelmeik után – a három gyermeket nevelő anyák vagy a négy vagy több gyermeket nevelő anyák kedvezményének figyelembevételével vonja le az adóelőleget. Adóelőleg-nyilatkozat munkáltatónak, és olyan kifizetőnek adható, aki összevonás alá eső jövedelmet juttat.', 30, ['két gyermekes anya', 'három gyermekes anya', 'négy vagy több gyermekes anya', 'ANYNTA']),
            $this->tax('family_tax_discount', 'ANYCSK', 'Családi kedvezmény', 'Adóelőleg-nyilatkozat a családi kedvezmény (és járulékkedvezmény) érvényesítéséről', 'A nyilatkozatot azoknak a magánszemélyeknek kell benyújtaniuk, akik azt szeretnék, hogy a kifizetőjük, munkáltatójuk a családi kedvezmény figyelembevételével vonja le az adóelőleget. Adóelőleg-nyilatkozat munkáltatónak, és olyan kifizetőnek adható, aki összevonás alá eső rendszeres jövedelmet juttat (például havi vagy heti bért, munkadíjat, tiszteletdíjat, személyes közreműködés ellenértékét, egyéb juttatást, adóköteles társadalombiztosítási ellátást).', 40, ['családi kedvezmény', 'járulékkedvezmény', 'ANYCSK']),
            $this->tax('first_marriage_discount', 'ANYEHK', 'Első házasok kedvezménye', 'Adóelőleg-nyilatkozat az első házasok kedvezményének érvényesítéséről', 'A nyilatkozatot azoknak a magánszemélyeknek kell benyújtaniuk, akik azt szeretnék, hogy a kifizetőjük, munkáltatójuk az első házasok kedvezményének figyelembevételével vonja le az adóelőleget. Adóelőleg-nyilatkozat munkáltatónak, és olyan kifizetőnek adható, aki összevonás alá eső rendszeres jövedelmet juttat (például havi vagy heti bért, munkadíjat, tiszteletdíjat, személyes közreműködés ellenértékét, egyéb juttatást, adóköteles társadalombiztosítási ellátást).', 50, ['első házasok', 'adóelőleg', 'ANYEHK']),
            $this->tax('personal_discount', 'ANYSZK', 'Személyi kedvezmény', 'Adóelőleg-nyilatkozat a személyi kedvezmény érvényesítéséről', 'Ha a magánszemély kéri, hogy a kifizető, munkáltató a járandóságaiból személyi kedvezmény figyelembe vételével vonja le az adóelőleget, akkor ezt a nyilatkozatot töltse ki. Adóelőleg-nyilatkozat munkáltatónak és olyan kifizetőnek adható, aki összevonás alá eső rendszeres jövedelmet juttat (például havi vagy heti bért, munkadíjat, tiszteletdíjat, személyes közreműködés ellenértékét, egyéb juttatást, adóköteles társadalombiztosítási ellátást).', 60, ['személyi kedvezmény', 'adóelőleg', 'ANYSZK']),
            $this->tax('under_25_tax_discount_waiver', 'ANY25NEM', '25 év alattiak (nemleges)', 'Adóelőleg-nyilatkozat a 25 év alatti fiatalok kedvezményének részben vagy egészben történő mellőzéséről', '2022. január 1-től új adóalap-kedvezményt vehetnek igénybe a 25 év alatti fiatalok. A kedvezmény érvényesítését a fiatalnak nem kell kérnie, azt a munkáltató, rendszeres bevételt juttató kifizető a jogosultsági hónapokban automatikusan figyelembe veszi. Nyilatkozatot kizárólag akkor kell kitölteni, ha a fiatal a kedvezményt csak részben vagy egyáltalán nem kívánja érvényesíteni.', 70, ['25 év alatti', 'nemleges', 'ANY25NEM']),

            $this->own('personal_data_statement', 'Személyes adatok', 'A munkavállaló alap személyes és azonosító adatainak megadása vagy ellenőrzése.', 'A beléptetéshez és a nyilatkozatok pontos kitöltéséhez szükséges személyes adatok rögzítése. Meglévő munkavállalónál adatváltozási kérelemként érdemes kezelni.', DeclarationTemplate::GROUP_PERSONAL_DATA, DeclarationTemplate::CATEGORY_ONBOARDING, DeclarationTemplate::REQUIRED_ALWAYS, DeclarationTemplate::REVIEW_ROLE_RECRUITER, false, 100, ['személyes adatok', 'TAJ', 'adóazonosító']),
            $this->own('bank_account_statement', 'Nyilatkozat bankszámlaszámról', 'Nyilatkozat a munkabér utalásához használt bankszámlaszámról.', 'A munkavállaló ezen a nyilatkozaton adja meg azt a bankszámlaszámot, amelyre a munkabér és egyéb járandóságok utalását kéri.', DeclarationTemplate::GROUP_EMPLOYMENT, DeclarationTemplate::CATEGORY_PAYROLL, DeclarationTemplate::REQUIRED_ALWAYS, DeclarationTemplate::REVIEW_ROLE_PAYROLL, true, 110, ['bankszámla', 'munkabér', 'utalás']),
            $this->own('bank_account_change_statement', 'Bankszámlaszám módosítása', 'Meglévő munkavállaló bankszámlaszám-módosítási nyilatkozata.', 'A munkavállaló ezen a nyilatkozaton jelenti be, ha a munkabér és egyéb járandóságok utalásához használt bankszámlaszámát módosítani szeretné.', DeclarationTemplate::GROUP_EMPLOYMENT, DeclarationTemplate::CATEGORY_PAYROLL, DeclarationTemplate::REQUIRED_OPTIONAL, DeclarationTemplate::REVIEW_ROLE_PAYROLL, true, 120, ['bankszámlaszám módosítás', 'munkabér', 'utalás']),
            $this->own('deduction_statement', 'Nyilatkozat letiltásról', 'Nyilatkozat munkabérből történő letiltásról vagy annak hiányáról.', 'A munkavállaló ezen a nyilatkozaton ad tájékoztatást arról, hogy van-e munkabérből történő letiltása vagy olyan kötelezettsége, amelyet a munkáltatónak figyelembe kell vennie.', DeclarationTemplate::GROUP_EMPLOYMENT, DeclarationTemplate::CATEGORY_PAYROLL, DeclarationTemplate::REQUIRED_CONDITIONAL, DeclarationTemplate::REVIEW_ROLE_PAYROLL, false, 130, ['letiltás', 'munkabér', 'végrehajtás']),
            $this->own('child_extra_leave_statement', 'Gyermek után járó pótszabadság igénybevételéről', 'Nyilatkozat a gyermek után járó pótszabadság igénybevételéről.', 'A munkavállaló ezen a nyilatkozaton jelzi, ha gyermek után járó pótszabadságot szeretne igénybe venni, illetve megadja az ehhez szükséges gyermekadatokat.', DeclarationTemplate::GROUP_EMPLOYMENT, DeclarationTemplate::CATEGORY_EMPLOYMENT, DeclarationTemplate::REQUIRED_CONDITIONAL, DeclarationTemplate::REVIEW_ROLE_PAYROLL, true, 140, ['pótszabadság', 'gyermek', 'szabadság']),
            $this->own('tb_booklet_statement', 'TB kiskönyv nyilatkozat', 'Nyilatkozat a TB kiskönyv leadásáról vagy pótlásáról.', 'A munkavállaló ezen a nyilatkozaton jelzi, hogy le tudja-e adni a TB kiskönyvét, még nem állt biztosítási jogviszonyban, elvesztette a korábbi TB kiskönyvet, vagy az előző munkáltatótól nem kapta meg.', DeclarationTemplate::GROUP_EMPLOYMENT, DeclarationTemplate::CATEGORY_ONBOARDING, DeclarationTemplate::REQUIRED_ALWAYS, DeclarationTemplate::REVIEW_ROLE_PAYROLL, false, 150, ['TB kiskönyv', 'biztosítási jogviszony', 'beléptetés']),
            $this->own('employment_history_statement', 'Jogviszony nyilatkozat', 'Nyilatkozat a jelenlegi munkaviszonyt megelőző biztosítási jogviszonyokról.', 'A munkavállaló ezen a nyilatkozaton nyilatkozik arról, hogy a jelenlegi munkaviszonyt megelőző két évben milyen biztosítási jogviszonyai voltak, illetve volt-e olyan jogviszonya, amelyet dokumentummal igazolni tud.', DeclarationTemplate::GROUP_EMPLOYMENT, DeclarationTemplate::CATEGORY_ONBOARDING, DeclarationTemplate::REQUIRED_ALWAYS, DeclarationTemplate::REVIEW_ROLE_PAYROLL, false, 160, ['jogviszony', 'biztosítási jogviszony', 'TB']),
        ];
    }

    /**
     * @param list<string> $keywords
     * @return array<string,mixed>
     */
    private function tax(string $code, string $navCode, string $name, string $shortDescription, string $longDescription, int $sortOrder, array $keywords): array
    {
        return [
            'code' => $code,
            'name' => $name . ' (' . $navCode . ')',
            'category' => DeclarationTemplate::CATEGORY_TAX_ADVANCE,
            'declaration_group' => DeclarationTemplate::GROUP_TAX,
            'tax_year' => null,
            'version' => '1.0',
            'effective_from' => null,
            'effective_to' => null,
            'parent_template_id' => null,
            'renewal_policy' => DeclarationTemplate::RENEWAL_YEARLY,
            'required_policy' => DeclarationTemplate::REQUIRED_OPTIONAL,
            'review_role' => DeclarationTemplate::REVIEW_ROLE_PAYROLL,
            'needs_signature' => 0,
            'is_candidate_selectable' => 1,
            'company_scope' => 'all',
            'class_name' => null,
            'description' => $shortDescription,
            'details' => [
                'nav_code' => $navCode,
                'short_description' => $shortDescription,
                'long_description' => $longDescription,
                'tax_group' => 'SZJA',
                'taxpayer_scope' => 'Magánszemély',
                'keywords' => $keywords,
            ],
            'sort_order' => $sortOrder,
            'is_active' => 1,
        ];
    }

    /**
     * @param list<string> $keywords
     * @return array<string,mixed>
     */
    private function own(string $code, string $name, string $shortDescription, string $longDescription, string $group, string $category, string $requiredPolicy, string $reviewRole, bool $employeeSelectable, int $sortOrder, array $keywords): array
    {
        return [
            'code' => $code,
            'name' => $name,
            'category' => $category,
            'declaration_group' => $group,
            'tax_year' => null,
            'version' => '1.0',
            'effective_from' => null,
            'effective_to' => null,
            'parent_template_id' => null,
            'renewal_policy' => $employeeSelectable ? DeclarationTemplate::RENEWAL_WHEN_CHANGED : DeclarationTemplate::RENEWAL_PER_RELATION,
            'required_policy' => $requiredPolicy,
            'review_role' => $reviewRole,
            'needs_signature' => 0,
            'is_candidate_selectable' => $employeeSelectable ? 1 : 0,
            'company_scope' => 'all',
            'class_name' => null,
            'description' => $shortDescription,
            'details' => [
                'short_description' => $shortDescription,
                'long_description' => $longDescription,
                'tax_group' => '-',
                'taxpayer_scope' => 'Munkavállaló',
                'keywords' => $keywords,
            ],
            'sort_order' => $sortOrder,
            'is_active' => 1,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function payload(array $row): array
    {
        $payload = $row;
        $details = $payload['details'] ?? [];
        unset($payload['details']);

        $payload['details_json'] = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $payload;
    }

    private function errors(string $fallback): string
    {
        $errors = $this->templateModel->errors();

        return !empty($errors) ? implode(' ', $errors) : $fallback;
    }
}
