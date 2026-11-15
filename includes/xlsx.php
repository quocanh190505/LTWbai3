<?php
// Tạo file Excel (.xlsx) bằng PHP thuần, không cần thư viện hay extension zip.
// Một file .xlsx là một file zip chứa vài file XML. Ở đây ghi zip kiểu "stored" (không nén): đơn giản và đủ cho
// bảng vài nghìn dòng; Excel và LibreOffice đều mở được.
//
// Vì sao không xuất CSV: Excel đọc CSV theo cài đặt vùng của máy (dấu phân cách khác nhau, mất số 0 đầu của số điện thoại,
// và ô bắt đầu bằng "=" bị hiểu là công thức). Với .xlsx mỗi ô có kiểu rõ ràng: chữ là chữ, số là số.
// Chỉ được nạp bằng include từ các trang; ai mở thẳng file này trên trình duyệt thì dừng ngay.
if (!defined("APP_START")) {
    exit("Truy cập không hợp lệ");
}

// Tên cột Excel từ số thứ tự bắt đầu từ 0: 0 -> A, 25 -> Z, 26 -> AA
function xlsx_col(int $index): string
{
    $name = '';
    for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
        $name = chr(65 + ($n - 1) % 26) . $name;
    }
    return $name;
}

// Chữ đưa vào XML: bỏ ký tự điều khiển (XML không cho phép) rồi thoát & < > " '
function xlsx_text(string $text): string
{
    $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';
    return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Gói các file vào một zip không nén. $files: [tên => nội dung]. Trả về nội dung file zip.
function xlsx_zip(array $files): string
{
    $now = getdate();
    $dosTime = ($now['hours'] << 11) | ($now['minutes'] << 5) | ($now['seconds'] >> 1);
    $dosDate = (max(1980, $now['year']) - 1980) << 9 | ($now['mon'] << 5) | $now['mday'];

    $body = '';
    $central = '';
    foreach ($files as $name => $data) {
        $crc = crc32($data);
        $size = strlen($data);
        $offset = strlen($body);
        // Tiêu đề cục bộ: chữ ký, version, cờ, phương thức 0 (không nén), giờ, ngày, CRC32, kích thước nén, kích thước gốc, độ dài tên, độ dài extra
        $body .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, strlen($name), 0) . $name . $data;
        // Thư mục trung tâm: như trên, thêm version tạo ra, độ dài chú thích, đĩa, thuộc tính trong và ngoài, vị trí tiêu đề cục bộ
        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
    }
    $count = count($files);
    $end = pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), strlen($body), 0);
    return $body . $central . $end;
}

// Tạo file .xlsx một trang tính.
//   $sheetName : tên trang (được làm sạch: tối đa 31 ký tự, bỏ \ / ? * [ ] :)
//   $columns   : mỗi cột ['title' => tiêu đề, 'width' => độ rộng, 'type' => 'text' | 'money' | 'rating']
//                text: ô chữ; money: số có dấu phân cách hàng nghìn và 2 số lẻ; rating: số 1 chữ số thập phân
//   $rows      : mỗi dòng là mảng giá trị theo thứ tự cột. Giá trị null hoặc '' để ô trống;
//                cột money và rating nhận chuỗi số thập phân như "500000000.00" (không phải số thì để ô trống)
// Dòng tiêu đề được in đậm, cố định khi cuộn và có bộ lọc tự động.
function xlsx_build(string $sheetName, array $columns, array $rows): string
{
    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $lastCol = xlsx_col(count($columns) - 1);
    $lastRow = count($rows) + 1;

    $cols = '';
    foreach ($columns as $i => $column) {
        $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (int) ($column['width'] ?? 14) . '" customWidth="1"/>';
    }

    // Chỉ số kiểu ô trong styles.xml: 0 thường, 1 tiêu đề, 2 tiền, 3 điểm
    $styleOf = ['text' => 0, 'money' => 2, 'rating' => 3];

    $sheetRows = '<row r="1">';
    foreach ($columns as $i => $column) {
        $sheetRows .= '<c r="' . xlsx_col($i) . '1" s="1" t="inlineStr"><is><t xml:space="preserve">' . xlsx_text((string) $column['title']) . '</t></is></c>';
    }
    $sheetRows .= '</row>';

    foreach ($rows as $n => $row) {
        $r = $n + 2;
        $sheetRows .= '<row r="' . $r . '">';
        foreach ($columns as $i => $column) {
            $value = $row[$i] ?? null;
            if ($value === null || $value === '') {
                continue;   // ô trống: không ghi
            }
            $ref = xlsx_col($i) . $r;
            $type = $column['type'] ?? 'text';
            if ($type === 'text') {
                $sheetRows .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . xlsx_text((string) $value) . '</t></is></c>';
            } elseif (preg_match('/^-?\d+(\.\d+)?$/', (string) $value) === 1) {   // chỉ nhận số thập phân thường; giá trị khác để ô trống
                $sheetRows .= '<c r="' . $ref . '" s="' . $styleOf[$type] . '"><v>' . $value . '</v></c>';
            }
        }
        $sheetRows .= '</row>';
    }

    $sheet = $xml . '<worksheet xmlns="' . $ns . '">'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<cols>' . $cols . '</cols>'
        . '<sheetData>' . $sheetRows . '</sheetData>'
        . '<autoFilter ref="A1:' . $lastCol . $lastRow . '"/>'
        . '</worksheet>';

    $safeName = preg_replace('/[\\\\\/?*\[\]:]/u', ' ', $sheetName) ?? '';
    $safeName = trim(mb_substr($safeName, 0, 31)) ?: 'Sheet1';

    $styles = $xml . '<styleSheet xmlns="' . $ns . '">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.0"/></numFmts>'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFE6F4F1"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="4">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
        . '<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    return xlsx_zip([
        '[Content_Types].xml' => $xml . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' => $xml . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => $xml . '<workbook xmlns="' . $ns . '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . xlsx_text($safeName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => $xml . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        'xl/styles.xml' => $styles,
        'xl/worksheets/sheet1.xml' => $sheet,
    ]);
}
