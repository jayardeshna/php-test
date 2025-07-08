<?php
ini_set('max_execution_time', 0); 
ini_set('memory_limit', '-1');

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

function parseAndGenerateCSV($rawData, $fileType = null, $targetFolder) {
    global $arr_sale;

  
    $rawData = mb_convert_encoding($rawData, 'UTF-8', 'UTF-16LE');

    $cleanedCsvDir = __DIR__ . "/cleaned-csv/" . $targetFolder . "/";
    if (!is_dir($cleanedCsvDir)) {
        mkdir($cleanedCsvDir, 0777, true);
    }

    $outputFileName = $cleanedCsvDir . $fileType . '.csv';

    $records = array_filter(explode('$|', $rawData));

    // Header processing
    $headerRow = [];
    if (!empty($records[0])) {
        $headerRow = array_map('trim', explode('|@|', preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $records[0])));
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Write headers to the sheet
    $colIndex = 1;
    foreach ($headerRow as $header) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex) . '1', $header);
        $colIndex++;
    }

    $rowIndex = 2;
    for ($i = 1; $i < count($records); $i++) {
        $dataFields = array_map('trim', explode('|@|', $records[$i]));
        $colIndex = 1;
        foreach ($dataFields as $field) {
            $cleanField = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $field);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex) . $rowIndex, $cleanField);
            $colIndex++;
        }
        $rowIndex++;
    }

    $writer = new Csv($spreadsheet);
    $writer->setDelimiter(',');
    $writer->setEnclosure('"');
    $writer->setLineEnding("\r\n");
    $writer->setSheetIndex(0);
    $writer->save($outputFileName);

    echo "CSV file generated successfully: " . $outputFileName . "\n";
}

?>
