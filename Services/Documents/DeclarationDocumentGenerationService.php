<?php

namespace App\Modules\Declarations\Services\Documents;

use App\Models\BasicdataModel;
use App\Modules\Declarations\Models\DeclarationAuditLogModel;
use App\Modules\Declarations\Models\DeclarationPacketItemModel;
use App\Modules\Declarations\Models\DeclarationPacketModel;
use App\Modules\Declarations\Models\DeclarationSubmissionModel;
use App\Modules\Declarations\Models\EmploymentRelationModel;
use App\Modules\Declarations\Models\PersonModel;
use RuntimeException;

class DeclarationDocumentGenerationService
{
    protected DeclarationPacketModel $packetModel;
    protected DeclarationPacketItemModel $itemModel;
    protected DeclarationSubmissionModel $submissionModel;
    protected PersonModel $personModel;
    protected EmploymentRelationModel $relationModel;
    protected BasicdataModel $basicdataModel;
    protected DeclarationAuditLogModel $auditLogModel;
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
        $this->auditLogModel = new DeclarationAuditLogModel();
        $this->generator = new DeclarationDocumentGenerator();
        $this->placeholderService = new DeclarationDocumentPlaceholderService();
    }

    public function generateForPacketItem(int $packetId, int $itemId, string $format): string
    {
        $format = strtolower(trim($format));

        if (!in_array($format, ['docx', 'pdf'], true)) {
            throw new RuntimeException('Csak DOCX vagy PDF dokumentum generálható.');
        }

        $packet = $this->packetModel->find($packetId);

        if (!$packet) {
            throw new RuntimeException('A nyilatkozatcsomag nem található.');
        }

        $item = $this->findItemForPacket($packetId, $itemId);
        $submission = $this->submissionModel->findByPacketItemId($itemId);

        if (!$submission) {
            throw new RuntimeException('Ehhez a nyilatkozathoz még nincs beküldött adat, ezért nem generálható dokumentum.');
        }

        $templateCode = trim((string) ($item->template_code ?? ''));

        if ($templateCode === '') {
            throw new RuntimeException('A nyilatkozat sablonkódja hiányzik.');
        }

        $templatePath = $this->resolveTemplatePath($item, $templateCode);

        if (!is_file($templatePath)) {
            throw new RuntimeException('A DOCX sablon nem található: ' . $templatePath);
        }

        $person = $this->personModel->find((int) $packet->person_id);
        $relation = $this->relationModel->find((int) $packet->employment_relation_id);
        $company = !empty($packet->company_id)
            ? $this->basicdataModel->where('type', 'division')->where('id', (int) $packet->company_id)->first()
            : null;

        $placeholders = $this->placeholderService->build($packet, $item, $submission, $person, $relation, $company);
        $outputPath = $this->outputPath((int) $packet->id, (int) $item->id, $templateCode, $format);

        if ($format === 'pdf') {
            $this->generator->generatePdf($templatePath, $placeholders, $outputPath);
        } else {
            $this->generator->generateDocx($templatePath, $placeholders, $outputPath);
        }

        $this->auditLogModel->logAction(
            DeclarationAuditLogModel::ACTION_DOCUMENT_GENERATED,
            'declaration_packet_item',
            (int) $item->id,
            (int) $packet->id,
            (int) $item->id,
            null,
            null,
            strtoupper($format) . ' dokumentum generálva.',
            [
                'person_id' => (int) $packet->person_id,
                'employment_relation_id' => (int) $packet->employment_relation_id,
                'submission_id' => (int) $submission->id,
                'template_code' => $templateCode,
                'template_version' => $item->template_version ?? null,
                'template_file' => $item->template_file ?? null,
                'format' => $format,
                'output_path' => $outputPath,
            ]
        );

        return $outputPath;
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

    private function outputPath(int $packetId, int $itemId, string $templateCode, string $format): string
    {
        $config = config(\App\Modules\Declarations\Config\Declarations::class);
        $directory = rtrim((string) $config->documentOutputPath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'packet_' . $packetId;

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('A dokumentum kimeneti könyvtár nem hozható létre.');
        }

        $safeCode = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $templateCode) ?: 'nyilatkozat';

        return $directory . DIRECTORY_SEPARATOR . $safeCode . '_item_' . $itemId . '.' . $format;
    }
}
