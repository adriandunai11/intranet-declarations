<?= $this->extend('admin/layout/default') ?>
<?= $this->section('content') ?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Nyilatkozatcsomag #
                    <?= (int) $packet->id ?>
                </h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?= url('/') ?>">
                            <?= lang('App.home') ?>
                        </a></li>
                    <li class="breadcrumb-item"><a href="<?= url('declarations/persons') ?>">Nyilatkozatok</a></li>
                    <?php if ($person): ?>
                        <li class="breadcrumb-item">
                            <a href="<?= url('declarations/persons/' . $person->id) ?>">
                                <?= esc($person->fullName()) ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active">Csomag #
                        <?= (int) $packet->id ?>
                    </li>
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
                    <h3 class="card-title">
                        Csomag adatai
                    </h3>
                </div>

                <div class="card-body">
                    <strong>Dolgozó</strong>
                    <p class="text-muted">
                        <?php if ($person): ?>
                            <a href="<?= url('declarations/persons/' . $person->id) ?>">
                                <?= esc($person->fullName()) ?>
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </p>

                    <strong>Cég</strong>
                    <p class="text-muted">
                        <?= esc($company->name ?? ('#' . $packet->company_id)) ?>
                    </p>

                    <?php if ($relation): ?>
                        <strong>Elsődleges toborzó</strong>
                        <p class="text-muted">
                            <?= esc($recruiterDisplayName ?? '-') ?>
                        </p>
                    <?php endif; ?>

                    <strong>Adóév</strong>
                    <p class="text-muted">
                        <?= esc($packet->tax_year ?: '-') ?>
                    </p>

                    <strong>Státusz</strong>
                    <p>
                        <?php
                        $packetStatusLabels = [
                            'draft' => ['Piszkozat', 'secondary'],
                            'sent' => ['Kiküldve', 'info'],
                            'in_progress' => ['Kitöltés folyamatban', 'warning'],
                            'submitted' => ['Ellenőrzésre vár', 'primary'],
                            'approved' => ['Elfogadva', 'success'],
                            'closed' => ['Lezárva', 'dark'],
                            'completed' => ['Elfogadva', 'success'],
                            'cancelled' => ['Törölve', 'danger'],
                        ];

                        [$packetStatusLabel, $packetStatusClass] = $packetStatusLabels[$packet->status] ?? [$packet->status ?: '-', 'secondary'];
                        ?>
                        <span class="badge badge-<?= esc($packetStatusClass) ?>">
                            <?= esc($packetStatusLabel) ?>
                        </span>
                    </p>

                    <?php if (hasPermissions('declarations_admin_override') && in_array((string) $packet->status, ['approved', 'completed'], true)): ?>
                        <?= form_open('declarations/packets/' . $packet->id . '/close', ['class' => 'mb-3']) ?>
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Biztosan lezárod ezt a nyilatkozatcsomagot?')">
                            <i class="fas fa-lock pr-1"></i> Csomag lezárása
                        </button>
                        <?= form_close() ?>
                    <?php endif; ?>

                    <strong>Létrehozva</strong>
                    <p class="text-muted">
                        <?= esc($packet->created_at ?: '-') ?>
                    </p>

                    <strong>Kiküldve</strong>
                    <p class="text-muted">
                        <?= esc($packet->sent_at ?: '-') ?>
                    </p>

                    <strong>Ellenőrzés lezárva</strong>
                    <p class="text-muted">
                        <?= esc($packet->completed_at ?: '-') ?>
                    </p>
                </div>
            </div>

            <a href="<?= url('declarations/persons/' . $packet->person_id) ?>" class="btn btn-default btn-block">
                <i class="fas fa-arrow-left pr-1"></i> Vissza a személy adatlapjára
            </a>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Nyilatkozatok a csomagban
                    </h3>
                </div>

                <div class="card-body">
                    <?php if (empty($reviewItems)): ?>
                        <p class="text-muted mb-0">
                            A csomagban nincs nyilatkozat.
                        </p>
                    <?php else: ?>
                        <div class="accordion" id="packetReviewAccordion">
                            <?php foreach ($reviewItems as $index => $reviewItem): ?>
                                <?php
                                $item = $reviewItem['item'];
                                $submission = $reviewItem['submission'];
                                $displayRows = $reviewItem['display_rows'];
                                $canReview = (bool) ($reviewItem['can_review'] ?? false);

                                $collapseId = 'packetItemCollapse' . (int) $item->id;
                                $headingId = 'packetItemHeading' . (int) $item->id;

                                $itemStatusLabels = [
                                    'pending' => ['Kitöltésre vár', 'secondary'],
                                    'in_progress' => ['Kitöltés alatt', 'warning'],
                                    'completed' => ['Beküldve', 'primary'],
                                    'accepted' => ['Elfogadva', 'success'],
                                    'rejected' => ['Elutasítva', 'danger'],
                                ];

                                [$itemStatusLabel, $itemStatusClass] = $itemStatusLabels[$item->status] ?? [$item->status ?: '-', 'secondary'];

                                $isOpen = $submission !== null || $index === 0;
                                ?>

                                <div class="card mb-2">
                                    <div class="card-header p-0" id="<?= esc($headingId) ?>">
                                        <button class="btn btn-link btn-block text-left text-decoration-none p-3" type="button"
                                            data-toggle="collapse" data-target="#<?= esc($collapseId) ?>"
                                            aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
                                            aria-controls="<?= esc($collapseId) ?>">
                                            <div class="d-flex justify-content-between align-items-start flex-wrap">
                                                <div>
                                                    <strong><?= esc($item->template_name ?: '-') ?></strong>
                                                    <div class="text-muted small mt-1">
                                                        <?= esc($item->template_code ?: '-') ?>
                                                        <?php if (!empty($item->template_review_role)): ?>
                                                            · Ellenőrzi:
                                                            <?= $item->template_review_role === 'payroll' ? 'Munkaügy' : 'Toborzó' ?>
                                                        <?php endif; ?>

                                                        <?php if (!empty($item->template_tax_year)): ?>
                                                            · Adóév: <?= esc($item->template_tax_year) ?>
                                                        <?php endif; ?>

                                                        <?php if (!empty($item->template_version)): ?>
                                                            · Verzió: <?= esc($item->template_version) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="text-right">
                                                    <span class="badge badge-<?= esc($itemStatusClass) ?>">
                                                        <?= esc($itemStatusLabel) ?>
                                                    </span>

                                                    <?php if ($submission && !empty($submission->submitted_at)): ?>
                                                        <div class="text-muted small mt-1">
                                                            Beküldve: <?= esc($submission->submitted_at) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </button>
                                    </div>

                                    <div id="<?= esc($collapseId) ?>" class="collapse <?= $isOpen ? 'show' : '' ?>"
                                        aria-labelledby="<?= esc($headingId) ?>" data-parent="#packetReviewAccordion">
                                        <div class="card-body">
                                            <?php if (!$submission): ?>
                                                <p class="text-muted mb-0">
                                                    Ez a nyilatkozat még nincs beküldve.
                                                </p>
                                            <?php elseif (empty($displayRows)): ?>
                                                <p class="text-muted mb-0">
                                                    A beküldött adatok nem jeleníthetők meg.
                                                </p>
                                            <?php else: ?>
                                                <dl class="row mb-0">
                                                    <?php foreach ($displayRows as $label => $value): ?>
                                                        <dt class="col-sm-4 col-lg-3">
                                                            <?= esc($label) ?>
                                                        </dt>
                                                        <dd class="col-sm-8 col-lg-9">
                                                            <?= esc($value !== '' ? $value : '-') ?>
                                                        </dd>
                                                    <?php endforeach; ?>
                                                </dl>

                                                <?php if ($submission && $item->status === 'completed' && $canReview): ?>
                                                    <hr>

                                                    <div class="d-flex flex-wrap">
                                                        <?= form_open('declarations/packets/' . $packet->id . '/items/' . $item->id . '/accept', ['class' => 'mr-2 mb-2']) ?>
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="fas fa-check pr-1"></i> Elfogadás
                                                        </button>
                                                        <?= form_close() ?>

                                                        <button type="button" class="btn btn-danger btn-sm mb-2" data-toggle="modal"
                                                            data-target="#rejectItemModal<?= (int) $item->id ?>">
                                                            <i class="fas fa-times pr-1"></i> Elutasítás
                                                        </button>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (
                                                    hasPermissions('declarations_admin_override')
                                                    && $submission
                                                    && in_array((string) $item->status, ['completed', 'accepted', 'rejected'], true)
                                                ): ?>
                                                    <hr>

                                                    <button type="button" class="btn btn-outline-warning btn-sm mb-2"
                                                        data-toggle="modal"
                                                        data-target="#reopenItemForCorrectionModal<?= (int) $item->id ?>">
                                                        <i class="fas fa-undo pr-1"></i> Újranyitás javításra
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($item->status === 'rejected' && !empty($item->review_note)): ?>
                                                    <div class="alert alert-danger mt-3 mb-0">
                                                        <strong>Elutasítás oka:</strong><br>
                                                        <?= nl2br(esc($item->review_note)) ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($item->status === 'accepted'): ?>
                                                    <div class="alert alert-success mt-3 mb-0">
                                                        A nyilatkozat elfogadva.
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title mb-0">Meghívó link kezelése</h3>
                </div>

                <div class="card-body">
                    <?php
                    $invitationStatusLabels = [
                        'created' => ['Létrehozva, még nincs kiküldve', 'secondary'],
                        'sent' => ['Kiküldve', 'info'],
                        'opened' => ['Megnyitva', 'primary'],
                        'completed' => ['Lezárva', 'success'],
                        'expired' => ['Lejárt', 'warning'],
                        'revoked' => ['Visszavonva', 'danger'],
                        'cancelled' => ['Törölve', 'danger'],
                    ];
                    $latestInvitation = $latestInvitation ?? null;
                    [$invitationStatusLabel, $invitationStatusClass] = $latestInvitation
                        ? ($invitationStatusLabels[$latestInvitation->status] ?? [$latestInvitation->status ?: '-', 'secondary'])
                        : ['Még nincs meghívó', 'secondary'];
                    ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Utolsó meghívó állapota</strong>
                            <p class="mb-2">
                                <span class="badge badge-<?= esc($invitationStatusClass) ?>"><?= esc($invitationStatusLabel) ?></span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <strong>E-mail cím</strong>
                            <p class="text-muted mb-2"><?= esc($latestInvitation->email ?? ($person->email ?? '-')) ?></p>
                        </div>
                        <div class="col-md-3">
                            <strong>Kiküldve</strong>
                            <p class="text-muted mb-2"><?= esc($latestInvitation->sent_at ?? '-') ?></p>
                        </div>
                        <div class="col-md-3">
                            <strong>Megnyitva</strong>
                            <p class="text-muted mb-2"><?= esc($latestInvitation->opened_at ?? '-') ?></p>
                        </div>
                        <div class="col-md-3">
                            <strong>Lejár</strong>
                            <p class="text-muted mb-2"><?= esc($latestInvitation->expires_at ?? '-') ?></p>
                        </div>
                        <div class="col-md-3">
                            <strong>Visszavonva</strong>
                            <p class="text-muted mb-2"><?= esc($latestInvitation->revoked_at ?? '-') ?></p>
                        </div>
                    </div>

                    <p class="text-muted mb-3">
                        Itt tudsz dokumentumkitöltő linket létrehozni vagy új linket küldeni a beállónak.
                        Az új link küldésekor a korábbi aktív linkek érvénytelenítésre kerülnek.
                    </p>

                    <?php if (!$latestInvitation && hasPermissions('declarations_packets_invite')): ?>
                        <?= form_open('declarations/packets/' . $packet->id . '/invitation/create', ['class' => 'd-inline-block mr-2 mb-2']) ?>
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-default">
                            <i class="fas fa-link pr-1"></i> Első meghívó link létrehozása
                        </button>
                        <?= form_close() ?>
                    <?php endif; ?>

                    <?php if (hasPermissions('declarations_invitations_regenerate')): ?>
                        <button type="button" class="btn btn-warning mb-2" data-toggle="modal"
                            data-target="#sendNewInvitationLinkModal">
                            <i class="fas fa-sync-alt pr-1"></i> Új meghívó link küldése
                        </button>
                    <?php endif; ?>

                    <div class="text-muted small mt-2">
                        A meghívó link 14 napig érvényes. Ha több dokumentumot nyitsz újra, előbb nyisd újra mindet, majd egyszer küldj új linket.
                    </div>
                </div>
            </div>

            <?php if (hasPermissions('declarations_invitations_regenerate')): ?>
                <div class="modal fade" id="sendNewInvitationLinkModal" tabindex="-1" role="dialog"
                    aria-labelledby="sendNewInvitationLinkModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <?= form_open('declarations/packets/' . $packet->id . '/invitation/send-new-link') ?>
                            <?= csrf_field() ?>

                            <div class="modal-header">
                                <h5 class="modal-title" id="sendNewInvitationLinkModalLabel">
                                    Új meghívó link küldése
                                </h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Bezárás">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>

                            <div class="modal-body">
                                <p>
                                    Biztosan új meghívó linket szeretnél küldeni a beállónak?
                                </p>

                                <div class="alert alert-warning mb-0">
                                    A korábbi aktív linkek érvénytelenítésre kerülnek.
                                    Ezt akkor használd, ha a beálló nem találja a korábbi e-mailt,
                                    vagy új hozzáférést szeretnél biztosítani.
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="submit" class="btn btn-warning">
                                    Új link küldése
                                </button>

                                <button type="button" class="btn btn-default" data-dismiss="modal">
                                    Mégsem
                                </button>
                            </div>

                            <?= form_close() ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title mb-0">Előzmények</h3>
                </div>

                <div class="card-body p-0">
                    <?php if (empty($auditLogs ?? [])): ?>
                        <div class="p-3 text-muted">Még nincs naplózott esemény ehhez a csomaghoz.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 160px;">Időpont</th>
                                        <th>Esemény</th>
                                        <th>Státusz</th>
                                        <th>Megjegyzés</th>
                                        <th style="width: 140px;">Szereplő</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($auditLogs as $auditLog): ?>
                                        <tr>
                                            <td><?= esc($auditLog->created_at ?: '-') ?></td>
                                            <td>
                                                <strong><?= esc($auditLog->action ?: '-') ?></strong>
                                                <div class="text-muted small">
                                                    <?= esc($auditLog->entity_type ?: '-') ?> #<?= esc($auditLog->entity_id ?: '-') ?>
                                                    <?php if (!empty($auditLog->packet_item_id)): ?>
                                                        · item #<?= (int) $auditLog->packet_item_id ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($auditLog->old_status) || !empty($auditLog->new_status)): ?>
                                                    <span class="text-muted"><?= esc($auditLog->old_status ?: '-') ?></span>
                                                    <i class="fas fa-arrow-right mx-1 text-muted"></i>
                                                    <span><?= esc($auditLog->new_status ?: '-') ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= esc($auditLog->note ?: '-') ?></td>
                                            <td><?= esc($auditLog->actor_label ?: ($auditLog->actor_user_id ? ('User #' . $auditLog->actor_user_id) : ($auditLog->actor_type ?: '-'))) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($reviewItems)): ?>
    <?php foreach ($reviewItems as $reviewItem): ?>
        <?php
        $item = $reviewItem['item'];
        $submission = $reviewItem['submission'];
        $canReview = (bool) ($reviewItem['can_review'] ?? false);
        ?>

        <?php if ($submission && $item->status === 'completed' && $canReview): ?>
            <div class="modal fade" id="rejectItemModal<?= (int) $item->id ?>" role="dialog" data-backdrop="static"
                aria-labelledby="rejectItemModalLabel<?= (int) $item->id ?>" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <?= form_open('declarations/packets/' . $packet->id . '/items/' . $item->id . '/reject') ?>
                        <?= csrf_field() ?>

                        <div class="modal-header">
                            <h5 class="modal-title" id="rejectItemModalLabel<?= (int) $item->id ?>">
                                Nyilatkozat elutasítása
                            </h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Bezárás">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>

                        <div class="modal-body">
                            <p>
                                Biztosan elutasítod ezt a nyilatkozatot?
                            </p>

                            <p class="text-muted">
                                <?= esc($item->template_name ?: '-') ?>
                            </p>

                            <div class="form-group">
                                <label for="review_note_<?= (int) $item->id ?>" class="required">
                                    Javítás oka / megjegyzés
                                </label>
                                <textarea name="review_note" id="review_note_<?= (int) $item->id ?>" class="form-control" rows="4"
                                    required></textarea>
                            </div>

                            <div class="alert alert-warning mb-0">
                                Elutasítás után a beálló újra szerkesztheti ezt a nyilatkozatot.
                                Az értesítési esemény rögzítésre kerül.
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-times pr-1"></i> Elutasítás
                            </button>

                            <button type="button" class="btn btn-default" data-dismiss="modal">
                                Mégsem
                            </button>
                        </div>

                        <?= form_close() ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($reviewItems) && hasPermissions('declarations_admin_override')): ?>
    <?php foreach ($reviewItems as $reviewItem): ?>
        <?php
        $item = $reviewItem['item'];
        $submission = $reviewItem['submission'];
        ?>

        <?php if ($submission && in_array((string) $item->status, ['completed', 'accepted', 'rejected'], true)): ?>
            <div class="modal fade" id="reopenItemForCorrectionModal<?= (int) $item->id ?>" tabindex="-1" role="dialog"
                aria-labelledby="reopenItemForCorrectionModalLabel<?= (int) $item->id ?>" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <?= form_open('declarations/packets/' . $packet->id . '/items/' . $item->id . '/reopen-for-correction') ?>
                        <?= csrf_field() ?>

                        <div class="modal-header">
                            <h5 class="modal-title" id="reopenItemForCorrectionModalLabel<?= (int) $item->id ?>">
                                Nyilatkozat újranyitása javításra
                            </h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Bezárás">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>

                        <div class="modal-body">
                            <p>
                                Ezzel a művelettel a dokumentum újra javíthatóvá válik a beálló számára.
                            </p>

                            <p class="text-muted">
                                <?= esc($item->template_name ?: '-') ?>
                            </p>

                            <div class="alert alert-warning">
                                A dokumentum javításra újranyílik. E-mail vagy új meghívó link nem megy ki automatikusan; azt külön, a csomag szintű gombbal tudod küldeni.
                            </div>

                            <div class="form-group">
                                <label for="reopen_review_note_<?= (int) $item->id ?>" class="required">
                                    Javítás oka / admin megjegyzés
                                </label>
                                <textarea name="review_note" id="reopen_review_note_<?= (int) $item->id ?>" class="form-control"
                                    rows="4" required></textarea>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="submit" class="btn btn-warning">
                                Újranyitás javításra
                            </button>

                            <button type="button" class="btn btn-default" data-dismiss="modal">
                                Mégsem
                            </button>
                        </div>

                        <?= form_close() ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<?= $this->endSection() ?>