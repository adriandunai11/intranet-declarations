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

                    <?php if ($relation): ?>
                        <strong>Jogviszony státusz</strong>
                        <p>
                            <?php
                            $relationStatusLabels = [
                                'draft' => ['Piszkozat', 'secondary'],
                                'invited' => ['Meghívó kiküldve', 'info'],
                                'onboarding' => ['Beléptetés alatt', 'warning'],
                                'in_progress' => ['Kitöltés folyamatban', 'warning'],
                                'declarations_submitted' => ['Nyilatkozatok ellenőrzésre várnak', 'primary'],
                                'completed' => ['Nyilatkozatok elfogadva', 'success'],
                                'active' => ['Aktív', 'success'],
                                'transferred' => ['Áthelyezve', 'info'],
                                'closed' => ['Lezárva', 'dark'],
                                'cancelled' => ['Törölve', 'danger'],
                            ];
                            [$relationStatusLabel, $relationStatusClass] = $relationStatusLabels[$relation->status] ?? [$relation->status ?: '-', 'secondary'];
                            ?>
                            <span class="badge badge-<?= esc($relationStatusClass) ?>">
                                <?= esc($relationStatusLabel) ?>
                            </span>
                        </p>
                    <?php endif; ?>

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
            <?php if (!empty($canEditPacketItems)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title mb-0">Nyilatkozatcsomag szerkesztése</h3>
                    </div>

                    <div class="card-body">
                        <?php if (empty($editableTemplates ?? [])): ?>
                            <p class="text-muted mb-0">
                                Nincs további hozzáadható online kitölthető nyilatkozat.
                            </p>
                        <?php else: ?>
                            <?= form_open('declarations/packets/' . $packet->id . '/items/add', ['class' => 'form-inline']) ?>
                            <?= csrf_field() ?>

                            <label for="add_template_id" class="mr-2">Kimaradt nyilatkozat</label>
                            <select name="template_id" id="add_template_id" class="form-control mr-2 mb-2" required>
                                <option value="">Válassz nyilatkozatot...</option>
                                <?php foreach ($editableTemplates as $template): ?>
                                    <option value="<?= (int) $template->id ?>">
                                        <?= esc($template->displayName()) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button type="submit" class="btn btn-default mb-2">
                                <i class="fas fa-plus pr-1"></i> Hozzáadás
                            </button>

                            <?= form_close() ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Nyilatkozatok a csomagban
                    </h3>
                    <?php if (!empty($batchRejectItems ?? [])): ?>
                        <div class="card-tools">
                            <button type="button" class="btn btn-sm btn-outline-danger" data-toggle="modal"
                                data-target="#batchRejectItemsModal">
                                <i class="fas fa-times-circle pr-1"></i> Több nyilatkozat elutasítása
                            </button>
                        </div>
                    <?php endif; ?>
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

                                                <?php if ($submission && hasPermissions('declarations_packets_view')): ?>
                                                    <hr>
                                                    <div class="d-flex flex-wrap align-items-center">
                                                        <a href="<?= url('declarations/packets/' . $packet->id . '/items/' . $item->id . '/documents/preview') ?>"
                                                            class="btn btn-outline-info btn-sm mr-2 mb-2"
                                                            target="_blank"
                                                            rel="noopener">
                                                            <i class="fas fa-eye pr-1"></i> PDF előnézet
                                                        </a>

                                                        <?= form_open('declarations/packets/' . $packet->id . '/items/' . $item->id . '/documents/generate/pdf', ['class' => 'mr-2 mb-2']) ?>
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                            <i class="fas fa-file-pdf pr-1"></i> PDF letöltés
                                                        </button>
                                                        <?= form_close() ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        A PDF a beküldött online űrlapadatokból készül, DOCX sablon nélkül.
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
                    [$invitationLabel, $invitationClass] = $invitationStatusLabels[$activeInvitation->status ?? $latestInvitation->status ?? ''] ?? ['Nincs aktív meghívó', 'secondary'];
                    ?>

                    <p>
                        <span class="badge badge-<?= esc($invitationClass) ?>"><?= esc($invitationLabel) ?></span>
                    </p>

                    <?php if ($latestInvitation): ?>
                        <dl class="row">
                            <dt class="col-sm-4">E-mail</dt>
                            <dd class="col-sm-8"><?= esc($latestInvitation->email ?: '-') ?></dd>

                            <dt class="col-sm-4">Kiküldve</dt>
                            <dd class="col-sm-8"><?= esc($latestInvitation->sent_at ?: '-') ?></dd>

                            <dt class="col-sm-4">Megnyitva</dt>
                            <dd class="col-sm-8"><?= esc($latestInvitation->opened_at ?: '-') ?></dd>

                            <dt class="col-sm-4">Lejárat</dt>
                            <dd class="col-sm-8"><?= esc($latestInvitation->expires_at ?: '-') ?></dd>
                        </dl>
                    <?php else: ?>
                        <p class="text-muted">Még nincs létrehozott meghívó link ehhez a csomaghoz.</p>
                    <?php endif; ?>

                    <?php if (hasPermissions('declarations_invitations_regenerate')): ?>
                        <?= form_open('declarations/packets/' . $packet->id . '/invitation/send-new-link') ?>
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-envelope pr-1"></i> Új link küldése
                        </button>
                        <?= form_close() ?>
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
        ?>

        <?php if ($submission && ($reviewItem['can_review'] ?? false)): ?>
            <div class="modal fade" id="rejectItemModal<?= (int) $item->id ?>" tabindex="-1" role="dialog"
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
                            <p class="text-muted">
                                Add meg, mit kell javítani. Az üzenetet a kitöltő megkapja.
                            </p>
                            <div class="form-group">
                                <label for="review_note_<?= (int) $item->id ?>">Javítás oka</label>
                                <textarea name="review_note" id="review_note_<?= (int) $item->id ?>" class="form-control" rows="4" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Mégsem</button>
                            <button type="submit" class="btn btn-danger">Elutasítás és értesítés</button>
                        </div>
                        <?= form_close() ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($submission && hasPermissions('declarations_admin_override')): ?>
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
                            <p class="text-muted">
                                Ez az elem újra kitölthető lesz a meghívó linken. Az elfogadás/elutasítás adatai törlődnek.
                            </p>
                            <div class="form-group">
                                <label for="reopen_review_note_<?= (int) $item->id ?>">Megjegyzés a javításhoz</label>
                                <textarea name="review_note" id="reopen_review_note_<?= (int) $item->id ?>" class="form-control" rows="4"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Mégsem</button>
                            <button type="submit" class="btn btn-warning">Újranyitás</button>
                        </div>
                        <?= form_close() ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($batchRejectItems ?? [])): ?>
    <div class="modal fade" id="batchRejectItemsModal" tabindex="-1" role="dialog" aria-labelledby="batchRejectItemsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <?= form_open('declarations/packets/' . $packet->id . '/items/reject-batch') ?>
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="batchRejectItemsModalLabel">Több nyilatkozat elutasítása</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Bezárás">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Jelöld ki a javítandó nyilatkozatokat, és írd be külön-külön a javítás okát.</p>

                    <?php foreach ($batchRejectItems as $reviewItem): ?>
                        <?php $item = $reviewItem['item']; ?>
                        <div class="border rounded p-3 mb-3">
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" name="item_ids[]" value="<?= (int) $item->id ?>" id="batch_reject_item_<?= (int) $item->id ?>">
                                <label class="custom-control-label" for="batch_reject_item_<?= (int) $item->id ?>">
                                    <?= esc($item->template_name ?: ('Nyilatkozat #' . $item->id)) ?>
                                </label>
                            </div>
                            <textarea name="review_notes[<?= (int) $item->id ?>]" class="form-control" rows="3" placeholder="Javítás oka"></textarea>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Mégsem</button>
                    <button type="submit" class="btn btn-danger">Kijelöltek elutasítása</button>
                </div>
                <?= form_close() ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
