<?php

namespace App\Modules\Declarations\Controllers\Employee;

use App\Controllers\AdminBaseController;
use App\Modules\Declarations\Services\DeclarationNotificationService;
use App\Modules\Declarations\Services\Documents\DeclarationDocumentPreviewService;
use App\Modules\Declarations\Services\EmployeeDeclarationSelfService;
use Throwable;

class MyDeclarationsController extends AdminBaseController
{
    protected EmployeeDeclarationSelfService $selfService;
    protected DeclarationNotificationService $notificationService;
    protected DeclarationDocumentPreviewService $documentPreviewService;

    public function __construct()
    {
        $this->selfService = new EmployeeDeclarationSelfService();
        $this->notificationService = new DeclarationNotificationService();
        $this->documentPreviewService = new DeclarationDocumentPreviewService();
    }

    public function index()
    {
        $this->permissionCheck('declarations_employee_view_own');

        try {
            $userId = $this->currentUserId();
            $data = $this->selfService->dashboardForUser($userId);

            return view('App\Modules\Declarations\Views\employee\my_declarations\index', $data);
        } catch (Throwable $e) {
            log_message('error', 'Employee declarations dashboard failed: ' . $e->getMessage());

            return view('App\Modules\Declarations\Views\employee\my_declarations\index', [
                'person' => null,
                'companies' => [],
                'templates' => [],
                'packets' => [],
                'defaultTaxYear' => (int) date('Y'),
                'pageError' => $e->getMessage(),
            ]);
        }
    }

    public function start()
    {
        $this->permissionCheck('declarations_employee_view_own');
        postAllowed();

        try {
            $result = $this->selfService->startForEmployee(
                $this->currentUserId(),
                (int) $this->request->getPost('company_id'),
                (int) $this->request->getPost('tax_year'),
                $this->request->getPost('template_ids') ?? []
            );

            try {
                $this->notificationService->notifyEmployeeSelfServiceInvitation(
                    (int) $result['packet_id'],
                    (string) $result['url']
                );

                return redirect()
                    ->to(url('declarations/my-declarations'))
                    ->with('sSuccess', 'A nyilatkozat kitöltési link elkészült és e-mailben kiküldtük.');
            } catch (Throwable $mailError) {
                log_message('error', 'Employee self-service invitation mail failed: ' . $mailError->getMessage());

                return redirect()
                    ->to(url('declarations/my-declarations'))
                    ->with('sError', 'A csomag elkészült, de az e-mail küldés nem sikerült. Ideiglenes kitöltési link: ' . (string) $result['url']);
            }
        } catch (Throwable $e) {
            log_message('error', 'Employee self-service declaration start failed: ' . $e->getMessage());
            log_message('error', $e->getTraceAsString());

            return redirect()
                ->to(url('declarations/my-declarations'))
                ->withInput()
                ->with('sError', $e->getMessage());
        }
    }

    public function show(int $packetId)
    {
        $this->permissionCheck('declarations_employee_view_own');

        try {
            return view(
                'App\Modules\Declarations\Views\employee\my_declarations\show',
                $this->selfService->packetDetailsForUser($this->currentUserId(), $packetId)
            );
        } catch (Throwable $e) {
            log_message('error', 'Employee declaration packet view failed: ' . $e->getMessage());

            return redirect()
                ->to(url('declarations/my-declarations'))
                ->with('sError', $e->getMessage());
        }
    }

    public function previewItemDocument(int $packetId, int $itemId)
    {
        $this->permissionCheck('declarations_employee_view_own');

        try {
            $this->selfService->assertCanViewPacketItemForUser($this->currentUserId(), $packetId, $itemId);

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
                log_message('error', 'Employee declaration PDF preview failed: ' . $previewError->getMessage());

                return view('App\Modules\Declarations\Views\employee\my_declarations\preview_html', array_merge(
                    $this->documentPreviewService->previewDataForPacketItem(
                        $packetId,
                        $itemId,
                        'A PDF előnézet most nem állítható elő, ezért az online kitöltésből készített nyilatkozat-előnézet látható.'
                    ),
                    [
                        'backUrl' => url('declarations/my-declarations/' . $packetId),
                    ]
                ));
            }
        } catch (Throwable $e) {
            log_message('error', 'Employee declaration item preview failed: ' . $e->getMessage());

            return redirect()
                ->to(url('declarations/my-declarations/' . $packetId))
                ->with('sError', $e->getMessage());
        }
    }

    private function currentUserId(): int
    {
        return function_exists('logged') ? (int) logged('id') : 0;
    }
}
