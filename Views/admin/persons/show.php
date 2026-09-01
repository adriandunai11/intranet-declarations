<?= $this->extend('admin/layout/default') ?>
<?= $this->section('content') ?>

<?php
$defaultRecruiterUserId = (int) ($defaultRecruiterUserId ?? 0);
$blockingPacket = $blockingPacket ?? null;

$packetStatusLabels = [
    'draft' => ['Előkészítés alatt', 'secondary'],
    'sent' => ['Kiküldve', 'info'],
    'in_progress' => ['Kitöltés alatt', 'warning'],
    'submitted' => ['Ellenőrzésre vár', 'primary'],
    'approved' => ['Elfogadva, lezárásra vár', 'warning'],
    'completed' => ['Elfogadva, lezárásra vár', 'warning'],
    'closed' => ['Lezárva', 'dark'],
    'cancelled' => ['Törölve', 'danger'],
];

$packetFlowLabels = [
    'onboarding' => 'Belépés',
    'self_service' => 'Saját indítás',
    'self_service_tax' => 'Saját adóügyi nyilatkozat',
    'self_service_change' => 'Saját adatváltozás',
    'admin_manual' => 'Egyéb munkaügyi kiküldés',
];

$divisionNames = [];
foreach ($divisions as $division) {
    $divisionNames[(int) $division->id] = $division->name;
}

