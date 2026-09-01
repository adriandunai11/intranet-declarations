<?= $this->extend('admin/layout/default') ?>
<?= $this->section('content') ?>

<?php
$declarationConnection = is_array($declarationConnection ?? null) ? $declarationConnection : [];
$linkedDeclarationPerson = $declarationConnection['linked_person'] ?? null;
$declarationCandidates = is_array($declarationConnection['candidates'] ?? null)
	? $declarationConnection['candidates']
	: [];
$declarationUserActive = (bool) ($declarationConnection['user_active'] ?? false);
$declarationConnectionError = trim((string) ($declarationConnection['error'] ?? ''));
$declarationConnectionNotice = session()->getFlashdata('declarationConnectionNotice');
$declarationConnectionNotice = is_array($declarationConnectionNotice) ? $declarationConnectionNotice : null;
?>

<!-- Content Header (Page header) -->
<section class="content-header">
	<div class="container-fluid">
		<div class="row mb-2">
			<div class="col-sm-6">
				<h1><?php echo $user->name . ' (' . $user->antraid . ')' ?></h1>
			</div>
			<div class="col-sm-6">
				<ol class="breadcrumb float-sm-right">
					<li class="breadcrumb-item"><a href="#"><?php echo lang('App.home') ?></a></li>
					<li class="breadcrumb-item"><a
							href="<?php echo url('/users') ?>"><?php echo lang('App.users') ?></a></li>
					<li class="breadcrumb-item active"><?php echo $user->name . ' (' . $user->antraid . ')' ?></li>
				</ol>
			</div>
		</div>
	</div><!-- /.container-fluid -->
</section>
<!-- Main content -->

