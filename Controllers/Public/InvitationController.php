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
use App\Modules\Declarations\Presenters\Submissions\SubmissionPresenterRegistry;
use Throwable;

class InvitationController extends BaseController
{
    protected InvitationContextService $contextService;
    protected DeclarationSubmissionService $submissionService;
    protected DeclarationFormRegistry $formRegistry;
    protected PublicUrlService $urlService;
    protected DeclarationPacketService $packetService;
    protected DeclarationTemplateModel $templateModel;
    protected SubmissionPresenterRegistry $submissionPresenterRegistry;

    public function __construct()
    {
        helper('cookie');
        $this->contextService = new InvitationContextService();
        $this->submissionService = new DeclarationSubmissionService();
        $this->formRegistry = new DeclarationFormRegistry();
        $this->urlService = new PublicUrlService();
        $this->packetService = new DeclarationPacketService();
        $this->templateModel = new DeclarationTemplateModel();
        $this->submissionPresenterRegistry = new SubmissionPresenterRegistry();
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

            if (!$this->isAntraVerified($context)) {
                return $this->antraVerificationView($context);
            }

            $this->contextService->markOpened($context);

            $items = $this->submissionService->getItemsForContext($context);
            $openPersonalDataItem = $this->findOpenPersonalDataItem($items, $context->packet);

            if ($openPersonalDataItem) {
                return redirect()
                    ->to($this->urlService->item($context->token, (int) $openPersonalDataItem->id));
            }

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
                'finalizeUrl' => $this->urlService->finalize($context->token),
                'optionalTaxTemplates' => $optionalTaxTemplates,
                'optionalTaxTemplateSupport' => $optionalTaxTemplateSupport,
                'canFinalize' => $this->submissionService->canFinalize($context),
                'summaryRowsByItemId' => $this->summaryRowsByItemId($context, $items),
            ]);
        } catch (Throwable $e) {
            return $this->invalid($e->getMessage());
        }
    }

    public function verifyAntra(string $token)
    {
        try {
            $context = $this->contextService->resolveOrFail($token);
            $submittedAntraId = trim((string) $this->request->getPost('antra_id'));
            $expectedAntraId = trim((string) ($context->person->antra_id ?? ''));

            if ($submittedAntraId === '' || $expectedAntraId === '' || !hash_equals($expectedAntraId, $submittedAntraId)) {
                return redirect()
                    ->to($this->urlService->start($token))
                    ->withInput()
                    ->with('sError', 'Az Antra azonosító nem egyezik a meghívóhoz rögzített azonosítóval.');
            }

            session()->set($this->antraSessionKey($context), true);

            return redirect()
                ->to($this->urlService->start($context->token));
        } catch (Throwable $e) {
            return $this->invalid($e->getMessage());
        }
    }

    public function selectTaxTemplate(string $token, int $templateId)
    {
        try {
            $context = $this->contextService->resolveOrFail($token);

            if (!$this->isAntraVerified($context)) {
                return redirect()
                    ->to($this->urlService->start($token))
                    ->with('sError', 'A folytatáshoz először add meg az Antra azonosítót.');
            }

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

    public function finalize(string $token)
    {
        try {
            $context = $this->contextService->resolveOrFail($token);

            if (!$this->isAntraVerified($context)) {
                return redirect()
                    ->to($this->urlService->start($token))
                    ->with('sError', 'A folytatáshoz először add meg az Antra azonosítót.');
            }

            $this->submissionService->finalize($context);

            return redirect()
                ->to($this->urlService->start($context->token))
                ->with('sSuccess', 'A nyilatkozatcsomag végleges beküldése sikeres.');
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

            if (!$this->isAntraVerified($context)) {
                return redirect()
                    ->to($this->urlService->start($token))
                    ->with('sError', 'A folytatáshoz először add meg az Antra azonosítót.');
            }

            $item = $this->submissionService->getItemForContext($context, $itemId);
            $openPersonalDataItem = $this->findOpenPersonalDataItem(
                $this->submissionService->getItemsForContext($context),
                $context->packet
            );

            if ($openPersonalDataItem && (int) $openPersonalDataItem->id !== (int) $item->id) {
                return redirect()
                    ->to($this->urlService->item($context->token, (int) $openPersonalDataItem->id))
                    ->with('sError', 'Először az Antra azonosítót és a személyes adatokat kell megadni.');
            }

            $submission = $this->submissionService->findSubmissionForItem($itemId);
            $handler = $this->formRegistry->forItem($item);

            if ($this->submissionService->isClosed($item, $context->packet)) {
                return view('App\Modules\Declarations\Views\public\forms\completed', [
                    'title' => 'Nyilatkozat beküldve',
                    'item' => $item,
                    'submission' => $submission,
                    'displayRows' => $this->submissionPresenterRegistry->rowsFor(
                        (string) ($item->template_code ?? ''),
                        $submission
                    ),
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

            if (!$this->isAntraVerified($context)) {
                return redirect()
                    ->to($this->urlService->start($token))
                    ->with('sError', 'A folytatáshoz először add meg az Antra azonosítót.');
            }

            $item = $this->submissionService->getItemForContext($context, $itemId);
            $openPersonalDataItem = $this->findOpenPersonalDataItem(
                $this->submissionService->getItemsForContext($context),
                $context->packet
            );

            if ($openPersonalDataItem && (int) $openPersonalDataItem->id !== (int) $item->id) {
                return redirect()
                    ->to($this->urlService->item($context->token, (int) $openPersonalDataItem->id))
                    ->with('sError', 'Először az Antra azonosítót és a személyes adatokat kell megadni.');
            }

            $handler = $this->formRegistry->forItem($item);

            $this->submissionService->submit($context, $item, $handler, $this->request);

            return redirect()
                ->to($this->urlService->start($context->token))
                ->with('sSuccess', 'A nyilatkozat mentése sikeres. A végleges beküldést az összesítőn tudod elindítani.');
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

    protected function antraVerificationView(InvitationContext $context)
    {
        return view('App\Modules\Declarations\Views\public\invitation\verify_antra', [
            'title' => 'Hozzáférés ellenőrzése',
            'verifyUrl' => $this->urlService->verifyAntra($context->token),
        ]);
    }

    protected function isAntraVerified(InvitationContext $context): bool
    {
        return session()->get($this->antraSessionKey($context)) === true;
    }

    protected function antraSessionKey(InvitationContext $context): string
    {
        return 'declaration_invitation_antra_verified_' . (int) $context->invitation->id;
    }

    protected function summaryRowsByItemId(InvitationContext $context, array $items): array
    {
        $submissionsByItemId = $this->submissionService->submissionsByItemId((int) $context->packet->id);
        $rowsByItemId = [];

        foreach ($items as $item) {
            $submission = $submissionsByItemId[(int) $item->id] ?? null;

            if (!$submission) {
                continue;
            }

            $rowsByItemId[(int) $item->id] = $this->submissionPresenterRegistry->rowsFor(
                (string) ($item->template_code ?? ''),
                $submission
            );
        }

        return $rowsByItemId;
    }

    protected function findOpenPersonalDataItem(array $items, object $packet): ?object
    {
        foreach ($items as $item) {
            if ((string) ($item->template_code ?? '') !== 'personal_data_statement') {
                continue;
            }

            if (!in_array((string) $item->status, ['completed', 'accepted'], true)) {
                return $item;
            }
        }

        return null;
    }

    protected function invalid(string $message = 'A megnyitott meghívó link nem érvényes, lejárt vagy már nem használható.')
    {
        return view('App\Modules\Declarations\Views\public\invitation\invalid', [
            'title' => 'Érvénytelen meghívó',
            'message' => $message,
        ]);
    }
}