$selectedProcessType = old('process_type', 'onboarding');
$selectedRecruiterUserId = (string) old(
    'primary_recruiter_user_id',
    $defaultRecruiterUserId > 0 ? (string) $defaultRecruiterUserId : ''
);
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-7">
                <h1 class="mb-0">
                    <?= esc($person->fullName()) ?>
                    <small class="d-block text-muted mt-1">
                        ANTRA: <?= esc($person->antra_id ?: '-') ?> · E-mail: <?= esc($person->email ?: '-') ?>
                    </small>
                </h1>
            </div>
            <div class="col-sm-5">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?= url('/') ?>"><?= lang('App.home') ?></a></li>
                    <li class="breadcrumb-item"><a href="<?= url('declarations/persons') ?>">Nyilatkozat személyek</a></li>
                    <li class="breadcrumb-item active"><?= esc($person->fullName()) ?></li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Személy adatai</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-toggle="collapse"
                            data-target="#personDetailsCollapse" aria-expanded="true" aria-controls="personDetailsCollapse">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="collapse show" id="personDetailsCollapse">
                    <div class="card-body">
                        <strong>Név</strong>
                        <p class="text-muted"><?= esc($person->fullName()) ?></p>

                        <strong>Antra azonosító</strong>
                        <p class="text-muted"><?= esc($person->antra_id ?: '-') ?></p>

                        <strong>Intranet felhasználó</strong>
                        <p class="text-muted">
                            <?= !empty($person->intranet_user_id) ? '#' . (int) $person->intranet_user_id : '-' ?>
                        </p>

                        <strong>E-mail</strong>
                        <p class="text-muted"><?= esc($person->email ?: '-') ?></p>

                        <strong>Adóazonosító jel</strong>
                        <p class="text-muted"><?= esc($person->tax_number ?: '-') ?></p>

                        <strong>TAJ szám</strong>
                        <p class="text-muted"><?= esc($person->taj_number ?: '-') ?></p>

                        <strong>Születési név</strong>
                        <p class="text-muted"><?= esc($person->birth_name ?: '-') ?></p>

                        <strong>Anyja neve</strong>
                        <p class="text-muted"><?= esc($person->mother_name ?: '-') ?></p>

                        <strong>Születési hely, idő</strong>
                        <p class="text-muted">
                            <?= esc($person->birth_place ?: '-') ?>,
                            <?= esc($person->birth_date ?: '-') ?>
                        </p>

                        <strong>Telefonszám</strong>
                        <p class="text-muted"><?= esc($person->phone ?: '-') ?></p>

                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Nyilatkozatcsomag létrehozása</h3>
                    <div class="card-tools">
                        <?php if (hasPermissions('declarations_packets_create') && !$blockingPacket): ?>
                            <button type="button" class="btn btn-sm btn-primary" data-toggle="modal"
                                data-target="#createPacketModal">
                                <i class="fas fa-file-signature pr-1"></i> Új csomag
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($blockingPacket): ?>
                        <div class="alert alert-warning mb-0">
                            Már van nyitott nyilatkozatcsomag:
                            <a href="<?= url('declarations/packets/' . (int) $blockingPacket->id) ?>">
                                #<?= (int) $blockingPacket->id ?>
                            </a>.
                            Új csomag akkor indítható, ha a korábbi csomagot lezárták vagy törölték.
                        </div>
                    <?php elseif (!hasPermissions('declarations_packets_create')): ?>
                        <p class="text-muted mb-0">
                            Nincs jogosultságod nyilatkozatcsomag létrehozására.
                        </p>
                    <?php else: ?>
                        <p class="text-muted mb-0">
                            Válassz belépési vagy egyéb nyilatkozatcsomagot, add meg a céget és a dátumot, majd jelöld ki a kitöltendő nyilatkozatokat.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Nyilatkozatcsomagok</h3>
                </div>

                <div class="card-body">
                    <?php if (empty($packets)): ?>
                        <p class="text-muted mb-0">
                            Még nincs létrehozott nyilatkozatcsomag.
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cég</th>
                                        <th>Folyamat</th>
                                        <th>Dátum</th>
                                        <th>Nyilatkozati év</th>
                                        <th>Státusz</th>
                                        <th>Létrehozva</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($packets as $packet): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= url('declarations/packets/' . $packet->id) ?>">
                                                    #<?= (int) $packet->id ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?= esc($divisionNames[(int) $packet->company_id] ?? ('#' . $packet->company_id)) ?>
                                            </td>
                                            <td>
                                                <?= esc($packetFlowLabels[(string) ($packet->flow_type ?? '')] ?? 'Egyéb') ?>
                                            </td>
                                            <td>
                                                <?= esc($packet->process_date ?: '-') ?>
                                            </td>
                                            <td>
                                                <?= esc($packet->tax_year ?: '-') ?>
                                            </td>
                                            <td>
                                                <?php
                                                [$packetStatusLabel, $packetStatusClass] = $packetStatusLabels[(string) ($packet->status ?? '')] ?? [$packet->status ?: '-', 'secondary'];
                                                ?>
                                                <span class="badge badge-<?= esc($packetStatusClass) ?>">
                                                    <?= esc($packetStatusLabel) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= esc($packet->created_at ?: '-') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Személy előzmények</h3>
                </div>

                <div class="card-body">
                    <p class="text-muted">
                        A személyhez tartozó naplózott események külön oldalon, nagyobb nézetben érhetők el.
                    </p>

                    <a href="<?= url('declarations/persons/' . (int) $person->id . '/audit') ?>" class="btn btn-default">
                        <i class="fas fa-history pr-1"></i> Előzmények megnyitása
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (hasPermissions('declarations_packets_create') && !$blockingPacket): ?>
    <div class="modal fade" id="createPacketModal" role="dialog" data-backdrop="static"
        aria-labelledby="createPacketModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <?= form_open('declarations/persons/' . (int) $person->id . '/packets/create', ['class' => 'form-validate', 'id' => 'createPacketForm']) ?>
                <?= csrf_field() ?>

                <div class="modal-header">
                    <h5 class="modal-title" id="createPacketModalLabel">Nyilatkozatcsomag létrehozása és kiküldése</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Bezárás">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="packet_process_type" class="required">Csomag típusa</label>
                            <select name="process_type" id="packet_process_type" class="form-control" required>
                                <option value="onboarding" <?= $selectedProcessType === 'onboarding' ? 'selected' : '' ?>>Belépés</option>
                                <option value="other" <?= $selectedProcessType === 'other' ? 'selected' : '' ?>>Egyéb</option>
                            </select>
                        </div>

                        <div class="col-md-6 form-group">
                            <label for="packet_tax_year">Nyilatkozati év</label>
                            <input type="number" name="tax_year" id="packet_tax_year" class="form-control"
                                value="<?= esc(old('tax_year', (string) date('Y')), 'attr') ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label for="packet_primary_recruiter_user_id" class="required">Elsődleges toborzó</label>
                            <select name="primary_recruiter_user_id" id="packet_primary_recruiter_user_id" class="form-control select2" required>
                                <option value="">Válassz toborzót...</option>
                                <?php foreach ($recruiters as $recruiter): ?>
                                    <?php
                                    $recruiterId = (int) ($recruiter->id ?? 0);
                                    $recruiterLabel = $recruiterDisplayNames[$recruiterId] ?? ('Felhasználó #' . $recruiterId);
                                    ?>
                                    <option value="<?= $recruiterId ?>" <?= $selectedRecruiterUserId === (string) $recruiterId ? 'selected' : '' ?>>
                                        <?= esc($recruiterLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="packet_company_id" class="required">Cég</label>
                            <select name="company_id" id="packet_company_id" class="form-control select2" required>
                                <option value="">Válassz céget...</option>
                                <?php foreach ($divisions as $division): ?>
                                    <option value="<?= (int) $division->id ?>" <?= (string) old('company_id') === (string) $division->id ? 'selected' : '' ?>>
                                        <?= esc($division->name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 form-group js-onboarding-date">
                            <label for="packet_start_date" class="required">Belépés dátuma</label>
                            <input type="date" name="start_date" id="packet_start_date" class="form-control"
                                value="<?= esc(old('start_date'), 'attr') ?>">
                        </div>

                        <div class="col-md-6 form-group js-other-date">
                            <label for="packet_request_date" class="required">Igénylés dátuma</label>
                            <input type="date" name="request_date" id="packet_request_date" class="form-control"
                                value="<?= esc(old('request_date', date('Y-m-d')), 'attr') ?>">
                        </div>
                    </div>

                    <div class="alert alert-info mb-3">
                        A kijelölt nyilatkozatokból létrejön a csomag, majd a kitöltési meghívót azonnal elküldjük a személy adatlapján szereplő e-mail-címre.
                    </div>

                    <label class="mb-2">Kitöltendő nyilatkozatok</label>

                    <?php if (empty($templates)): ?>
                        <p class="text-muted">
                            Nincs aktív nyilatkozat.
                        </p>
                    <?php else: ?>
                        <?php
                        $oldTemplateIds = old('template_ids', []);
                        $oldTemplateIds = is_array($oldTemplateIds) ? array_map('intval', $oldTemplateIds) : [];
                        $defaultTemplateCodes = [
                            'personal_data_statement',
                            'under_3_child_work_schedule_statement',
                            'bank_account_statement',
                        ];
                        $hasPreviousInput = old('process_type') !== null;
                        ?>
                        <?php foreach ($templates as $template): ?>
                            <?php
                            $groupLabel = match ($template->declaration_group ?? '') {
                                'employment' => 'Munkaügyi nyilatkozat',
                                'tax' => 'Adóügyi nyilatkozat',
                                'personal_data' => 'Személyes adatok',
                                default => $template->declaration_group ?: '-',
                            };

                            $reviewLabel = match ($template->review_role ?? '') {
                                'recruiter' => 'Toborzó ellenőrzi',
                                'payroll' => 'Munkaügy ellenőrzi',
                                'none' => 'Nincs külön ellenőrzés',
                                default => $template->review_role ?: '-',
                            };

                            $isTemplateChecked = $hasPreviousInput
                                ? in_array((int) $template->id, $oldTemplateIds, true)
                                : in_array((string) $template->code, $defaultTemplateCodes, true);
                            ?>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input"
                                    id="packet_template_<?= (int) $template->id ?>" name="template_ids[]"
                                    value="<?= (int) $template->id ?>" <?= $isTemplateChecked ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="packet_template_<?= (int) $template->id ?>">
                                    <?= esc($template->displayName()) ?>
                                    <small class="text-muted d-block">
                                        <?= esc($groupLabel) ?> · <?= esc($reviewLabel) ?>
                                    </small>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane pr-1"></i> Létrehozás és kiküldés
                    </button>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Mégsem</button>
                </div>

                <?= form_close() ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
    $(function () {
        var packetModal = $('#createPacketModal');
        var processType = $('#packet_process_type');
        var onboardingDateGroup = $('.js-onboarding-date');
        var otherDateGroup = $('.js-other-date');
        var onboardingDate = $('#packet_start_date');
        var otherDate = $('#packet_request_date');

        function syncPacketDateFields() {
            var isOnboarding = processType.val() === 'onboarding';

            onboardingDateGroup.toggle(isOnboarding);
            otherDateGroup.toggle(!isOnboarding);
            onboardingDate.prop('required', isOnboarding);
            otherDate.prop('required', !isOnboarding);
        }

        processType.on('change', syncPacketDateFields);
        syncPacketDateFields();

        if ($.fn.select2) {
            $('#packet_primary_recruiter_user_id').select2({
                dropdownParent: packetModal,
                width: '100%',
                placeholder: 'Válassz toborzót...'
            });

            $('#packet_company_id').select2({
                dropdownParent: packetModal,
                width: '100%',
                placeholder: 'Válassz céget...'
            });
        }
    });
</script>
<?= $this->endSection() ?>
