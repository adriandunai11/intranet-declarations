<?php

namespace App\Modules\Declarations\Controllers\Employee;

use App\Controllers\AdminBaseController;

class MyDeclarationsController extends AdminBaseController
{
    public function index()
    {
        $this->permissionCheck('declarations.employee.view_own');

        return view('App\Modules\Declarations\Views\employee\my_declarations\index');
    }
}
