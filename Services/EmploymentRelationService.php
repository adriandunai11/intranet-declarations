<?php

namespace App\Modules\Declarations\Services;

use App\Modules\Declarations\Entities\EmploymentRelation;
use App\Modules\Declarations\Models\EmploymentRelationModel;
use App\Modules\Declarations\Models\PersonModel;
use App\Models\BasicdataModel;
use App\Models\CustomerLocationsModel;
use App\Modules\Declarations\Services\DeclarationAuditService;
use RuntimeException;

class EmploymentRelationService
{
    protected EmploymentRelationModel $relationModel;
    protected PersonModel $personModel;
    protected BasicdataModel $basicdataModel;
    protected CustomerLocationsModel $locationsModel;
    protected RecruiterService $recruiterService;
    protected DeclarationAuditService $auditService;

    public function __construct()
    {
        $this->relationModel = new EmploymentRelationModel();
        $this->personModel = new PersonModel();
        $this->basicdataModel = new BasicdataModel();
        $this->locationsModel = new CustomerLocationsModel();
        $this->recruiterService = new RecruiterService();
        $this->auditService = new DeclarationAuditService();
    }

    public function findByPersonId(int $personId): array
    {
        return $this->relationModel->findByPersonId($personId);
    }

    public function createForPerson(int $personId, array $data): int
    {
        $person = $this->personModel->find($personId);

        if (!$person) {
            throw new RuntimeException('A személy nem található.');
        }

        $companyId = (int) ($data['company_id'] ?? 0);

        if ($companyId <= 0) {
            throw new RuntimeException('A cég megadása kötelező.');
        }

        $company = $this->basicdataModel
            ->where('type', 'division')
            ->where('status', 1)
            ->where('id', $companyId)
            ->first();

        if (!$company) {
            throw new RuntimeException('A kiválasztott cég nem található vagy nem aktív.');
        }

        $openRelations = $this->relationModel->findOpenByPersonAndCompany($personId, $companyId);

        if (!empty($openRelations)) {
            throw new RuntimeException('Ehhez a személyhez ennél a cégnél már van nyitott jogviszony.');
        }

        $locationId = (int) ($data['location_id'] ?? 0);
        $locationName = null;

        if ($locationId > 0) {
            $location = $this->locationsModel
                ->where('status', 'active')
                ->where('id', $locationId)
                ->first();

            if (!$location) {
                throw new RuntimeException('A kiválasztott telephely nem található vagy nem aktív.');
            }

            $locationName = $location->name;
            $locationId = (int) $location->id;
        } else {
            $locationId = null;
        }

        $primaryRecruiterUserId = (int) ($data['primary_recruiter_user_id'] ?? 0);
        $this->recruiterService->ensureRecruiterExists($primaryRecruiterUserId);

        $payload = [
            'person_id' => $personId,
            'company_id' => $companyId,
            'location_id' => $locationId,
            'location' => $locationName,
            'intranet_user_id' => null,
            'primary_recruiter_user_id' => $primaryRecruiterUserId,
            'onboarding_type' => $this->nullableString($data['onboarding_type'] ?? null) ?? EmploymentRelation::ONBOARDING_TYPE_CANDIDATE,
            'status' => EmploymentRelation::STATUS_ONBOARDING,
            'start_date' => $this->nullableString($data['start_date'] ?? null),
            'previous_relation_id' => isset($data['previous_relation_id']) && $data['previous_relation_id'] !== ''
                ? (int) $data['previous_relation_id']
                : null,
            'created_by_user_id' => function_exists('logged') ? logged('id') : null,
        ];

        $relationId = $this->relationModel->insert($payload, true);

        if (!$relationId) {
            $errors = $this->relationModel->errors();

            if (!empty($errors)) {
                throw new RuntimeException(implode(' ', $errors));
            }

            throw new RuntimeException('A jogviszony létrehozása sikertelen.');
        }

        $this->auditService->log('employment_relation_created', 'employment_relation', (int) $relationId, [
            'person_id' => $personId,
            'employment_relation_id' => (int) $relationId,
            'new_status' => EmploymentRelation::STATUS_ONBOARDING,
            'payload' => [
                'company_id' => $companyId,
                'location_id' => $locationId,
                'primary_recruiter_user_id' => $primaryRecruiterUserId,
                'onboarding_type' => $payload['onboarding_type'] ?? null,
                'start_date' => $payload['start_date'] ?? null,
            ],
        ]);

        return (int) $relationId;
    }

    public function closeRelation(int $personId, int $relationId, string $endDate): void
    {
        $relation = $this->relationModel->find($relationId);

        if (!$relation || (int) $relation->person_id !== $personId) {
            throw new RuntimeException('A jogviszony nem található ennél a személynél.');
        }

        $endDate = trim($endDate);

        if ($endDate === '' || strtotime($endDate) === false) {
            throw new RuntimeException('A lezárás dátumának megadása kötelező.');
        }

        if (!empty($relation->start_date) && strtotime($endDate) < strtotime((string) $relation->start_date)) {
            throw new RuntimeException('A lezárás dátuma nem lehet korábbi, mint a kezdés dátuma.');
        }

        if (!$relation->isOpen()) {
            throw new RuntimeException('Csak nyitott jogviszony zárható le.');
        }

        $oldStatus = (string) $relation->status;

        if (!$this->relationModel->close($relationId, $endDate)) {
            $errors = $this->relationModel->errors();

            throw new RuntimeException(!empty($errors) ? implode(' ', $errors) : 'A jogviszony lezárása sikertelen.');
        }

        $this->auditService->log('employment_relation_closed', 'employment_relation', $relationId, [
            'person_id' => $personId,
            'employment_relation_id' => $relationId,
            'old_status' => $oldStatus,
            'new_status' => EmploymentRelation::STATUS_CLOSED,
            'payload' => [
                'end_date' => $endDate,
            ],
        ]);
    }


    public function getRecruiters(): array
    {
        return $this->recruiterService->getRecruiters();
    }

    public function getRecruiterDisplayMap(): array
    {
        return $this->recruiterService->getRecruiterDisplayMap();
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    public function getActiveDivisions(): array
    {
        return $this->basicdataModel->getActiveDivisions();
    }

    public function getActiveLocations(): array
    {
        return $this->locationsModel->getActiveLocations();
    }
}
