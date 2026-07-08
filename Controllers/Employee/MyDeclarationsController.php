<?php

namespace App\Modules\Declarations\Controllers\Employee;

use App\Controllers\AdminBaseController;
use App\Modules\Declarations\Services\DeclarationNotificationService;
use App\Modules\Declarations\Services\EmployeeDeclarationSelfService;
use Throwable;

class MyDeclarationsController extends AdminBaseController
{
    protected EmployeeDeclarationSelfService $selfService;
    protected DeclarationNotificationService $notificationService;

    public function __construct()
    {
        $this->selfService = new EmployeeDeclarationSelfService();
        $this->notificationService = new DeclarationNotificationService();
    }

    public function index()
    {
        $this->permissionCheck('declarations.employee.view_own');

        try {
            $userId = $this->currentUserId();
            $data = $this->selfService->dashboardForUser($userId);

            return view('App\Modules\Declarations\Views\employee\my_declarations\index', $data);
        } catch (Throwable $e) {
            log_message('error', 'Employee declarations dashboard failed: ' . $e->getMessage());

            return view('App\Modules\Declarations\Views\employee\my_declarations\index', [
                'person' => null,
                'relations' => [],
                'templates' => [],
                'packets' => [],
                'defaultTaxYear' => (int) date('Y') + 1,
                'pageError' => $e->getMessage(),
            ]);
        }
    }

    public function start()
    {
        $this->permissionCheck('declarations.employee.view_own');
        postAllowed();

        try {
            $result = $this->selfService->startForEmployee(
                $this->currentUserId(),
                (int) $this->request->getPost('relation_id'),
                (int) $this->request->getPost('tax_year'),
                $this->request->getPost('template_ids') ?? []
            );

            try {
                $this->notificationService->notifyEmployeeSelfServiceInvitation(
                    (int) $result['packet_id'],
                    (string) $result['url']
                );

                return redirect()
                    ->to(url('my-declarations'))
                    ->with('sSuccess', 'A nyilatkozat kitöltési link elkészült és e-mailben kiküldtük.');
            } catch (Throwable $mailError) {
                log_message('error', 'Employee self-service invitation mail failed: ' . $mailError->getMessage());

                return redirect()
                    ->to(url('my-declarations'))
                    ->with('sError', 'A csomag elkészült, de az e-mail küldés nem sikerült. Ideiglenes kitöltési link: ' . (string) $result['url']);
            }
        } catch (Throwable $e) {
            log_message('error', 'Employee self-service declaration start failed: ' . $e->getMessage());
            log_message('error', $e->getTraceAsString());

            return redirect()
                ->to(url('my-declarations'))
                ->withInput()
                ->with('sError', $e->getMessage());
        }
    }

    private function currentUserId(): int
    {
        return function_exists('logged') ? (int) logged('id') : 0;
    }
}
