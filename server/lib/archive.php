<?php
/**
 * Shared by cron/monthly-archive.php (automatic, 1st of the month) and
 * admin/archive.php (manual "run it now" button). MySQL remains the
 * permanent store; this writes a convenience XLSX copy into server/archive/,
 * which is .htaccess-denied and never served to a browser.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/export.php';

/**
 * @param string|null $yearMonth "YYYY-MM"; defaults to last calendar month.
 * @return array{path: string, filename: string, count: int}
 */
function mnj_run_monthly_archive(?string $yearMonth = null): array
{
    $yearMonth ??= date('Y-m', strtotime('first day of last month'));
    if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
        throw new InvalidArgumentException('yearMonth must be YYYY-MM');
    }

    $from = $yearMonth . '-01';
    $to = date('Y-m-t', strtotime($from));

    $pdo = mnj_db();
    $stmt = $pdo->prepare(
        "SELECT id, lead_type, firm_name, contact_name, mobile, city, gstin, products,
                bill_amount, usage_units, roof_type, message, status, locale, created_at
         FROM leads WHERE created_at BETWEEN :from AND :to ORDER BY created_at ASC"
    );
    $stmt->execute(['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59']);
    $leads = $stmt->fetchAll();

    $headers = ['ID', 'Date', 'Type', 'Firm', 'Contact', 'Mobile', 'City', 'GSTIN', 'Products',
        'Bill (₹)', 'Usage (units)', 'Roof', 'Message', 'Status'];
    $rows = array_map(static fn($l) => [
        (string) $l['id'], $l['created_at'], $l['lead_type'], (string) $l['firm_name'],
        $l['contact_name'], $l['mobile'], (string) $l['city'], (string) $l['gstin'],
        (string) $l['products'], (string) $l['bill_amount'], (string) $l['usage_units'],
        (string) $l['roof_type'], (string) $l['message'], $l['status'],
    ], $leads);

    $dir = __DIR__ . '/../archive';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $filename = "leads-{$yearMonth}.xlsx";
    $path = $dir . '/' . $filename;

    mnj_write_xlsx_file($headers, $rows, $path);

    return ['path' => $path, 'filename' => $filename, 'count' => count($leads)];
}

/** Same builder as mnj_send_xlsx() in export.php, but writes to disk instead of streaming. */
function mnj_write_xlsx_file(array $headers, array $rows, string $path): void
{
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', mnj_xlsx_content_types());
    $zip->addFromString('_rels/.rels', mnj_xlsx_root_rels());
    $zip->addFromString('xl/workbook.xml', mnj_xlsx_workbook());
    $zip->addFromString('xl/_rels/workbook.xml.rels', mnj_xlsx_workbook_rels());
    $zip->addFromString('xl/styles.xml', mnj_xlsx_styles());
    $zip->addFromString('xl/worksheets/sheet1.xml', mnj_xlsx_sheet($headers, $rows));
    $zip->close();
}
