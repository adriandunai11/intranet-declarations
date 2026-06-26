<?php

namespace App\Modules\Declarations\Services\Documents;

use App\Models\BasicdataModel;
use App\Modules\Declarations\Models\DeclarationPacketItemModel;
use App\Modules\Declarations\Models\DeclarationPacketModel;
use App\Modules\Declarations\Models\DeclarationSubmissionModel;
use App\Modules\Declarations\Models\EmploymentRelationModel;
use App\Modules\Declarations\Models\PersonModel;
use RuntimeException;

class DeclarationDocumentPreviewService
{
    protected DeclarationPacketModel $packetModel;
    protected DeclarationPacketItemModel $itemModel;
    protected DeclarationSubmissionModel $submissionModel;
    protected PersonModel $personModel;
    protected EmploymentRelationModel $relationModel;
    protected BasicdataModel $basicdataModel;
    protected DeclarationDocumentGenerator $generator;
    protected DeclarationDocumentPlaceholderService $placeholderService;

    public function __construct()
    {
        $this->packetModel = new DeclarationPacketModel();
        $this->itemModel = new DeclarationPacketItemModel();
        $this->submissionModel = new DeclarationSubmissionModel();
        $this->personModel = new PersonModel();
        $this->relationModel = new EmploymentRelationModel();
        $this->basicdataModel = new BasicdataModel();
        $this->generator = new DeclarationDocumentGenerator();
        $this->placeholderService = new DeclarationDocumentPlaceholderService();
    }

    public function generateTemporaryPdfForPacketItem(int $packetId, int $itemId): string
    {
        $this->cleanupOldPreviews();

        $packet = $this->packetModel->find($packetId);

        if (!$packet) {
            throw new RuntimeException('A nyilatkozatcsomag nem található.');
        }

        $item = $this->findItemForPacket($packetId, $itemId);
        $submission = $this->submissionModel->findByPacketItemId($itemId);

        if (!$submission) {
            throw new RuntimeException('Ehhez a nyilatkozathoz még nincs mentett adat, ezért nem készíthető előnézet.');
        }

        $templateCode = trim((string) ($item->template_code ?? ''));

        if ($templateCode === '' || $templateCode === 'personal_data_statement') {
            throw new RuntimeException('Ehhez a nyilatkozathoz nincs külön PDF sablon. Az adatok az összesítőben ellenőrizhetők.');
        }

        $templatePath = $this->resolveTemplatePath($item, $templateCode);

        if (!is_file($templatePath)) {
            throw new RuntimeException('A dokumentum sablon még nincs feltöltve ehhez a nyilatkozathoz.');
        }

        $person = $this->personModel->find((int) $packet->person_id);
        $relation = $this->relationModel->find((int) $packet->employment_relation_id);
        $company = !empty($packet->company_id)
            ? $this->basicdataModel->where('type', 'division')->where('id', (int) $packet->company_id)->first()
            : null;

        $placeholders = $this->placeholderService->build($packet, $item, $submission, $person, $relation, $company);
        $outputPath = $this->previewPath((int) $packet->id, (int) $item->id, $templateCode);

        $this->generator->generatePdf($templatePath, $placeholders, $outputPath);

        return $outputPath;
    }

    public function cleanupOldPreviews(): void
    {
        $config = config(\App\Modules\Declarations\Config\Declarations::class);
        $directory = rtrim((string) $config->documentPreviewCachePath, DIRECTORY_SEPARATOR);
        $ttl = max(300, (int) ($config->documentPreviewTtlSeconds ?? 7200));

        if (!is_dir($directory)) {
            return;
        }

        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.pdf') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }

            if ((time() - filemtime($file)) > $ttl) {
                @unlink($file);
            }
        }
    }

    private function findItemForPacket(int $packetId, int $itemId): object
    {
        foreach ($this->itemModel->findWithTemplatesByPacketId($packetId) as $item) {
            if ((int) $item->id === $itemId) {
                return $item;
            }
        }

        throw new RuntimeException('A nyilatkozat nem található ebben a csomagban.');
    }

    private function resolveTemplatePath(object $item, string $templateCode): string
    {
        $config = config(\App\Modules\Declarations\Config\Declarations::class);
        $basePath = rtrim((string) $config->documentTemplatePath, DIRECTORY_SEPARATOR);
        $templateFile = trim((string) ($item->template_file ?? ''));

        if ($templateFile !== '') {
            return $basePath . DIRECTORY_SEPARATOR . ltrim($templateFile, DIRECTORY_SEPARATOR);
        }

        return $basePath . DIRECTORY_SEPARATOR . $templateCode . '.docx';
    }

    private function previewPath(int $packetId, int $itemId, string $templateCode): string
    {
        $config = config(\App\Modules\Declarations\Config\Declarations::class);
        $directory = rtrim((string) $config->documentPreviewCachePath, DIRECTORY_SEPARATOR);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Az előnézeti könyvtár nem hozható létre.');
        }

        $safeCode = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $templateCode) ?: 'nyilatkozat';

        return $directory
            . DIRECTORY_SEPARATOR
            . 'packet_' . $packetId
            . '_item_' . $itemId
            . '_' . $safeCode
            . '_' . bin2hex(random_bytes(4))
            . '.pdf';
    }
}
