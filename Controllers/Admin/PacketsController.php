<?php

namespace App\Modules\Declarations\Controllers\Admin;

use App\Controllers\AdminBaseController;
use App\Modules\Declarations\Services\DeclarationPacketService;
use App\Modules\Declarations\Services\PacketReviewService;
use App\Modules\Declarations\Services\DeclarationNotificationService;
use App\Modules\Declarations\Services\Documents\DeclarationDocumentGenerationService;
use App\Modules\Declarations\Services\Documents\DeclarationDocumentPreviewService;
use Throwable;

class PacketsController extends AdminBaseController
{
    public $menu = 'declarations';
    public $submenu = 'declarationPackets';
    public $title = 'Nyilatkozatcsomagok';

    protected DeclarationPacketService $declarationPacketService;
    protected PacketReviewService $packetReviewService;
    protected DeclarationNotificationService $notificationService;
    protected DeclarationDocumentGenerationService $documentGenerationService;
    protected DeclarationDocumentPreviewService $documentPreviewService;

    public function __construct()
    {
        $this->declarationPacketService = new DeclarationPacketService();
        $this->packetReviewService = new PacketReviewService();
        $this->notificationService = new DeclarationNotificationService();
        $this->documentGenerationService = new DeclarationDocumentGenerationService();
        $this->documentPreviewService = new DeclarationDocumentPreviewService();

    }

    public function show(int $packetId)
    {
        $this->permissionCheck('declarations_packets_view');

        try {
            $details = $this->declarationPacketService->findPacketReviewDetails($packetId);

            return view('App\Modules\Declarations\Views\admin\packets\show', $details);
        } catch (Throwable $e) {
            $this->logFailure('packet_show', $e);

            return redirect()
                ->to(url('declarations/persons'))
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
            $this->logFailure('packet_item_add', $e);

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
            $this->logFailure('packet_item_accept', $e);

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
            $this->logFailure('packet_item_reject', $e);

            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->withInput()
                ->with('sError', $e->getMessage());
        }
    }

    public function rejectItems(int $packetId)
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
            $itemIds = $this->request->getPost('item_ids') ?? [];
            $reviewNotes = $this->request->getPost('review_notes') ?? [];

            $this->packetReviewService->rejectItems(
                $packetId,
                is_array($itemIds) ? $itemIds : [],
                is_array($reviewNotes) ? $reviewNotes : []
            );

            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sSuccess', 'A kijelölt nyilatkozatok elutasítva. A beálló egy összesítő e-mailt kapott.');
        } catch (Throwable $e) {
            $this->logFailure('packet_items_reject_batch', $e);

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
            $this->logFailure('packet_invitation_send', $e);

            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sError', $e->getMessage());
        }
    }


    public function generateItemDocument(int $packetId, int $itemId, string $format)
    {
        $this->permissionCheck('declarations_packets_view');
        postAllowed();

        try {
            $path = $this->documentGenerationService->generateForPacketItem($packetId, $itemId, $format);

            return $this->response->download($path, null);
        } catch (Throwable $e) {
            $this->logFailure('packet_item_document_generate', $e);

            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sError', $e->getMessage());
        }
    }

    public function previewItemDocument(int $packetId, int $itemId)
    {
        $this->permissionCheck('declarations_packets_view');

        try {
            try {
                $path = $this->documentPreviewService->generateTemporaryPdfForPacketItem($packetId, $itemId);
                $content = file_get_contents($path);

                if ($content === false) {
                    throw new \RuntimeException('Az előnézet nem olvasható.');
                }

                @unlink($path);

                return $this->response
                    ->setContentType('application/pdf')
                    ->setHeader('Content-Disposition', 'inline')
                    ->setBody($content);
            } catch (Throwable $previewError) {
                $this->logFailure('packet_item_document_preview_pdf', $previewError);

                return view('App\Modules\Declarations\Views\admin\documents\preview_html', array_merge(
                    $this->documentPreviewService->previewDataForPacketItem(
                        $packetId,
                        $itemId,
                        'A PDF előnézet most nem állítható elő, ezért az online kitöltésből készített dokumentum-előnézet látható.'
                    ),
                    [
                        'backUrl' => url('declarations/packets/' . $packetId),
                    ]
                ));
            }
        } catch (Throwable $e) {
            $this->logFailure('packet_item_document_preview', $e);

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
            $this->logFailure('packet_close', $e);

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
            $this->logFailure('packet_item_reopen_for_correction', $e);

            return redirect()
                ->to(url('declarations/packets/' . $packetId))
                ->with('sError', $e->getMessage());
        }
    }

    private function logFailure(string $action, Throwable $e): void
    {
        log_message('error', sprintf('Declarations packet admin action failed [%s]: %s', $action, $e->getMessage()));
        log_message('error', $e->getTraceAsString());
    }
}
