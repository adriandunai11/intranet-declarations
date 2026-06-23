<?php

namespace App\Modules\Declarations\Controllers\Public;

use App\Controllers\BaseController;
use App\Modules\Declarations\Services\DeclarationForms\DeclarationFormRegistry;
use App\Modules\Declarations\Services\DeclarationPacketService;
use App\Modules\Declarations\Services\Exceptions\DeclarationAlreadySubmittedException;
use App\Modules\Declarations\Services\Exceptions\FormValidationException;
use App\Modules\Declarations\Services\Public\DeclarationSubmissionService;
use App\Modules\Declarations\Services\Public\InvitationContext;
use App\Modules\Declarations\Services\Public\InvitationContextService;
use App\Modules\Declarations\Services\Public\PublicUrlService;
use App\Modules\Declarations\Models\DeclarationTemplateModel;
use Throwable;

class InvitationController extends BaseController
{
    protected InvitationContextService $contextService;
    protected DeclarationSubmissionService $submissionService;
    protected DeclarationFormRegistry $formRegistry;
    protected PublicUrlService $urlService;
    protected DeclarationPacketService $packetService;
    protected DeclarationTemplateModel $templateModel;

    public function __construct()
    {
        helper('cookie');
        $this->contextService = new InvitationContextService();
        $this->submissionService = new DeclarationSubmissionService();
        $this->formRegistry = new DeclarationFormRegistry();
        $this->urlService = new PublicUrlService();
        $this->packetService = new DeclarationPacketService();
        $this->templateModel = new DeclarationTemplateModel();
    }

    public function landing()
    {
        return view('App\Modules\Declarations\Views\public\invitation\landing', [
            'title' => 'Miell Group nyilatkozatok',
        ]);
    }

    public function start(string $token)
    {
        try {
            $context = $this->contextService->resolveOrFail($token);
            $this->contextService->markOpened($context);

            $items = $this->submissionService->getItemsForContext($context);
            $itemUrls = [];

            foreach ($items as $item) {
                $itemUrls[(int) $item->id] = $this->urlService->item($context->token, (int) $item->id);
            }

            $optionalTaxTemplates = $this->packetService->getCandidateSelectableTaxTemplatesForPacket((int) $context->packet->id);
            $optionalTaxTemplateSupport = [];

            foreach ($optionalTaxTemplates as $optionalTemplate) {
                $optionalTaxTemplateSupport[(int) $optionalTemplate->id] = $this->formRegistry->hasConcreteHandlerForCode((string) $optionalTemplate->code);
            }

            return view('App\Modules\Declarations\Views\public\invitation\start', [
                'title' => 'Nyilatkozatok kitöltése',
                'invitation' => $context->invitation,
                'packet' => $context->packet,
                'person' => $context->person,
                'items' => $items,
                'itemUrls' => $itemUrls,
                'startUrl' => $this->urlService->start($context->token),
                'optionalTaxTemplates' => $optionalTaxTemplates,
                'optionalTaxTemplateSupport' => $optionalTaxTemplateSupport,
            ]);
        } catch (Throwable $e) {
            return $this->invalid($e->getMessage());
        }
    }

    public function selectTaxTemplate(string $token, int $templateId)
    {
        try {
            $context = $this->contextService->resolveOrFail($token);
            $template = $this->templateModel->find($templateId);

            if (!$template || !$this->formRegistry->hasConcreteHandlerForCode((string) $template->code)) {
                throw new \RuntimeException('Ez az adóügyi nyilatkozat még nem tölthető ki online.');
            }

            $itemId = $this->packetService->addCandidateSelectedTemplate((int) $context->packet->id, $templateId);

            return redirect()
                ->to($this->urlService->item($context->token, $itemId))
                ->with('sSuccess', 'A kiválasztott adóügyi nyilatkozat hozzáadva.');
        } catch (Throwable $e) {
            return redirect()
                ->to($this->urlService->start($token))
                ->with('sError', $e->getMessage());
        }
    }

    public function item(string $token, int $itemId)
    {
        try {
            $context = $this->contextService->resolveOrFail($token);
            $item = $this->submissionService->getItemForContext($context, $itemId);
            $submission = $this->submissionService->findSubmissionForItem($itemId);
            $handler = $this->formRegistry->forItem($item);

            if ($this->submissionService->isClosed($item)) {
                return view('App\Modules\Declarations\Views\public\forms\completed', [
                    'title' => 'Nyilatkozat beküldve',
                    'item' => $item,
                    'submission' => $submission,
                    'startUrl' => $this->urlService->start($context->token),
                ]);
            }

            return view($handler->view(), $this->viewData($context, $item, $submission, $handler->title($item)));
        } catch (Throwable $e) {
            return $this->invalid($e->getMessage());
        }
    }

    public function submitItem(string $token, int $itemId)
    {
        try {
            $context = $this->contextService->resolveOrFail($token);
            $item = $this->submissionService->getItemForContext($context, $itemId);
            $handler = $this->formRegistry->forItem($item);

            $this->submissionService->submit($context, $item, $handler, $this->request);

            return redirect()
                ->to($this->urlService->start($context->token))
                ->with('sSuccess', 'A nyilatkozat beküldése sikeres.');
        } catch (FormValidationException $e) {
            return redirect()
                ->to($this->urlService->item($token, $itemId))
                ->withInput()
                ->with('sError', $e->getMessage())
                ->with('validationErrors', $e->errors());
        } catch (DeclarationAlreadySubmittedException $e) {
            return redirect()
                ->to($this->urlService->item($token, $itemId))
                ->with('sError', $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'Public declaration submit failed: ' . $e->getMessage());
            log_message('error', $e->getTraceAsString());

            return redirect()
                ->to($this->urlService->item($token, $itemId))
                ->withInput()
                ->with('sError', $e->getMessage());
        }
    }

    protected function viewData(InvitationContext $context, object $item, $submission, string $title): array
    {
        return [
            'title' => $title,
            'invitation' => $context->invitation,
            'packet' => $context->packet,
            'person' => $context->person,
            'item' => $item,
            'submission' => $submission,
            'startUrl' => $this->urlService->start($context->token),
            'itemUrl' => $this->urlService->item($context->token, (int) $item->id),
        ];
    }

    protected function invalid(string $message = 'A megnyitott meghívó link nem érvényes, lejárt vagy már nem használható.')
    {
        return view('App\Modules\Declarations\Views\public\invitation\invalid', [
            'title' => 'Érvénytelen meghívó',
            'message' => $message,
        ]);
    }
}
