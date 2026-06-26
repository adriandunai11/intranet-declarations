<?php

namespace App\Modules\Declarations\Controllers\Public;

use App\Controllers\BaseController;
use App\Modules\Declarations\Services\Documents\DeclarationDocumentPreviewService;
use App\Modules\Declarations\Services\Public\DeclarationSubmissionService;
use App\Modules\Declarations\Services\Public\InvitationContext;
use App\Modules\Declarations\Services\Public\InvitationContextService;
use App\Modules\Declarations\Services\Public\PublicUrlService;
use Throwable;

class DocumentPreviewController extends BaseController
{
    protected InvitationContextService $contextService;
    protected DeclarationSubmissionService $submissionService;
    protected PublicUrlService $urlService;
    protected DeclarationDocumentPreviewService $previewService;

    public function __construct()
    {
        $this->contextService = new InvitationContextService();
        $this->submissionService = new DeclarationSubmissionService();
        $this->urlService = new PublicUrlService();
        $this->previewService = new DeclarationDocumentPreviewService();
    }

    public function item(string $token, int $itemId)
    {
        try {
            $context = $this->contextService->resolveOrFail($token);

            if (!$this->isAntraVerified($context)) {
                return redirect()
                    ->to($this->urlService->start($token))
                    ->with('sError', 'A dokumentum előnézetéhez először adja meg az Antra azonosítót.');
            }

            $item = $this->submissionService->getItemForContext($context, $itemId);
            $submission = $this->submissionService->findSubmissionForItem($itemId);

            if (!$submission) {
                return redirect()
                    ->to($this->urlService->start($token))
                    ->with('sError', 'Előnézet csak mentett nyilatkozathoz készíthető.');
            }

            $path = $this->previewService->generateTemporaryPdfForPacketItem((int) $context->packet->id, (int) $item->id);
            $content = file_get_contents($path);

            if ($content === false) {
                throw new \RuntimeException('Az előnézet nem olvasható.');
            }

            @unlink($path);

            return $this->response
                ->setContentType('application/pdf')
                ->setHeader('Content-Disposition', 'inline')
                ->setBody($content);
        } catch (Throwable $e) {
            return view('App\Modules\Declarations\Views\public\documents\preview_unavailable', [
                'title' => 'Dokumentum előnézet',
                'message' => $e->getMessage(),
                'backUrl' => $this->urlService->start($token),
            ]);
        }
    }

    protected function isAntraVerified(InvitationContext $context): bool
    {
        return session()->get('declaration_invitation_antra_verified_' . (int) $context->invitation->id) === true;
    }
}
