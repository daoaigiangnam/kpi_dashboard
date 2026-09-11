<?php

namespace App\Services\ItTools;

use PhpOffice\PhpSpreadsheet\IOFactory;

class BulkAuditImportService
{
    public function import(string $path, int $maxItems = 100): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        $items = [];
        $header = array_map(fn ($v) => strtolower(trim((string) $v)), $rows[1] ?? []);
        $domainCol = $this->columnIndex($header, ['domain', 'hostname', 'url']);
        $ipCol = $this->columnIndex($header, ['wan ip', 'wan_ip', 'ip', 'ipv4']);

        foreach (array_slice($rows, 1, $maxItems) as $row) {
            $domain = $domainCol !== null ? trim((string) ($row[$domainCol] ?? '')) : trim((string) ($row['A'] ?? ''));
            if ($domain === '') {
                continue;
            }

            $wanIp = $ipCol !== null ? trim((string) ($row[$ipCol] ?? '')) : trim((string) ($row['B'] ?? ''));
            $items[] = [
                'domain' => $domain,
                'wan_ip' => $wanIp !== '' ? $wanIp : null,
            ];
        }

        return [
            'rows_read' => max(0, count($rows) - 1),
            'items' => $items,
            'count' => count($items),
            'max_items' => $maxItems,
        ];
    }

    private function columnIndex(array $headers, array $names): ?string
    {
        foreach ($headers as $column => $header) {
            if (in_array($header, $names, true)) {
                return $column;
            }
        }
        return null;
    }
}
