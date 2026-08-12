<?php
/**
 * CSV and XLSX writers, hand-rolled — no Composer, no PhpSpreadsheet. CSV needs
 * nothing but fputcsv(). XLSX is a real (minimal) OOXML file built with
 * ZipArchive, which ships with PHP's standard zip extension — not a
 * SpreadsheetML 2003 XML file wearing an .xlsx extension, so it opens cleanly
 * as an actual spreadsheet in both Excel and Google Sheets.
 */

declare(strict_types=1);

/** @param list<string> $headers @param list<list<string>> $rows */
function mnj_send_csv(array $headers, array $rows, string $filename): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM so Excel on Windows (the desk/admin's likely spreadsheet app)
    // detects the encoding instead of mangling Devanagari city/firm names.
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

/** @param list<string> $headers @param list<list<string>> $rows */
function mnj_send_xlsx(array $headers, array $rows, string $filename): never
{
    $tmp = tempnam(sys_get_temp_dir(), 'mnj_xlsx_');
    if ($tmp === false) {
        http_response_code(500);
        exit('Could not create a temp file for the export.');
    }

    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);

    $zip->addFromString('[Content_Types].xml', mnj_xlsx_content_types());
    $zip->addFromString('_rels/.rels', mnj_xlsx_root_rels());
    $zip->addFromString('xl/workbook.xml', mnj_xlsx_workbook());
    $zip->addFromString('xl/_rels/workbook.xml.rels', mnj_xlsx_workbook_rels());
    $zip->addFromString('xl/styles.xml', mnj_xlsx_styles());
    $zip->addFromString('xl/worksheets/sheet1.xml', mnj_xlsx_sheet($headers, $rows));
    $zip->close();

    $bytes = file_get_contents($tmp);
    unlink($tmp);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen((string) $bytes));
    echo $bytes;
    exit;
}

function mnj_xlsx_content_types(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';
}

function mnj_xlsx_root_rels(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
}

function mnj_xlsx_workbook(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Leads" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';
}

function mnj_xlsx_workbook_rels(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';
}

/** Minimal but complete: Excel expects styles.xml to exist even with one bare style. */
function mnj_xlsx_styles(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
        . '</styleSheet>';
}

function mnj_xlsx_col_letter(int $index): string
{
    $letter = '';
    $index++;
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $letter = chr(65 + $mod) . $letter;
        $index = intdiv($index - 1, 26);
    }
    return $letter;
}

/** @param list<string> $headers @param list<list<string>> $rows */
function mnj_xlsx_sheet(array $headers, array $rows): string
{
    $esc = static fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    $xmlRow = static function (int $rowIndex, array $cells) use ($esc): string {
        $out = '<row r="' . ($rowIndex + 1) . '">';
        foreach (array_values($cells) as $colIndex => $value) {
            $ref = mnj_xlsx_col_letter($colIndex) . ($rowIndex + 1);
            // Every cell is an inline string, deliberately — a mobile number or
            // GSTIN reformatted as a "number" (leading zero dropped, scientific
            // notation on a long digit string) is a worse failure than losing
            // numeric sort in the sheet.
            $out .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                . $esc((string) $value) . '</t></is></c>';
        }
        return $out . '</row>';
    };

    $body = $xmlRow(0, $headers);
    foreach ($rows as $i => $row) {
        $body .= $xmlRow($i + 1, $row);
    }

    $lastCol = mnj_xlsx_col_letter(max(0, count($headers) - 1));
    $lastRow = count($rows) + 1;

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="A1:' . $lastCol . $lastRow . '"/>'
        . '<sheetData>' . $body . '</sheetData>'
        . '</worksheet>';
}
