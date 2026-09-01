<?php

namespace App\Controllers;

use App\Controllers\AdminBaseController;

use App\Models\UserModel;
use App\Models\EmailTemplateModel;
use App\Models\NewsletterModel;
use App\Models\UserPermissionsModel;
use App\Models\UserRolesModel;
use App\Models\CreditLogsModel;
use App\Models\UserAreas;
use App\Modules\Declarations\Services\IntranetUserLinkService;

use \Hermawan\DataTables\DataTable;
use Config\Services;

class Users extends AdminBaseController
{

	public $title = 'Felhasználók kezelése';
	public $menu = 'users';


	public function index()
	{
		$this->permissionCheck('users_list');
		$users = (new UserModel)->findAll();
		return view('admin/users/list', compact('users'));
	}

	public function teszt()
	{
		$this->permissionCheck('users_list');
		$users = (new UserModel)->findAll();
		return view('admin/users/teszt', compact('users'));
	}

	public function loadUsers()
	{
		$builder = (new UserModel)
			->select('
            users.id,
			users.antraid,
            users.name,
			users.entrydate,
			users.valid_from,
            users.jobtitle,
            users.department,
            users.office,
			users.division,
            users.wactivity,
            users.email,
            users.phone,
            users.whoiswho,
			users.privacy,
			users.datamanagement,
			users.payrollEmail,
			users.forImages,
			users.employeeofthemonth,
			users.privacyreviewon,
			users.privacydate,
			users.last_login,
			users.status,
			o.name AS office_name,
			d.name AS division_name,

           GROUP_CONCAT(DISTINCT roles.title ORDER BY roles.title SEPARATOR ",") AS role_names
        ')
			->join('user_roles', 'user_roles.userid = users.id', 'left')
			->join('roles', 'roles.id = user_roles.role', 'left')
			->join('basicdata o', 'o.id = users.office', 'left')
			->join('basicdata d', 'd.id = users.division', 'left')

			->groupBy('users.id');

		$canEdit = hasPermissions('users_edit');
		$canDelete = hasPermissions('users_delete');
		$canView = hasPermissions('users_view');

		return DataTable::of($builder)
			->setSearchableColumns([
				'users.name',
				'users.email',
				'users.antraid',
				'roles.title',
			])
			->add('actions', function ($row) use ($canEdit, $canDelete, $canView) {
				
				 $canDelete = $canDelete && ($row->id !== logged('id'));
	
				$btns = [];

				if ($canView) {
					$btns[] = '<a href="' . site_url('users/view/' . $row->id) . '" title="Felhasználó megtekintése" data-toggle="tooltip" class="btn btn-sm btn-default">
                         <i class="fas fa-eye"></i>
                       </a>';
				}

				if ($canEdit) {
					$btns[] = '<a href="' . site_url('users/edit/' . $row->id) . '" title="Felhasználó szerkesztése" data-toggle="tooltip" class="btn btn-sm btn-default">
                         <i class="fas fa-edit"></i>
                       </a>';
				}


				return $btns ? implode(' ', $btns) : '';
			}, 'last' , false)
			->edit('status', function ($row) {
				return ((int) $row->status === 1)
					? '<span class="badge bg-success">Aktív</span>'
					: '<span class="badge bg-danger">Inaktív</span>';
			})
			->edit('division', function ($row) {
				return esc($row->division_name ?? '');
			})
			->edit('office', function ($row) {
				return esc($row->office_name ?? '');
			})
			->edit('employeeofthemonth', function ($row) {
				return ((int) $row->employeeofthemonth === 1)
					? '<span class="badge bg-success">Igen</span>'
					: '<span class="badge bg-danger">Nem</span>';
			})
			->edit('forImages', function ($row) {
				return ((int) $row->forImages === 1)
					? '<span class="badge bg-success">Igen</span>'
					: '<span class="badge bg-danger">Nem</span>';
			})
			->edit('payrollEmail', function ($row) {
				return ((int) $row->payrollEmail === 1)
					? '<span class="badge bg-success">Igen</span>'
					: '<span class="badge bg-danger">Nem</span>';
			})
			->edit('datamanagement', function ($row) {
				return ((int) $row->datamanagement === 1)
					? '<span class="badge bg-success">Igen</span>'
					: '<span class="badge bg-danger">Nem</span>';
			})
			->edit('whoiswho', function ($row) {
				return ((int) $row->privacy === 1)
					? '<span class="badge bg-success">Igen</span>'
					: '<span class="badge bg-danger">Nem</span>';
			})
			->edit('whoiswho', function ($row) {
				return ((int) $row->whoiswho === 1)
					? '<span class="badge bg-success">Igen</span>'
					: '<span class="badge bg-danger">Nem</span>';
			})
			->add('roles', function ($row) {
				$rolesStr = (string) ($row->role_names ?? '');
				$roles = array_filter(array_map('trim', explode(',', $rolesStr)));
				if (empty($roles)) {
					return '<span class="text-muted">—</span>';
				}
				$badges = array_map(function ($r) {
					return '<span class="badge bg-info text-dark me-1">' . esc($r) . '</span>';
				}, $roles);
				return implode(' ', $badges);
			})
			->add('avatar_cell', function ($row) {
				$imgUrl = userProfile($row->id);
				$img = '<a href="' . esc($imgUrl) . '" data-lightbox="photos' . (int) $row->id . '" data-title="' . (int) $row->id . ' - ' . esc($row->name) . '">
                        <img src="' . esc($imgUrl) . '" alt="' . esc($row->name) . '" class="img-avatar" width="64">
                    </a>';
				$profileUrl = site_url('users/view/' . (int) $row->id);

				return '
                <div class="d-flex flex-column align-items-center text-center">
                    ' . $img . '
                </div>
            ';
			})
			->toJson(true);
	}

	public function add()
	{
		$this->permissionCheck('users_add');
		return view('admin/users/add');
	}

	public function save()
	{
		$this->permissionCheck('users_add');
		postAllowed();



		$id = (new UserModel)->create([
			'lastname' => post('lastname'),
			'firstname' => post('firstname'),
			'name' => post('lastname') . ' ' . post('firstname'),
			'email' => post('email'),
			'phone' => post('phone'),
			'antraid' => post('antraid'),
			'secondary_antraid' => post('secondary_antraid'),

			'entrydate' => post('entrydate'),
			'valid_from' => post('entrydate'),
			'division' => post('division'),
			'status' => (int) post('status'),
			'jobtitle' => post('jobtitle'),
			'wactivity' => post('activity'),
			'manager' => post('manager'),
			'deputy' => post('deputy'),
			'office' => post('office'),
			'whoiswho' => post('whoiswho'),
			'news' => '1',
			'newsletter_email' => post('email'),
			'password' => hash("sha256", post('password')),
		]);

		$linkedDeclarationPerson = $this->tryLinkDeclarationPerson((int) $id);

		$newsletter = (new NewsletterModel)->create([
			'userid' => $id,
			'type' => 3,
			'status' => 1,
			'email' => post('email'),
		]);

		if (!empty(post('department'))) {
			$Departments = [];
			foreach (post('department') as $department) {
				array_push($Departments, [
					'user_id' => $id,
					'area_id' => $department,
				]);
			}

			(new UserAreas())->createBatch($Departments);
		}

		if (!empty(post('role'))) {
			$Roles = [];
			foreach (post('role') as $role) {
				array_push($Roles, [
					'userid' => $id,
					'role' => $role,
				]);
			}

			(new UserRolesModel)->createBatch($Roles);
		}


		$destDir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'users';
		if (!is_dir($destDir)) {
			@mkdir($destDir, 0775, true);
		}

		if (!empty($_FILES['image']['name'])) {
			$img = $this->request->getFile('image');

			if ($img->isValid() && !$img->hasMoved()) {
				$tmpPath = $img->getTempName();
				$target = $destDir . DIRECTORY_SEPARATOR . $id . '.webp';
				$quality = 70;

				$saved = false;
				try {
					// CI4 Image Service: EXIF reorient + mentés WebP-be (ha támogatott)
					$image = Services::image(); // handler: GD vagy Imagick a configtól függően
					$saved = $image
						->withFile($tmpPath)
						->reorient()                 // <-- EXIF Orientation javítása
						->save($target, $quality);   // kiterjesztés alapján WebP-t ír, ha van támogatás
				} catch (\Throwable $e) {
					$saved = false;
				}

				if (!$saved) {
					// Fallback: közvetlen Imagick, ha a handler nem tudott WebP-t
					$ok = false;
					if (class_exists('Imagick')) {
						try {
							$im = new \Imagick($tmpPath);
							if (method_exists($im, 'autoOrientImage')) {
								$im->autoOrientImage(); // EXIF javítás
							}
							$im->setImageFormat('webp');
							$im->setImageCompressionQuality($quality);
							if (method_exists($im, 'stripImage')) {
								$im->stripImage();     // metaadatok eldobása
							}
							$ok = $im->writeImage($target);
							$im->clear();
							$im->destroy();
						} catch (\Throwable $e) {
							$ok = false;
						}
					}
					$saved = $ok;
				}

				(new UserModel)->update($id, ['img_type' => $saved ? 'webp' : null]);
			} else {
				(new UserModel)->update($id, ['img_type' => null]);
			}
		} else {
			(new UserModel)->update($id, ['img_type' => null]);
		}

		$data = getEmailShortCodes();
		$data['name'] = post('lastname') . ' ' . post('firstname');
		$data['email'] = post('email');
		$data['password'] = post('password');


		$parser = \Config\Services::parser();

		$template = (new EmailTemplateModel)->getByWhere([
			'code' => 'user_register'
		])[0]->data;

		$html = $parser->setData($data)->renderString($template);


		$email = \Config\Services::email();

		$email->setFrom(setting('company_email'), setting('company_name'));

		$email->setTo(post('email'));


		$email->setSubject('Belépési adatok | ' . setting('company_name'));
		$email->setMessage($html);

		if (!$email->send()) {
			model('App\Models\EmailLogsModel')->add(setting('company_email'), post('email'), 'Belépési adatok | ' . setting('company_name'), strip_tags($html), 0);
			die(var_dump($email->printDebugger(['headers'])));
			return redirect()->to('users/add')->with('alertError', "Unable to Send Email");

		}

		model('App\Models\EmailLogsModel')->add(setting('company_email'), post('email'), 'Belépési adatok | ' . setting('company_name'), strip_tags($html), 1);
		model('App\Models\ActivityLogModel')->add('Új felhasználó ' . post('lastname') . ' ' . post('firstname') . ' (' . $id . ') sikeresen létrehozva ' . logged('name') . ' (' . logged('id') . ') által.');

		$connectionNotice = $linkedDeclarationPerson
			? [
				'type' => 'success',
				'message' => 'Az intranet felhasználót az ANTRA-azonosító alapján automatikusan összekapcsoltuk a nyilatkozati személlyel.',
			]
			: [
				'type' => 'warning',
				'message' => 'Az intranet felhasználó elkészült, de még nincs nyilatkozati személyhez kapcsolva. Ellenőrizd az alábbi találatokat, és szükség esetén végezd el a kapcsolást.',
			];

		return redirect()
			->to(url('users/view/' . (int) $id) . '#declaration-connection')
			->with('notifySuccess', 'Új felhasználó sikeresen létrehozva.')
			->with('declarationConnectionNotice', $connectionNotice);

	}

	public function saveCredit($id)
	{
		$this->permissionCheck('users_edit');
		postAllowed();

		$current_credit = (new UserModel)->getRowById($id, 'credit');

		if (post('type') == 'increase') {
			$new_value = $current_credit + post('amount');
		} elseif (post('type') == 'decrease') {
			$new_value = $current_credit - post('amount');
		}

		if ((new UserModel)->update($id, ['credit' => $new_value])) {
			(new CreditLogsModel)->add($id, post('type'), $current_credit, $new_value, logged('id'));
			return redirect()->to('users/view/' . $id)->with('sSuccess', 'A felhasználó kreditje sikeresen módosítva.');
		} else {
			return redirect()->to('users/view/' . $id)->with('sError', 'Nem sikerült módosítani a felhasználó kreditjeit. Kérlek, hogy jelentsd egy IT-s kollégának.');
		}
	}

	public function edit($id)
	{

		$this->permissionCheck('users_edit');

		$user = (new UserModel)->getById($id);

		$roles = (new UserRolesModel)->getByWhere([
			'userid' => $user->id
		]);

		$_roles = array_map(function ($data) {
			return $data->role;
		}, $roles);

		$user_roles = $_roles;

		$departments = (new UserAreas())->getByWhere([
			'user_id' => $user->id
		]);

		$_departments = array_map(function ($data) {
			return $data->area_id;
		}, $departments);

		$user_departments = $_departments;
		return view('admin/users/edit', compact('user', 'user_roles', 'roles', 'user_departments'));


	}

	public function update($id)
	{

		$this->permissionCheck('users_edit');
		postAllowed();

		$userid = $id;
		$data = [
			'lastname' => post('lastname'),
			'firstname' => post('firstname'),
			'name' => post('lastname') . ' ' . post('firstname'),
			'email' => post('email'),
			'phone' => post('phone'),
			'antraid' => post('antraid'),
			'secondary_antraid' => post('secondary_antraid'),
			'entrydate' => post('entrydate'),
			'division' => post('division'),
			'status' => (int) post('status'),
			'jobtitle' => post('jobtitle'),
			'wactivity' => post('activity'),
			'manager' => post('manager'),
			'deputy' => post('deputy'),
			'office' => post('office'),
			'whoiswho' => post('whoiswho'),
			'valid_from' => post('valid_from')
		];

		$password = post('password');

		if (logged('id') == $id)
			$data['status'] = '1';

		if ($data['status'] == 0) {
			$newsletter = (new NewsletterModel)->getByAnotherId('userid', $id);
			(new NewsletterModel)->update($newsletter->id, ['status' => 0]);
		}


		if (!empty($password))
			$data['password'] = hash("sha256", $password);

		/*if(!$this->permissionCheck('user_role_select') && !empty(post('role'))) {
				  $data['role'] = post('role');
			  }*/

		// Data which will be added
		if (!empty(post('role'))) {
			$Roles = [];
			foreach (post('role') as $role) {
				if (!empty((new UserRolesModel)->getByWhere(['userid' => $id, 'role' => $role]))) {
				} else {
					array_push($Roles, [
						'userid' => $id,
						'role' => $role,
					]);
				}
			}
		}

		if (!empty($Roles))
			(new UserRolesModel)->createBatch($Roles);

		$all_roles = (new UserRolesModel)->getByWhere([
			'userid' => $id,
		]);

		if (!empty($all_roles)) {
			// Permissions which will be deleted
			foreach ($all_roles as $row) {

				if (!empty(post('role'))) {
					if (!in_array($row->role, post('role'))) {
						(new UserRolesModel)->delete($row->id);
					}
				} else {
					(new UserRolesModel)->delete($row->id);
				}
			}
		}

		// Data which will be added
		if (!empty(post('department'))) {
			$Departments = [];
			foreach (post('department') as $department) {
				if (!empty((new UserAreas())->getByWhere(['user_id' => $id, 'area_id' => $department]))) {
				} else {
					array_push($Departments, [
						'user_id' => $id,
						'area_id' => $department,
					]);
				}
			}
		}

		if (!empty($Departments))
			(new UserAreas)->createBatch($Departments);

		$all_departments = (new UserAreas)->getByWhere([
			'user_id' => $id,
		]);

		if (!empty($all_departments)) {
			// Permissions which will be deleted
			foreach ($all_departments as $row) {

				if (!empty(post('department'))) {
					if (!in_array($row->area_id, post('department'))) {
						(new UserAreas)->delete($row->id);
					}
				} else {
					(new UserAreas)->delete($row->id);
				}
			}
		}

		$destDir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'users';
		if (!is_dir($destDir)) {
			@mkdir($destDir, 0775, true);
		}

		$img = $this->request->getFile('image');

		if (!empty($_FILES['image']['name']) && $img && $img->isValid() && !$img->hasMoved()) {
			$srcPath = $img->getTempName();
			$mime = $img->getMimeType() ?: '';
			$target = $destDir . DIRECTORY_SEPARATOR . $id . '.webp';
			$quality = 70;

			$src = null;
			switch ($mime) {
				case 'image/jpeg':
				case 'image/pjpeg':
					$src = @imagecreatefromjpeg($srcPath);
					break;
				case 'image/png':
					$src = @imagecreatefrompng($srcPath);
					if ($src !== false) {
						@imagepalettetotruecolor($src);
						@imagesavealpha($src, true);
					}
					break;
				case 'image/gif':
					$src = @imagecreatefromgif($srcPath);
					if ($src !== false) {
						@imagepalettetotruecolor($src);
						@imagesavealpha($src, true);
					}
					break;
				case 'image/webp':
					$src = @imagecreatefromwebp($srcPath);
					break;
				default:
					$src = @imagecreatefromstring(@file_get_contents($srcPath));
			}

			if ($src === false || $src === null) {
				return redirect()->back()->with('notifyError', 'A képfájl nem betölthető.');
			}

			if (function_exists('exif_read_data')) {
				$exif = @exif_read_data($srcPath);
				if (!empty($exif['Orientation'])) {
					$o = (int) $exif['Orientation'];
					if ($o === 2 && function_exists('imageflip')) {
						imageflip($src, IMG_FLIP_HORIZONTAL);
					} elseif ($o === 3) {
						if ($rot = @imagerotate($src, 180, 0)) {
							imagedestroy($src);
							$src = $rot;
						}
					} elseif ($o === 4 && function_exists('imageflip')) {
						imageflip($src, IMG_FLIP_VERTICAL);
					} elseif ($o === 5) {
						if ($rot = @imagerotate($src, 90, 0)) {
							imagedestroy($src);
							$src = $rot;
						}
						if (function_exists('imageflip'))
							imageflip($src, IMG_FLIP_HORIZONTAL);
					} elseif ($o === 6) {
						if ($rot = @imagerotate($src, -90, 0)) {
							imagedestroy($src);
							$src = $rot;
						}
					} elseif ($o === 7) {
						if ($rot = @imagerotate($src, -90, 0)) {
							imagedestroy($src);
							$src = $rot;
						}
						if (function_exists('imageflip'))
							imageflip($src, IMG_FLIP_HORIZONTAL);
					} elseif ($o === 8) {
						if ($rot = @imagerotate($src, 90, 0)) {
							imagedestroy($src);
							$src = $rot;
						}
					}
				}
			}

			$ok = false;
			if (function_exists('imagewebp')) {
				@imagesavealpha($src, true);
				$ok = @imagewebp($src, $target, $quality);
				@imagedestroy($src);
			}

			if (!$ok && class_exists('Imagick')) {
				try {
					$im = new \Imagick($srcPath);
					if (method_exists($im, 'autoOrientImage')) {
						$im->autoOrientImage();
					}
					$im->setImageFormat('webp');
					$im->setImageCompressionQuality($quality);
					if (method_exists($im, 'stripImage')) {
						$im->stripImage();
					}
					$ok = $im->writeImage($target);
					$im->clear();
					$im->destroy();
				} catch (\Throwable $e) {
					$ok = false;
				}
			}

			if (!$ok) {
				return redirect()->back()->with('notifyError', 'Szerver hiba: WebP mentés sikertelen.');
			}

			foreach (glob($destDir . DIRECTORY_SEPARATOR . $id . '.*') as $path) {
				if ($path !== $target) {
					@unlink($path);
				}
			}

			$data['img_type'] = 'webp';			
		}

		(new UserModel)->update($id, $data);
		$this->tryLinkDeclarationPerson((int) $id);

		model('App\Models\ActivityLogModel')->add($data['name'] . ' (' . $userid . ') sikeresen modosítva ' . logged('name') . ' (' . logged('id') . ') által.');

		return redirect()->to('users')->with('notifySuccess', $data['name'] . ' (' . $userid . ') sikeresen módosítva.');

	}

	public function view($id)
	{

		$this->permissionCheck('users_view');

		$user = (new UserModel)->getById($id);
		$user->activity = model('App\Models\ActivityLogModel')->getByWhere([
			'user' => $id
		], ['order' => ['id', 'desc']]);

		$user->credit_logs = model('App\Models\CreditLogsModel')->getByWhere([
			'userid' => $id
		], ['order' => ['id', 'desc']]);


		$user->requests = model('App\Models\ShopRequestModel')->getByWhere([
			'userid' => $id
		], ['order' => ['id', 'desc']]);

		try {
			$declarationConnection = (new IntranetUserLinkService())->connectionForUser((int) $id);
		} catch (\Throwable $e) {
			log_message('error', 'Declaration user connection could not be loaded: ' . $e->getMessage());
			$declarationConnection = [
				'user' => null,
				'user_active' => false,
				'linked_person' => null,
				'candidates' => [],
				'error' => 'A nyilatkozati kapcsolat adatai jelenleg nem tölthetők be.',
			];
		}

		return view('admin/users/view', compact('user', 'declarationConnection'));

	}

	public function linkDeclarationPerson($id)
	{
		$this->permissionCheck('users_edit');
		postAllowed();

		$userId = (int) $id;
		$personId = (int) post('person_id');

		try {
			if (!(new UserModel())->getById($userId)) {
				throw new \RuntimeException('A felhasználó nem található.');
			}

			if ($personId <= 0) {
				throw new \RuntimeException('Nincs kiválasztva nyilatkozati személy.');
			}

			(new IntranetUserLinkService())->linkPersonForUser($userId, $personId);
			model('App\Models\ActivityLogModel')->add(
				'A(z) ' . $userId . ' azonosítójú intranet felhasználó összekapcsolva a(z) '
				. $personId . ' azonosítójú nyilatkozati személlyel ' . logged('name')
				. ' (' . logged('id') . ') által.'
			);

			return redirect()
				->to(url('users/view/' . $userId) . '#declaration-connection')
				->with('notifySuccess', 'A nyilatkozati személy kapcsolása sikerült.');
		} catch (\Throwable $e) {
			log_message('error', 'Declaration person linking failed: ' . $e->getMessage());

			return redirect()
				->to(url('users/view/' . $userId) . '#declaration-connection')
				->with('notifyError', $e->getMessage());
		}
	}

	private function tryLinkDeclarationPerson(int $userId): ?object
	{
		if ($userId <= 0) {
			return null;
		}

		try {
			return (new IntranetUserLinkService())->linkMatchingPersonForUser($userId);
		} catch (\Throwable $e) {
			log_message('error', 'Automatic declaration person linking failed: ' . $e->getMessage());

			return null;
		}
	}

	public function updateuserperm($id)
	{
		/*	postAllowed();

			   $userid = $id;
			   // Data which will be added
			   if (!empty(post('permission'))) {
				   $Data = [];
				   foreach (post('permission') as $permission) {
					   if( !empty((new UserPermissionsModel)->getByWhere([ 'userid' => $id, 'permission' => $permission ])) ){ }else{
						   array_push($Data, [
							   'userid' => $id,
							   'permission' => $permission,
						   ]);
					   }
				   }
			   }

			   if(!empty($Data))
				   (new UserPermissionsModel)->createBatch($Data);		

			   $all_permissions = (new UserPermissionsModel)->getByWhere([
				   'userid' =>  $userid
			   ]);

			   if(!empty($all_permissions)){
				   // Permissions which will be deleted
				   foreach ($all_permissions as $data) {

					   if (!empty(post('permission'))){
						   if(!in_array($data->permission, post('permission'))){
							   (new UserPermissionsModel)->delete($data->id);
						   }
					   } else {
						   (new RolePermissionModel)->delete($data->id);
					   }

				   }
			   }	
			   return redirect()->back()->with('notifySuccess', 'Jogosultságok sikeresen szerkesztve.');*/
	}


	public function check()
	{
		$email = !empty(get('email')) ? get('email') : false;
		$antraid = !empty(get('antraid')) ? get('antraid') : false;
		$valid_from = !empty(get('valid_from')) ? get('valid_from') : false;

		$notId = !empty(get('notId')) ? get('notId') : 0;

		if ($email)
			$exists = count((new UserModel)->getByWhere([
				'email' => $email,
				'id !=' => $notId,
			])) > 0 ? true : false;

		if ($antraid)
			$exists = count((new UserModel)->getByWhere([
				'antraid' => $antraid,
				'id !=' => $notId,
			])) > 0 ? true : false;

		if ($valid_from)
			$exists = count((new UserModel)->getByWhere([
				'valid_from <=' => $valid_from,
				'id' => $notId,
			])) > 0 ? false : true;

		echo $exists ? 'false' : 'true';
	}

	public function change_status($id)
	{
		$this->permissionCheck('users_edit');
		(new UserModel)->update($id, ['status' => get('status') == 'true' ? 1 : 0]);

		//$newsletter = (new NewsletterModel)->getByWhere(['userid' => $id])[0];
		//(new NewsletterModel)->update($newsletter->id, ['status' => get('status') == 'true' ? 1 : 0 ]);
		echo 'done';
	}

	public function editprivacyreviewon($id)
	{
		$this->permissionCheck('users_edit');
		postAllowed();

		if (
			!$this->validate([
				'date' => 'required'
			])
		) {
			return redirect()->to('users')->with('sError', 'A dátum mező kitöltése kötelező!');
		}

		$data = [
			'privacyreviewon' => post('date')
		];

		(new UserModel)->update($id, $data);

		return redirect()->to('users')->with('sSuccess', 'A felhasználó sikeresen szerkesztve.');
	}

	public function delete($id)
	{

		$this->permissionCheck('users_delete');

		if ($id !== 1 && $id != logged('id')) {
		} else {
			return redirect()->to('users');
		}

		if (!empty((new NewsletterModel)->getByWhere(['userid' => $id])[0])) {
			$newsletter = (new NewsletterModel)->getByWhere(['userid' => $id])[0];
			(new NewsletterModel)->delete($newsletter->id);
		}

		$permissions = (new UserRolesModel)->getByWhere(['userid' => $id]);

		foreach ($permissions as $permission) {
			(new UserRolesModel)->delete($permission->id);
		}


		(new UserModel)->delete($id);

		model('App\Models\ActivityLogModel')->add("User #$id Deleted by User:" . logged('name'));

		return redirect()->to('users')->with('notifySuccess', 'User has been Deleted Successfully');

	}

	public function updatePrivacy()
	{

		postAllowed();

		if (checkUserRole(3)) {
			// validate
			if (
				!$this->validate([
					'datamanagement' => 'required',
					'payrollEmail' => 'required',
					'forImages' => 'required',
					'employeeofthemonth' => 'required',
				], [
					'datamanagement' => [
						'required' => 'Az Intranet használatához az adatvédelmi tájékoztatóban foglaltak megismerése (a tájékoztató megnyitása) és a megismerés tényének checkboxban történő jelzése kötelező! Kérem, hogy jelöld be a checkboxot, vagy ha ezt nem szeretnéd, akkor zárd be az Intranet oldalát.'
					],
					'payrollEmail' => [
						'required' => 'Az egyiket kötelező kitölteni.'
					],
					'forImages' => [
						'required' => 'Az egyiket kötelező kitölteni.'
					],
					'employeeofthemonth' => [
						'required' => 'Az egyiket kötelező kitölteni.'
					],
				])
			) {
				return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
			}
		} else if (checkUserRole(13) || checkUserRole(14) || checkUserRole(15) || checkUserRole(16) || checkUserRole(17) || checkUserRole(18)) {
			if (
				!$this->validate([
					'datamanagement' => 'required',
				], [
					'datamanagement' => [
						'required' => 'Az Intranet használatához az adatvédelmi tájékoztatóban foglaltak megismerése (a tájékoztató megnyitása) és a megismerés tényének checkboxban történő jelzése kötelező! Kérem, hogy jelöld be a checkboxot, vagy ha ezt nem szeretnéd, akkor zárd be az Intranet oldalát.'
					],
				])
			) {
				return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
			}
		} else if (checkUserRole(12) || checkUserRole(19)) {
			if (
				!$this->validate([
					'datamanagement' => 'required',
					'employeeofthemonth' => 'required',
				], [
					'datamanagement' => [
						'required' => 'Az Intranet használatához az adatvédelmi tájékoztatóban foglaltak megismerése (a tájékoztató megnyitása) és a megismerés tényének checkboxban történő jelzése kötelező! Kérem, hogy jelöld be a checkboxot, vagy ha ezt nem szeretnéd, akkor zárd be az Intranet oldalát.'
					],
					'employeeofthemonth' => [
						'required' => 'Az egyiket kötelező kitölteni.'
					],
				])
			) {
				return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
			}
		}


		$datamanagement = 0;
		$payrollEmail = 0;
		$forImages = 0;
		$employeeofthemonth = 0;

		if (post('datamanagement') == 1) {
			$datamanagement = 1;
		}

		if (post('payrollEmail') == 1) {
			$payrollEmail = 1;
		}

		if (post('forImages') == 1) {
			$forImages = 1;
		}

		if (post('employeeofthemonth') == 1) {
			$employeeofthemonth = 1;
		}

		if (checkUserRole(3)) {
			if (post('payrollEmail') == 0 || post('forImages') == 0 || post('employeeofthemonth') == 0) {
				$data = getEmailShortCodes();
				$data['antraid'] = logged('antraid');
				$data['name'] = logged('name');
				$text = '<ul>';
				if (post('payrollEmail') == 0) {
					$text .= '<li>bérjegyzék kiküldése e-mailben</li>';
				}

				if (post('forImages') == 0) {
					$text .= '<li>gyermek születési fénykép készítés</li>';
				}

				if (post('employeeofthemonth') == 0) {
					$text .= '<li>hónap dolgózói fénykép készítés</li>';
				}
				$text .= '</ul>';
				$data['data'] = $text;

				$parser = \Config\Services::parser();

				$template = (new EmailTemplateModel)->getByWhere([
					'code' => 'privacy_policy'
				])[0]->data;

				$html = $parser->setData($data)->renderString($template);





				$email = \Config\Services::email();
				$email->setMailType('html');
				$email->setHeader('MIME-Version=1.0', 'charset=utf-8');
				$email->setFrom(setting('company_email'), setting('company_name'));
				$email->setTo(setting('privacy_email'));
				$email->setBcc('intranet@miellgroup.com'); // másolatot kapjon az intranet@miellgroup.com e-mail cím

				$email->setSubject(logged('name') . ' | Adatkezelési hozzájárulás | ' . setting('company_name'));
				$email->setMessage($html);

				if (!$email->send()) {
					model('App\Models\EmailLogsModel')->add(setting('company_email'), setting('privacy_email'), logged('name') . ' | Adatkezelési hozzájárulás | ' . setting('company_name'), strip_tags($html), 0);
				}

				model('App\Models\EmailLogsModel')->add(setting('company_email'), setting('privacy_email'), logged('name') . ' | Adatkezelési hozzájárulás | ' . setting('company_name'), strip_tags($html), 1);


			}
		} else if (checkUserRole(12) || checkUserRole(19)) {
			if (post('employeeofthemonth') == 0) {
				$data = getEmailShortCodes();
				$data['antraid'] = logged('antraid');
				$data['name'] = logged('name');
				$text = '<ul>';


				if (post('employeeofthemonth') == 0) {
					$text .= '<li>hónap dolgózói fénykép készítés</li>';
				}
				$text .= '</ul>';
				$data['data'] = $text;

				$parser = \Config\Services::parser();

				$template = (new EmailTemplateModel)->getByWhere([
					'code' => 'privacy_policy'
				])[0]->data;

				$html = $parser->setData($data)->renderString($template);





				$email = \Config\Services::email();
				$email->setMailType('html');
				$email->setHeader('MIME-Version=1.0', 'charset=utf-8');
				$email->setFrom(setting('company_email'), setting('company_name'));
				$email->setTo(setting('privacy_email'));
				$email->setBcc('intranet@miellgroup.com'); // másolatot kapjon az intranet@miellgroup.com e-mail cím

				$email->setSubject(logged('name') . ' | Adatkezelési hozzájárulás | ' . setting('company_name'));
				$email->setMessage($html);

				if (!$email->send()) {
					model('App\Models\EmailLogsModel')->add(setting('company_email'), setting('privacy_email'), logged('name') . ' | Adatkezelési hozzájárulás | ' . setting('company_name'), strip_tags($html), 0);
				}

				model('App\Models\EmailLogsModel')->add(setting('company_email'), setting('privacy_email'), logged('name') . ' | Adatkezelési hozzájárulás | ' . setting('company_name'), strip_tags($html), 1);


			}
		}

		$data = [
			'privacy' => 1,
			'datamanagement' => $datamanagement,
			'payrollEmail' => $payrollEmail,
			'forImages' => $forImages,
			'employeeofthemonth' => $employeeofthemonth,
			'privacydate' => date('Y-m-d H:i:s'),
		];

		model('App\Models\UserModel')->update(logged('id'), $data);

		return redirect()->to('home');

	}

	public function importUsers()
	{
		$this->permissionCheck('users_add');

		$file = $this->request->getFile('file');

		if (!$file) {
			return $this->response->setJSON(['error' => 'Nincs fájl a requestben.'])->setStatusCode(400);
		}

		if (!$file->isValid() || $file->getClientExtension() !== 'csv') {
			return $this->response->setJSON(['error' => 'Érvénytelen fájl. Csak CSV fájl engedélyezett.'])->setStatusCode(400);
		}


		$fileDatas = [];
		$skippedRows = [];
		$i = 0;

		$userModel = new UserModel();
		$rolesModel = new UserRolesModel();
		$newsletterModel = new NewsletterModel();

		$csv = fopen($file->getTempName(), 'r');
		while (($row = fgetcsv($csv, 1000, ";")) !== false) {
			if ($i > 0) {
				$userid = $userModel->getRowByAntraID('antraid', $row[0], 'id');
				$user_email = $userModel->getRowByAnotherId('email', $row[4], 'id');


				if ($userid || $user_email) {
					$skippedRows[] = [
						'antraid' => $row[0],
						'name' => $row[1] . ' ' . $row[2],
						'email' => $row[4],
						'reason' => 'Átfedő rekord már létezik'
					];
					$i++;
					continue;
				}

				$fileDatas[] = [
					'antraid' => $row[0],
					'lastname' => $row[1],
					'firstname' => $row[2],
					'name' => $row[1] . ' ' . $row[2],
					'phone' => $row[3],
					'email' => $row[4],
					'password' => $row[5],
					'entry_date' => $row[6],
					'jobtitle' => $row[7],
					'department' => $row[8],
					'wactivity' => $row[9],
					'status' => $row[10],
					'manager' => $row[11],
					'division' => $row[12],
					'deputy' => $row[13],
					'whoiswho' => $row[14]
				];
			}
			$i++;
		}
		fclose($csv);

		foreach ($fileDatas as $row) {
			$id = $userModel->insert($row);

			$roles = $this->request->getPost('role');
			if ($roles && is_array($roles)) {
				foreach ($roles as $roleId) {
					$rolesModel->create([
						'userid' => $id,
						'role' => $roleId,
					]);
				}
			}

			copy(FCPATH . 'uploads/users/default.png', 'uploads/users/' . $id . '.png');
			$newsletterModel->create([
				'userid' => $id,
				'email' => $row['email'],
				'status' => 1,
				'type' => 3,
			]);

			(new UserModel)->updateById($id, ['password' => hash('sha256', $row['password'])]);

			$data = getEmailShortCodes();
			$data['name'] = $row['name'];
			$data['email'] = $row['email'];
			$data['password'] = $row['password'];


			$parser = \Config\Services::parser();

			$template = (new EmailTemplateModel)->getByWhere([
				'code' => 'user_register'
			])[0]->data;

			$html = $parser->setData($data)->renderString($template);
			$email = \Config\Services::email();

			$email->setFrom(setting('company_email'), setting('company_name'));
			$email->setTo($row['email']);
			$email->setBcc('adrian.dunai@miellgroup.com');


			$email->setSubject('Belépési adatok | ' . setting('company_name'));
			$email->setMessage($html);

			if (!$email->send()) {
				model('App\Models\EmailLogsModel')->add(setting('company_email'), $row['email'], 'Belépési adatok | ' . setting('company_name'), strip_tags($html), 0);
			}

			model('App\Models\EmailLogsModel')->add(setting('company_email'), $row['email'], 'Belépési adatok | ' . setting('company_name'), strip_tags($html), 1);
			model('App\Models\ActivityLogModel')->add('Új felhasználó (' . $row['name'] . ') sikeresen létrehozva ' . logged('name') . ' (' . logged('id') . ') által.');
		}

		return $this->response
			->setStatusCode(200)
			->setJSON([
				'success' => true,
				'skipped' => $skippedRows,
			]);
	}

	public function downloadSampleCSV()
	{

		$this->permissionCheck('users_add');

		$filename = "sample_users.csv";

		return $this->response
			->setHeader('Content-Type', 'text/csv; charset=utf-8')
			->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
			->setHeader('Pragma', 'no-cache')
			->setHeader('Expires', '0')
			->setBody($this->generateSampleCSV());
	}

	private function generateSampleCSV()
	{
		$output = fopen('php://temp', 'r+');

		fwrite($output, "\xEF\xBB\xBF");

		fputcsv($output, ['ANTRA azonosító', 'Vezetéknév', 'Keresztnév', 'Telefonszám', 'E-mail', 'Jelszó', 'Belépés dátuma', 'Munkakör', 'Szervezeti egység', 'Tevékenység', 'Állapot', 'Vezető', 'Cég', 'Helyettes', 'Kikicsoda'], ';');

		rewind($output);
		$csv = stream_get_contents($output);
		fclose($output);

		return $csv;
	}

}
