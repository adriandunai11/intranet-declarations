<?php

namespace App\Modules\Declarations\Controllers\Admin;

use App\Controllers\AdminBaseController;
use App\Modules\Declarations\Models\DeclarationAuditLogModel;
use App\Modules\Declarations\Models\PersonModel;
use App\Modules\Declarations\Services\AuditActorResolverService;
use App\Modules\Declarations\Services\DeclarationPacketService;
use Throwable;

class AuditController extends AdminBaseController
{
    public $menu = 'declarations';
    public $submenu = 'declarationPersons';
    public $title = 'Előzmények';

    protected DeclarationAuditLogModel $auditLogModel;
    protected PersonModel $personModel;
    protected DeclarationPacketService $packetService;
    protected AuditActorResolverService $actorResolver;

    public function __construct()
    {
        $this->auditLogModel = new DeclarationAuditLogModel();
        $this->personModel = new PersonModel();
        $this->packetService = new DeclarationPacketService();
        $this->actorResolver = new AuditActorResolverService();
    }

    public function person(int $personId)
    {
        $this->permissionCheck('declarations_persons_list');

        $person = $this->personModel->find($personId);

        if (!$person) {
            return redirect()
                ->to(url('declarations/persons'))
                ->with('sError', 'A keresett személy nem található.');
        }

        return view('App\Modules\Declarations\Views\admin\audit\show', [
            'title' => 'Személy előzmények',
            'heading' => 'Személy előzmények',
            'subjectTitle' => method_exists($person, 'fullName') ? $person->fullName() : ('Személy #' . $personId),
            'subjectMeta' => [
                'Személy ID' => '#' . $personId,
                'Antra' => $person->antra_id ?: '-',
                'E-mail' => $person->email ?: '-',
            ],
            'backUrl' => url('declarations/persons/' . $personId),
            'backLabel' => 'Vissza a személy adatlapjára',
            'auditLogs' => $this->actorResolver->enrichAuditLogs(
                $this->auditLogModel->findByPersonId($personId, 200)
            ),
            'tableId' => 'personAuditLogTable',
            'emptyText' => 'Még nincs személyhez vagy jogviszonyhoz tartozó naplózott esemény.',
        ]);
    }

    public function packet(int $packetId)
    {
        $this->permissionCheck('declarations_packets_view');

        try {
            $details = $this->packetService->findPacketDetails($packetId);
        } catch (Throwable $e) {
            return redirect()
                ->to(url('declarations/persons'))
                ->with('sError', $e->getMessage());
        }

        $packet = $details['packet'];
        $person = $details['person'] ?? null;
        $company = $details['company'] ?? null;

        return view('App\Modules\Declarations\Views\admin\audit\show', [
            'title' => 'Nyilatkozatcsomag előzmények',
            'heading' => 'Nyilatkozatcsomag előzmények',
            'subjectTitle' => 'Csomag #' . (int) $packet->id,
            'subjectMeta' => [
                'Dolgozó' => $person && method_exists($person, 'fullName') ? $person->fullName() : '-',
                'Cég' => $company->name ?? ('#' . ($packet->company_id ?? '-')),
                'Adóév' => $packet->tax_year ?: '-',
            ],
            'backUrl' => url('declarations/packets/' . $packetId),
            'backLabel' => 'Vissza a csomaghoz',
            'auditLogs' => $this->actorResolver->enrichAuditLogs(
                $this->auditLogModel->findByPacketId($packetId, 200)
            ),
            'tableId' => 'packetAuditLogTable',
            'emptyText' => 'Még nincs naplózott esemény ehhez a csomaghoz.',
        ]);
    }
}
