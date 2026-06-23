<?php

namespace App\Modules\Declarations\Controllers\Admin;

use App\Controllers\AdminBaseController;
use App\Modules\Declarations\Services\DeclarationPacketService;
use App\Modules\Declarations\Services\PacketReviewService;
use App\Modules\Declarations\Services\DeclarationNotificationService;
use Throwable;

class PacketsController extends AdminBaseController
{
    public $menu = 'declarations';
    public $submenu = 'declarationPackets';
    public $title = 'Nyilatkozatcsomagok';

    protected DeclarationPacketService $declarationPacketService;
    protected PacketReviewService $packetReviewService;
    protected DeclarationNotificationService $notificationService;

    public function __construct()
    {
        $this->declarationPacketService = new DeclarationPacketService();
        $this->packetReviewService = new PacketReviewService();
        $this->notificationService = new DeclarationNotificationService();

    }

    public function show(int $packetId)
    {
        $this->permissionCheck('declarations_packets_view');

        try {
            $details = $this->declarationPacketService->findPacketReviewDetails($packetId);

            return view('App\Modules\Declarations\Views\admin\packets\show', $details);
        } catch (Throwable $e) {
            return redirect()
                ->to(url('declarations/persons'))
                ->with('sError', $e->getMessage());
        }
    }
    public function createInvitation(int $packetId)
    {
        $this->permissionCheck('declarations_packets_invite');
        postAllowed();

        try {
            $invitation = $this->declarationPacketService->createInvitationLink($packetId);

            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sSuccess', 'Meghívó link létrehozva: ' . $invitation['url']);
        } catch (Throwable $e) {
            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sError', $e->getMessage());
        }
    }

    public function addItem(int $packetId)
    {
        if (!hasPermissions('declarations_admin_override') && !hasPermissions('declarations_packets_create')) {
            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sError', 'Nincs jogosultságod nyilatkozat hozzáadására.');
        }

        postAllowed();

        try {
            $templateId = (int) $this->request->getPost('template_id');

            if ($templateId <= 0) {
                throw new \RuntimeException('Válassz hozzáadandó nyilatkozatot.');
            }

            $this->declarationPacketService->addTemplateToPacket($packetId, $templateId);

            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sSuccess', 'A nyilatkozat hozzáadva a csomaghoz.');
        } catch (Throwable $e) {
            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sError', $e->getMessage());
        }
    }

    public function acceptItem(int $packetId, int $itemId)
    {
        if (
            !hasPermissions('declarations_admin_override')
            && !hasPermissions('declarations_review_recruiter')
            && !hasPermissions('declarations_review_payroll')
        ) {
            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sError', 'Nincs jogosultságod nyilatkozat ellenőrzésére.');
        }

        postAllowed();

        try {
            $this->packetReviewService->acceptItem($packetId, $itemId);

            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sSuccess', 'A nyilatkozat elfogadva.');
        } catch (Throwable $e) {
            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sError', $e->getMessage());
        }
    }

    public function rejectItem(int $packetId, int $itemId)
    {
        if (
            !hasPermissions('declarations_admin_override')
            && !hasPermissions('declarations_review_recruiter')
            && !hasPermissions('declarations_review_payroll')
        ) {
            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sError', 'Nincs jogosultságod nyilatkozat ellenőrzésére.');
        }

        postAllowed();

        try {
            $this->packetReviewService->rejectItem(
                $packetId,
                $itemId,
                (string) $this->request->getPost('review_note')
            );

            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sSuccess', 'A nyilatkozat elutasítva. A beálló értesítése elindult.');
        } catch (Throwable $e) {
            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->withInput()
                ->with('sError', $e->getMessage());
        }
    }

    public function sendNewInvitationLink(int $packetId)
    {
        if (!hasPermissions('declarations_invitations_regenerate')) {
            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sError', 'Nincs jogosultságod új meghívó link küldésére.');
        }

        postAllowed();

        try {
            $invitation = $this->declarationPacketService->createNewInvitationLink($packetId);

            $this->notificationService->notifyInvitationLinkCreated(
                $packetId,
                $invitation['url']
            );

            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sSuccess', 'Az új meghívó link elkészült és kiküldésre került.');
        } catch (Throwable $e) {
            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sError', $e->getMessage());
        }
    }

    public function closePacket(int $packetId)
    {
        if (!hasPermissions('declarations_admin_override')) {
            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sError', 'Nincs jogosultságod a nyilatkozatcsomag lezárásához.');
        }

        postAllowed();

        try {
            $this->declarationPacketService->closePacket($packetId);

            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sSuccess', 'A nyilatkozatcsomag lezárva.');
        } catch (Throwable $e) {
            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sError', $e->getMessage());
        }
    }

    public function reopenItemForCorrection(int $packetId, int $itemId)
    {
        if (!hasPermissions('declarations_admin_override')) {
            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sError', 'Nincs jogosultságod admin újranyitásra.');
        }

        postAllowed();

        $reviewNote = trim((string) $this->request->getPost('review_note'));

        try {
            $this->packetReviewService->reopenItemForCorrection($packetId, $itemId, $reviewNote);

            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sSuccess', 'A nyilatkozat javításra újranyitva. Szükség esetén küldj külön új meghívó linket a beállónak.');
        } catch (Throwable $e) {
            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sError', $e->getMessage());
        }
    }
}
