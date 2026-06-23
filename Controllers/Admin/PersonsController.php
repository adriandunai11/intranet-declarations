<?php

namespace App\Modules\Declarations\Controllers\Admin;

use App\Controllers\AdminBaseController;
use App\Modules\Declarations\Models\PersonModel;
use App\Modules\Declarations\Presenters\PersonTablePresenter;
use App\Modules\Declarations\Services\DeclarationPacketService;
use App\Modules\Declarations\Services\EmploymentRelationService;
use App\Modules\Declarations\Services\PersonMatchingService;
use App\Modules\Declarations\Services\PersonService;
use App\Modules\Declarations\Services\RecruiterService;
use Hermawan\DataTables\DataTable;
use Throwable;

class PersonsController extends AdminBaseController
{
    public $menu = 'declarations';
    public $submenu = 'declarationPersons';
    public $title = 'Nyilatkozat személyek';

    protected PersonService $personService;
    protected EmploymentRelationService $employmentRelationService;
    protected DeclarationPacketService $declarationPacketService;
    protected PersonTablePresenter $personTablePresenter;
    protected RecruiterService $recruiterService;

    public function __construct()
    {
        $this->personService = new PersonService();
        $this->employmentRelationService = new EmploymentRelationService();
        $this->declarationPacketService = new DeclarationPacketService();
        $this->personTablePresenter = new PersonTablePresenter();
        $this->recruiterService = new RecruiterService();
    }

    public function index()
    {
        $this->permissionCheck('declarations_persons_list');

        return view('App\Modules\Declarations\Views\admin\persons\list');
    }

    public function create()
    {
        $this->permissionCheck('declarations_persons_add');
        postAllowed();

        $input = $this->request->getPost();

        $matches = (new PersonMatchingService())->findPossibleMatches($input);

        if ($matches !== [] && empty($input['force_create'])) {
            return redirect()->back()
                ->withInput()
                ->with('possible_matches', $matches)
                ->with('validation', ['Lehetséges visszatérő dolgozó található. Ellenőrzés után válassz: meglévő személy megnyitása vagy új személy létrehozása.']);
        }

        $rules = [
            'antra_id' => 'permit_empty|trim|min_length[2]|max_length[50]|is_unique[declaration_persons.antra_id]',
            'lastname' => 'required|trim|min_length[2]|max_length[100]',
            'firstname' => 'required|trim|min_length[2]|max_length[100]',
            'email' => 'required|trim|valid_email|max_length[190]|is_unique[declaration_persons.email]',
            'birth_date' => 'permit_empty|valid_date[Y-m-d]',
        ];

        $messages = [
            'lastname' => ['required' => 'A vezetéknév megadása kötelező.'],
            'firstname' => ['required' => 'A keresztnév megadása kötelező.'],
            'email' => [
                'required' => 'Az e-mail cím megadása kötelező.',
                'valid_email' => 'Az e-mail cím formátuma hibás.',
                'is_unique' => 'Ez az e-mail cím már létezik.',
            ],
        ];

        if (!$this->validate($rules, $messages)) {
            return redirect()->back()
                ->withInput()
                ->with('validation', $this->validator->getErrors());
        }

        try {
            $personId = $this->personService->create($input);

            return redirect()->to(url('declarations/persons/' . $personId))
                ->with('sSuccess', 'Személy létrehozva. A következő lépésben jogviszonyt és nyilatkozatcsomagot lehet indítani.');
        } catch (Throwable $e) {
            return redirect()->back()
                ->withInput()
                ->with('validation', [$this->mapDuplicateError($e->getMessage())]);
        }
    }

    public function update(int $id)
    {
        $this->permissionCheck('declarations_persons_edit');
        postAllowed();

        $rules = [
            'antra_id' => 'permit_empty|trim|min_length[2]|max_length[50]',
            'lastname' => 'required|trim|min_length[2]|max_length[100]',
            'firstname' => 'required|trim|min_length[2]|max_length[100]',
            'email' => 'permit_empty|trim|valid_email|max_length[190]',
            'birth_date' => 'permit_empty|valid_date[Y-m-d]',
            'status' => 'required|in_list[active,inactive,blocked,merged]',
        ];

        if (!$this->validate($rules)) {
            return redirect()
                ->to(url('declarations/persons'))
                ->withInput()
                ->with('sError', implode(' ', $this->validator->getErrors()));
        }

        try {
            $this->personService->update($id, $this->request->getPost());

            return redirect()
                ->to(url('declarations/persons'))
                ->with('sSuccess', 'A személy adatai frissítve.');
        } catch (Throwable $e) {
            return redirect()
                ->to(url('declarations/persons'))
                ->withInput()
                ->with('sError', $this->mapDuplicateError($e->getMessage()));
        }
    }