<!-- Main content -->
<section class="content">
	<div class="row">
		<div class="col-12">
			<!-- Custom Tabs -->
			<div class="card">
				<div class="card-header d-flex p-0">
					<h3 class="card-title p-3"><?php echo lang('App.view_user') ?></h3>
					<ul class="nav nav-pills ml-auto p-2">

						<li class="nav-item active"><a class="nav-link active" href="#tab_1"
								data-toggle="tab"><?php echo lang('App.overview') ?></a></li>
						<li class="nav-item"><a class="nav-link" href="#tab_4"
								data-toggle="tab">Nyilatkozati kapcsolat</a></li>
						<li class="nav-item"><a class="nav-link" href="#tab_2" data-toggle="tab">MIELL Coin</a></li>

						<li class="nav-item"><a class="nav-link" href="#tab_3"
								data-toggle="tab"><?php echo lang('App.activity') ?></a></li>
						<?php if (hasPermissions('users_edit')): ?>
							<li class="nav-item"><a class="nav-link"
									href="<?php echo url('users/edit/' . $user->id) ?>"><?php echo lang('App.edit_user') ?></a>
							</li>
						<?php endif ?>
					</ul>
				</div><!-- /.card-header -->
				<div class="card-body">
					<div class="tab-content">
						<div class="tab-pane active" id="tab_1">
							<div class="row">
								<div class="col-sm-2" style="">
									<br>
									<img src="<?php echo userProfile($user->id) ?>" width="150"
										class="profile-user-img img-responsive" alt="">
									<br>
								</div>
								<div class="col-sm-10" style="padding-left: 50px;">
									<table class="table table-bordered table-striped">
										<tbody>
											<tr>
												<td width="160"><strong><?php echo lang('App.user_name') ?></strong>:
												</td>
												<td><?php echo $user->name ?></td>
											</tr>
											<tr>
												<td><strong><?php echo lang('App.user_email') ?></strong>:</td>
												<td><?php echo $user->email ?></td>
											</tr>
											<tr>
												<td><strong><?php echo lang('App.user_contact') ?></strong>:</td>
												<td><?php echo $user->phone ?></td>
											</tr>
											<tr>
												<td><strong><?php echo lang('App.user_last_login') ?></strong>:</td>
												<td><?php echo ($user->last_login != '0000-00-00 00:00:00') ? date(setting('datetime_format'), strtotime($user->last_login)) : 'No Record' ?>
												</td>
											</tr>
											<tr>
												<td><strong><?php echo lang('App.user_role') ?>(ek)</strong>:</td>
												<?php
												$getUserRoles = model('App\Models\UserRolesModel')->getByWhere(['userid' => $user->id]);
												echo '<td>';
												echo '<ul>';
												foreach ($getUserRoles as $row) {
													$getUserRoles = model('App\Models\RoleModel')->getByWhere(['id' => $row->role]);
													foreach ($getUserRoles as $data) {
														echo '<li>' . $data->title . '</li>';
													}
												}
												echo '</ul>';
												echo '</td>';
												?>
											</tr>
											<tr>
												<td><strong>Belépés dátuma</strong>:</td>
												<td><?php echo $user->entrydate ?></td>
											</tr>
											<tr>
												<td><strong>Iroda</strong>:</td>
												<td><?php echo model('App\Models\BasicdataModel')->getRowById($user->office, 'name')  ?></td>
											</tr>
											<tr>
												<td><strong>Cég (divízió)</strong>:</td>
												<td><?php echo model('App\Models\BasicdataModel')->getRowById($user->division, 'name')  ?></td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
						</div>
						<div class="tab-pane" id="tab_4">
							<div id="declaration-connection" class="p-3">
								<h4>Nyilatkozati kapcsolat</h4>
								<p class="text-muted">
									Ez a kapcsolat teszi lehetővé, hogy a felhasználó a saját nyilatkozatait az intraneten elérje és elindítsa.
								</p>

								<?php if ($declarationConnectionNotice): ?>
									<?php $noticeType = ($declarationConnectionNotice['type'] ?? '') === 'success' ? 'success' : 'warning'; ?>
									<div class="alert alert-<?php echo esc($noticeType) ?>">
										<?php echo esc($declarationConnectionNotice['message'] ?? '') ?>
									</div>
								<?php endif; ?>

								<?php if ($declarationConnectionError !== ''): ?>
									<div class="alert alert-danger mb-0"><?php echo esc($declarationConnectionError) ?></div>
								<?php elseif ($linkedDeclarationPerson): ?>
									<div class="alert alert-success">
										<i class="fas fa-link pr-1"></i> A felhasználó nyilatkozati személyhez van kapcsolva.
									</div>
									<table class="table table-bordered mb-0">
										<tbody>
											<tr>
												<th width="220">Nyilatkozati személy</th>
												<td><?php echo esc($linkedDeclarationPerson->fullName()) ?></td>
											</tr>
											<tr>
												<th>ANTRA-azonosító</th>
												<td><?php echo esc($linkedDeclarationPerson->antra_id ?: '-') ?></td>
											</tr>
											<tr>
												<th>Nyilatkozati e-mail-cím</th>
												<td><?php echo esc($linkedDeclarationPerson->email ?: '-') ?></td>
											</tr>
										</tbody>
									</table>
								<?php elseif ($declarationCandidates !== []): ?>
									<?php if (!$declarationUserActive): ?>
										<div class="alert alert-secondary">
											Inaktív intranet felhasználó nem kapcsolható nyilatkozati személyhez.
										</div>
									<?php endif; ?>
									<div class="alert alert-warning">
										A felhasználó még nincs összekapcsolva. Ellenőrizd az egyező adatokat, majd válaszd ki a megfelelő nyilatkozati személyt.
									</div>
									<div class="list-group">
										<?php foreach ($declarationCandidates as $candidate): ?>
											<div class="list-group-item">
												<div class="d-flex flex-wrap justify-content-between align-items-start">
													<div class="pr-3 mb-2">
														<strong><?php echo esc($candidate['name']) ?></strong>
														<div class="text-muted small">
															ANTRA: <?php echo esc($candidate['antra_id'] ?: '-') ?>
															· E-mail: <?php echo esc($candidate['email'] ?: '-') ?>
														</div>
														<div class="text-muted small"><?php echo esc($candidate['reason']) ?></div>
													</div>

													<?php if (!empty($candidate['can_link']) && hasPermissions('users_edit')): ?>
														<?php echo form_open('users/linkDeclarationPerson/' . (int) $user->id) ?>
															<?php echo csrf_field() ?>
															<input type="hidden" name="person_id" value="<?php echo (int) $candidate['id'] ?>">
															<button type="submit" class="btn btn-primary btn-sm">
																<i class="fas fa-link pr-1"></i> Kapcsolás
															</button>
														<?php echo form_close() ?>
													<?php elseif (!empty($candidate['linked_user_id'])): ?>
														<span class="badge badge-secondary">
															Másik felhasználóhoz kapcsolva
														</span>
													<?php endif; ?>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								<?php else: ?>
									<div class="alert alert-info mb-0">
										Nem található azonos ANTRA-azonosítójú vagy e-mail-című nyilatkozati személy.
										Ellenőrizd az ANTRA-azonosítót. A kapcsolat akkor hozható létre, amikor a toborzó már létrehozta a személy nyilatkozatcsomagját.
									</div>
								<?php endif; ?>
							</div>
						</div>
						<div class="tab-pane" id="tab_2">
							<div class="row">
								<?php echo form_open_multipart('users/saveCredit/' . $user->id, ['class' => 'form-validate']); ?>

								<div class="col-md-12" style="">
									A felhasználó jelenlegi egyenlege: <?php echo $user->credit ?> MIELL Coin
									<div class="form-group">
										<label for="type" class="required">Típus</label>
										<select name="type" id="type" required class="form-control">
											<option value="increase">Növelés</option>
											<option <?php echo ($user->credit <= 0) ? 'disabled' : '' ?>
												value="decrease">Csökkentés</option>
										</select>
									</div>
									<div class="form-group">
										<label for="amount" class="required">Összeg</label>
										<input type="number" id="amount" name="amount" min="1" class="form-control"
											required value="" placeholder="Add meg az összeget">
									</div>
									<div class="form-group">
										<button type="submit" class="form-control btn btn-success">Mentés</button>
									</div>
									<p id="credit-info"></p>
								</div>
								<?php echo form_close() ?>
								<div class="col-md-12" style="">
									<h3>MIELL Coin változás</h3>
									<table id="dataTable1" class="table table-bordered table-striped">
										<thead>
											<tr>
												<th>ID</th>
												<th>Felhasználó</th>
												<th>Típus</th>
												<th>Régi érték</th>
												<th>Új érték</th>
												<th>Létrehozva</th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ($user->credit_logs as $row): ?>
												<tr>
													<td><?php echo $row->id ?></td>
													<td><?php echo model('App\Models\UserModel')->getRowById($row->userid, 'name') . ' (' . model('App\Models\UserModel')->getRowById($row->userid, 'antraid') . ')' ?>
													</td>
													<td><?php echo ($row->type == "increase") ? '<span class="badge bg-success">jóváírás</span>' : '<span class="badge bg-danger">terhelés</span>' ?>
													</td>
													<td><?php echo number_format($row->old_value, 0, '.', ' ') ?></td>
													<td><?php echo number_format($row->new_value, 0, '.', ' ') ?></td>
													<td><?php echo model('App\Models\UserModel')->getRowById($row->creator, 'name') . ' (' . model('App\Models\UserModel')->getRowById($row->creator, 'antraid') . ')<br>' . $row->created_at ?>
													</td>

												</tr>
											<?php endforeach ?>
										</tbody>
									</table>
								</div>
								<div class="col-md-12" style="">
									<h3>Igénylések</h3>
									<table id="dataTable2" class="table table-bordered table-striped">
										<thead>
											<tr>
												<th>Igénylés AZ</th>
												<th>Felhasználó</th>
												<th>Termék</th>
												<th>Igénylés dátuma</th>
												<th>Megigénylés dátuma</th>
												<th>Kiadás dátuma</th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ($user->requests as $row): ?>
												<tr>
													<td><?php echo $row->request_number ?></td>
													<td><?php echo model('App\Models\UserModel')->getRowById($row->userid, 'name') . ' (' . model('App\Models\UserModel')->getRowById($row->userid, 'antraid') . ')' ?>
													</td>
													<td><?php echo model('App\Models\ShopProductsModel')->getRowById($row->product_id, 'name') . ' (' . $row->product_id . ')' ?>
													</td>
													<td><?php echo $row->request_date ?></td>
													<td><?php echo $row->requested_date ?></td>
													<td><?php echo $row->delivery_date ?></td>
												</tr>
											<?php endforeach ?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
						<!-- /.tab-pane -->
						<div class="tab-pane" id="tab_3">
							<table id="dataTable3" class="table table-bordered table-striped">
								<thead>
									<tr>
										<th><?php echo lang('App.id') ?></th>
										<th><?php echo lang('App.activity_ip_address') ?></th>
										<th><?php echo lang('App.activity_message') ?></th>
										<th><?php echo lang('App.activity_datetime') ?></th>
										<th><?php echo lang('App.action') ?></th>
									</tr>
								</thead>
								<tbody>

									<?php foreach ($user->activity as $row): ?>
										<tr>
											<td width="60"><?php echo $row->id ?></td>
											<td><?php echo !empty($row->ip_address) ? '<a href="' . url('activity_logs/index?ip=' . urlencode($row->ip_address)) . '">' . $row->ip_address . '</a>' : 'N.A' ?>
											</td>
											<td>
												<a
													href="<?php echo url('activity_logs/view/' . $row->id) ?>"><?php echo $row->title ?></a>
											</td>
											<td><?php echo date('d M, Y', strtotime($row->created_at)) ?></td>
											<td>
												<a href="<?php echo url('activity_logs/view/' . $row->id) ?>"
													class="btn btn-sm btn-default" title="View Activity"
													data-toggle="tooltip"><i class="fa fa-eye"></i></a>
											</td>
										</tr>
									<?php endforeach ?>

								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>

