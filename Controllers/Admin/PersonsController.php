<?php

namespace App\Modules\Declarations\Controllers\Admin;

use App\Controllers\AdminBaseController;
use App\Modules\Declarations\Models\PersonModel;
use App\Modules\Declarations\Models\DeclarationAuditLogModel;
use App\Modules\Declarations\Presenters\PersonTablePresenter;
use App\Modules\Declarations\Services\DeclarationPacketService;
use App\Modules\Declarations\Services\EmploymentRelationService;
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
    protected DeclarationAuditLogModel $auditLogModel;

    public function __construct()
    {
        $this->personService = new PersonService();
        $this->employmentRelationService = new EmploymentRelationService();
        $this->declarationPacketService = new DeclarationPacketService();
        $this->personTablePresenter = new PersonTablePresenter();
        $this->recruiterService = new RecruiterService();
        $this->auditLogModel = new DeclarationAuditLogModel();
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
        $antraId = trim((string) ($input['antra_id'] ?? ''));

        if ($antraId !== '') {
            $existingPerson = (new PersonModel())->findByAntraId($antraId);

            if ($existingPerson) {
                $message = 'Ehhez az Antra azonosítóhoz már létezik személy: ' . $existingPerson->fullName() . '.';

                if ($this->request->isAJAX()) {
                    return $this->jsonResponse([
                        'success' => false,
                        'warning' => $message,
                        'person_id' => (int) $existingPerson->id,
                    ], 409);
                }

                return redirect()->back()
                    ->withInput()
                    ->with('validation', [$message]);
            }
        }

        $rules = [
            'antra_id' => 'required|trim|min_length[2]|max_length[50]|is_unique[declaration_persons.antra_id]',
            'lastname' => 'required|trim|min_length[2]|max_length[100]',
            'firstname' => 'required|trim|min_length[2]|max_length[100]',
            'email' => 'required|trim|valid_email|max_length[190]|is_unique[declaration_persons.email]',
        ];

        $messages = [
            'antra_id' => [
                'required' => 'Az Antra azonosító megadása kötelező.',
                'is_unique' => 'Ez az Antra azonosító már létezik.',
            ],
            'lastname' => ['required' => 'A vezetéknév megadása kötelező.'],
            'firstname' => ['required' => 'A keresztnév megadása kötelező.'],
            'email' => [
                'required' => 'Az e-mail cím megadása kötelező.',
                'valid_email' => 'Az e-mail cím formátuma hibás.',
                'is_unique' => 'Ez az e-mail cím már létezik.',
            ],
        ];

        if (!$this->validate($rules, $messages)) {
            if ($this->request->isAJAX()) {
                return $this->jsonResponse([
                    'success' => false,
                    'errors' => $this->validator->getErrors(),
                ], 422);
            }

            return redirect()->back()
                ->withInput()
                ->with('validation', $this->validator->getErrors());
        }

        try {
            $personId = $this->personService->create($input);

            if ($this->request->isAJAX()) {
                return $this->jsonResponse([
                    'success' => true,
                    'person_id' => $personId,
                    'message' => 'Személy létrehozva.',
                ]);
            }

            return redirect()->to(url('declarations/persons'))
                ->with('sSuccess', 'Személy létrehozva. A listában megnyitható a személy adatlapja, ha jogviszonyt vagy nyilatkozatcsomagot indítanál.');
        } catch (Throwable $e) {
            $this->logFailure('person_create', $e);

            if ($this->request->isAJAX()) {
                return $this->jsonResponse([
                    'success' => false,
                    'errors' => [$this->mapDuplicateError($e->getMessage())],
                ], 422);
            }

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
            if ($this->request->isAJAX()) {
                return $this->jsonResponse([
                    'success' => false,
                    'errors' => $this->validator->getErrors(),
                ], 422);
            }

            return redirect()
                ->to(url('declarations/persons'))
                ->withInput()
                ->with('sError', implode(' ', $this->validator->getErrors()));
        }

        try {
            $this->personService->update($id, $this->request->getPost());

            if ($this->request->isAJAX()) {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'A személy adatai frissítve.',
                    'person' => $this->personPayload($this->personService->find($id)),
                ]);
            }

            return redirect()
                ->to(url('declarations/persons'))
                ->with('sSuccess', 'A személy adatai frissítve.');
        } catch (Throwable $e) {
            $this->logFailure('person_update', $e);

            if ($this->request->isAJAX()) {
                return $this->jsonResponse([
                    'success' => false,
                    'errors' => [$this->mapDuplicateError($e->getMessage())],
                ], 422);
            }

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

    public function checkAntra()
    {
        if (!hasPermissions('declarations_persons_add') && !hasPermissions('declarations_persons_edit')) {
            return $this->jsonResponse([
                'success' => false,
                'errors' => ['Nincs jogosultságod az Antra azonosító ellenőrzésére.'],
            ], 403);
        }

        $antraId = trim((string) $this->request->getGet('antra_id'));
        $excludePersonId = (int) $this->request->getGet('exclude_person_id');

        if ($antraId === '') {
            return $this->jsonResponse([
                'success' => true,
                'exists' => false,
            ]);
        }

        $existingPerson = (new PersonModel())->findByAntraId($antraId);
        $exists = $existingPerson && (int) $existingPerson->id !== $excludePersonId;

        return $this->jsonResponse([
            'success' => true,
            'exists' => (bool) $exists,
            'message' => $exists
                ? 'Ehhez az Antra azonosítóhoz már létezik személy: ' . $existingPerson->fullName() . '.'
                : 'Az Antra azonosító szabad.',
            'person_id' => $exists ? (int) $existingPerson->id : null,
        ]);
    }

    public function json(int $id)
    {
        $this->permissionCheck('declarations_persons_list');

        $person = $this->personService->find($id);

        if (!$person) {
            return $this->jsonResponse([
                'success' => false,
                'errors' => ['A keresett személy nem található.'],
            ], 404);
        }

        return $this->jsonResponse([
            'success' => true,
            'person' => $this->personPayload($person),
        ]);
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
        $sentPacketRelationIds = [];
        $sentPacketCompanyYearKeys = [];
        $draftPacketsByRelationId = [];

        foreach ($packets as $packet) {
            $relationId = (int) $packet->employment_relation_id;
            $companyYearKey = (int) $packet->company_id . ':' . (int) ($packet->tax_year ?: date('Y'));

            if ((string) $packet->status === 'draft') {
                $draftPacketsByRelationId[$relationId] ??= $packet;
                continue;
            }

            if ((string) $packet->status !== 'cancelled') {
                $sentPacketRelationIds[$relationId] = true;
                $sentPacketCompanyYearKeys[$companyYearKey] = true;
            }
        }

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
            'sentPacketRelationIds' => $sentPacketRelationIds,
            'sentPacketCompanyYearKeys' => $sentPacketCompanyYearKeys,
            'draftPacketsByRelationId' => $draftPacketsByRelationId,
            'auditLogs' => $this->auditLogModel->findByPersonId($id, 50),
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
            $this->logFailure('relation_create', $e);

            return redirect()
                ->to(url('declarations/persons/' . $personId))
                ->withInput()
                ->with('sError', $e->getMessage());
        }
    }

    public function closeRelation(int $personId, int $relationId)
    {
        if (!hasPermissions('declarations_admin_override') && !hasPermissions('declarations_review_payroll')) {
            return redirect()
                ->to(url('declarations/persons/' . $personId))
                ->with('sError', 'Nincs jogosultságod jogviszony lezárására.');
        }

        postAllowed();

        try {
            $this->employmentRelationService->closeRelation(
                $personId,
                $relationId,
                (string) $this->request->getPost('end_date')
            );

            return redirect()
                ->to(url('declarations/persons/' . $personId))
                ->with('sSuccess', 'Jogviszony lezárva.');
        } catch (Throwable $e) {
            $this->logFailure('relation_close', $e);

            return redirect()
                ->to(url('declarations/persons/' . $personId))
                ->withInput()
                ->with('sError', $e->getMessage());
        }
    }

    public function reopenRelation(int $personId, int $relationId)
    {
        if (!hasPermissions('declarations_admin_override')) {
            return redirect()
                ->to(url('declarations/persons/' . $personId))
                ->with('sError', 'Nincs jogosultságod jogviszony visszanyitására.');
        }

        postAllowed();

        try {
            $this->employmentRelationService->reopenRelation($personId, $relationId);

            return redirect()
                ->to(url('declarations/persons/' . $personId))
                ->with('sSuccess', 'Jogviszony visszanyitva.');
        } catch (Throwable $e) {
            $this->logFailure('relation_reopen', $e);

            return redirect()
                ->to(url('declarations/persons/' . $personId))
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
            $this->logFailure('packet_create_for_relation', $e);

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

    private function personPayload($person): array
    {
        if (!$person) {
            return [];
        }

        return [
            'id' => (int) $person->id,
            'antra_id' => $person->antra_id ?? '',
            'lastname' => $person->lastname ?? '',
            'firstname' => $person->firstname ?? '',
            'birth_name' => $person->birth_name ?? '',
            'mother_name' => $person->mother_name ?? '',
            'birth_place' => $person->birth_place ?? '',
            'birth_date' => $person->birth_date ?? '',
            'tax_number' => $person->tax_number ?? '',
            'taj_number' => $person->taj_number ?? '',
            'email' => $person->email ?? '',
            'phone' => $person->phone ?? '',
            'status' => $person->status ?? 'active',
        ];
    }

    private function jsonResponse(array $payload, int $status = 200)
    {
        if (function_exists('csrf_hash')) {
            $payload['csrfHash'] = csrf_hash();
        }

        return $this->response
            ->setStatusCode($status)
            ->setJSON($payload);
    }

    private function logFailure(string $action, Throwable $e): void
    {
        log_message('error', sprintf('Declarations person admin action failed [%s]: %s', $action, $e->getMessage()));
        log_message('error', $e->getTraceAsString());
    }
}