    public function datatable()
    {
        $this->permissionCheck('declarations_persons_list');

        $model = new PersonModel();

        $builder = $model->builder()
            ->select([
                'id',
                'intranet_user_id',
                'antra_id',
                'lastname',
                'firstname',
                'birth_name',
                'mother_name',
                'birth_place',
                'birth_date',
                'tax_number',
                'tax_number AS tax_number_raw',
                'taj_number',
                'taj_number AS taj_number_raw',
                'email',
                'phone',
                'status',
                'status AS person_status',
                "CONCAT(lastname,' ',firstname) AS name",
            ]);

        return DataTable::of($builder)
            ->filter(function ($builder): void {
                $search = trim((string) ($this->request->getPost('search')['value'] ?? ''));

                if ($search === '') {
                    return;
                }

                $builder->groupStart()
                    ->like('id', $search)
                    ->orLike('antra_id', $search)
                    ->orLike('lastname', $search)
                    ->orLike('firstname', $search)
                    ->orLike('birth_name', $search)
                    ->orLike('mother_name', $search)
                    ->orLike('birth_place', $search)
                    ->orLike('email', $search)
                    ->orLike('phone', $search)
                    ->orLike('tax_number', $search)
                    ->orLike('taj_number', $search)
                    ->orLike('status', $search)
                    ->groupEnd();
            })
            ->edit('tax_number', fn($row): string => $this->personTablePresenter->mask((string) ($row->tax_number ?? ''), 4))
            ->edit('taj_number', fn($row): string => $this->personTablePresenter->mask((string) ($row->taj_number ?? ''), 3))
            ->edit('status', fn($row): string => $this->personTablePresenter->statusBadge((string) ($row->status ?? '')))
            ->add('intranet_link', fn($row): string => $this->personTablePresenter->intranetLinkBadge($row))
            ->add('actions', fn($row): string => $this->personTablePresenter->actions($row))
            ->toJson(true);
    }

    public function show(int $id)
    {
        $this->permissionCheck('declarations_persons_list');

        $person = $this->personService->find($id);

        if (!$person) {
            return redirect()
                ->to(url('declarations/persons'))
                ->with('sError', 'A keresett személy nem található.');
        }

        $relations = $this->employmentRelationService->findByPersonId($id);
        $divisions = $this->employmentRelationService->getActiveDivisions();
        $locations = $this->employmentRelationService->getActiveLocations();
        $recruiters = $this->recruiterService->getRecruiters();
        $recruiterDisplayNames = $this->recruiterService->getRecruiterDisplayMap();
        $templates = $this->declarationPacketService->getAvailableTemplates((int) date('Y'));
        $taxTemplates = $this->declarationPacketService->getCandidateSelectableTaxTemplates((int) date('Y'));
        $packets = $this->declarationPacketService->findPacketsByPersonId($id);

        return view('App\Modules\Declarations\Views\admin\persons\show', [
            'person' => $person,
            'relations' => $relations,
            'divisions' => $divisions,
            'locations' => $locations,
            'recruiters' => $recruiters,
            'recruiterDisplayNames' => $recruiterDisplayNames,
            'templates' => $templates,
            'taxTemplates' => $taxTemplates,
            'packets' => $packets,
        ]);
    }

    public function createRelation(int $personId)
    {
        $this->permissionCheck('declarations_relations_create');
        postAllowed();

        try {
            $this->employmentRelationService->createForPerson($personId, $this->request->getPost());

            return redirect()
                ->to(url('declarations/persons/' . $personId))
                ->with('sSuccess', 'Jogviszony sikeresen létrehozva.');
        } catch (Throwable $e) {
            return redirect()
                ->to(url('declarations/persons/' . $personId))
                ->withInput()
                ->with('sError', $e->getMessage());
        }
    }

    public function createPacket(int $personId, int $relationId)
    {
        $this->permissionCheck('declarations_packets_create');
        postAllowed();

        try {
            $templateIds = $this->request->getPost('template_ids') ?? [];
            $taxYear = $this->request->getPost('tax_year');

            $taxYear = $taxYear !== null && $taxYear !== '' ? (int) $taxYear : null;

            if ($this->request->getPost('packet_mode') === 'default_onboarding') {
                $packetId = $this->declarationPacketService->createDefaultOnboardingForRelation($relationId, $taxYear);
            } else {
                $packetId = $this->declarationPacketService->createForRelation(
                    $relationId,
                    is_array($templateIds) ? $templateIds : [],
                    $taxYear
                );
            }

            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sSuccess', 'Nyilatkozatcsomag létrehozva.');
        } catch (Throwable $e) {
            return redirect()
                ->to(url('declarations/persons/' . $personId))
                ->withInput()
                ->with('sError', $e->getMessage());
        }
    }

    private function mapDuplicateError(string $raw): string
    {
        $rawLower = strtolower($raw);

        return match (true) {
            str_contains($rawLower, 'email') => 'Ez az e-mail cím már létezik.',
            str_contains($rawLower, 'tax_number') => 'Ez az adóazonosító már létezik.',
            str_contains($rawLower, 'taj_number') => 'Ez a TAJ szám már létezik.',
            str_contains($rawLower, 'antra_id') => 'Ez az Antra azonosító már létezik.',
            default => $raw !== '' ? $raw : 'Hiba történt mentés közben.',
        };
    }
}