<?= $this->endSection() ?>
<?= $this->section('js') ?>

<script>


	$(document).ready(function () {
		if (window.location.hash === '#declaration-connection') {
			$('a[href="#tab_4"]').tab('show');
		}

		$("#amount, #type").on("change keypress input", function () {
			var amount = addCommas($("#amount").val());
			var type = $("#type").val();
			var current_credit = parseInt(<?php echo $user->credit ?>);
			var new_credit = parseInt(0);

			if (type == 'increase') {
				type = '<span class="text-success">jóváírásra</span>';
				new_credit = current_credit + parseInt($("#amount").val());

			} else if (type == 'decrease') {
				type = '<span class="text-danger">levonásra</span>';
				new_credit = current_credit - parseInt($("#amount").val());

			}

			$("#credit-info").html('A mentés gomb megnyomása után a felhasználónak <span class="text-success">' + amount + ' MIELL Coin</span> kerül ' + type + '. Így az új egyenleg: <span class="text-success">' + addCommas(new_credit) + ' MIELL Coin</span>'); // <= Change on this line
		});
	});


	function addCommas(numberString) {
		numberString += '';
		var x = numberString.split('.'),
			x1 = x[0],
			x2 = x.length > 1 ? '.' + x[1] : '',
			rgxp = /(\d+)(\d{3})/;

		while (rgxp.test(x1)) {
			x1 = x1.replace(rgxp, '$1' + ' ' + '$2');
		}

		return x1 + x2;
	}

	$("#dataTable1").DataTable({
		"responsive": true,
		"autoWidth": false,
		language: {
			url: '//cdn.datatables.net/plug-ins/1.11.4/i18n/hu.json',
		},
		stateSave: true,
	});

	$("#dataTable2").DataTable({
		"responsive": true,
		"autoWidth": false,
		language: {
			url: '//cdn.datatables.net/plug-ins/1.11.4/i18n/hu.json',
		},
		stateSave: true,
	});

	$("#dataTable3").DataTable({
		"responsive": true,
		"autoWidth": false,
		language: {
			url: '//cdn.datatables.net/plug-ins/1.11.4/i18n/hu.json',
		},
		stateSave: true,
	});
</script>
<?= $this->endSection() ?>
