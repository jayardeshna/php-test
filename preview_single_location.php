<?php
echo "=== SINGLE LOCATION PREVIEW ===\n";
echo "Preview what files will be processed for Location201555\n";
echo "Output folder: historical-cleaned\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Configuration
$LOCATION = 'Location201555';
$OUTPUT_FOLDER = 'historical-cleaned';

// Define the required file types
$requiredFileTypes = [
    'Sale_' => 'sale',
    'SaleLine_' => 'saleline',
    'SaleLineProduct_' => 'salelineproduct',
    'SaleLineService_' => 'salelineservice',
    'SaleLineEmployee_' => 'sale_line_employee',
    'SaleLinePayment_' => 'sale_line_payment',
    'SaleLineTax_' => 'sale_line_tax',
    'SaleEmployeeTip_' => 'sale_employee_tip',
    'ClientGiftCard_' => 'clientgiftcard',
    'ClientMembership_T_' => 'clientmembership',
    'ClientService_T_' => 'clientservice'
];

/**
 * Check if file should be processed
 */
function shouldProcessFile($filename, $requiredFileTypes) {
    if (strpos($filename, '.temp.') !== false || 
        strpos($filename, 'index.') !== false || 
        strpos($filename, 'readme.') !== false) {
        return false;
    }
    
    foreach ($requiredFileTypes as $prefix => $type) {
        if (strpos($filename, $prefix) === 0) {
            return $type;
        }
    }
    
    return false;
}

/**
 * Extract date from filename
 */
function extractDateFromFilename($filename) {
    if (preg_match('/_(\d{8})\.csv$/', $filename, $matches)) {
        return $matches[1];
    }
    return null;
}

/**
 * Check if date is in range (2021 to today)
 */
function isDateInRange($dateStr) {
    if (!$dateStr) return false;
    
    $date = DateTime::createFromFormat('Ymd', $dateStr);
    if (!$date) return false;
    
    $startDate = new DateTime('2021-01-01');
    $endDate = new DateTime();
    
    return $date >= $startDate && $date <= $endDate;
}

// Check if location exists
$locationPath = __DIR__ . '/row-data/' . $LOCATION;
if (!is_dir($locationPath)) {
    die("Error: Location directory not found: $locationPath\n");
}

echo "LOCATION: $LOCATION\n";
echo "Path: $locationPath\n";
echo "Output: ./$OUTPUT_FOLDER/$LOCATION/\n\n";

echo "REQUIRED FILE TYPES:\n";
foreach ($requiredFileTypes as $prefix => $type) {
    echo "  $prefix* -> $type.csv\n";
}
echo "\n";

// Analyze files
$csvFiles = glob($locationPath . '/*.csv');
$stats = [
    'total_files' => count($csvFiles),
    'will_process' => 0,
    'will_skip' => 0,
    'process_files' => [],
    'skip_files' => []
];

foreach ($csvFiles as $filePath) {
    $filename = basename($filePath);
    $fileSize = filesize($filePath) / (1024 * 1024); // MB
    
    $fileType = shouldProcessFile($filename, $requiredFileTypes);
    $dateStr = extractDateFromFilename($filename);
    $dateInRange = isDateInRange($dateStr);
    
    if ($fileType && $dateInRange) {
        $stats['will_process']++;
        $stats['process_files'][] = [
            'name' => $filename,
            'type' => $fileType,
            'date' => $dateStr,
            'size_mb' => $fileSize
        ];
    } else {
        $stats['will_skip']++;
        $reason = !$fileType ? 'not required type' : 'date out of range';
        $stats['skip_files'][] = [
            'name' => $filename,
            'reason' => $reason,
            'date' => $dateStr
        ];
    }
}

echo "ANALYSIS RESULTS:\n";
echo "Total files found: {$stats['total_files']}\n";
echo "Files to process: {$stats['will_process']}\n";
echo "Files to skip: {$stats['will_skip']}\n";
echo "Processing ratio: " . number_format(($stats['will_process'] / $stats['total_files']) * 100, 1) . "%\n\n";

if (!empty($stats['process_files'])) {
    echo "FILES TO PROCESS:\n";
    $totalSize = 0;
    foreach ($stats['process_files'] as $file) {
        echo "  ✓ {$file['name']} -> {$file['type']}.csv (date: {$file['date']}, " . 
             number_format($file['size_mb'], 2) . " MB)\n";
        $totalSize += $file['size_mb'];
    }
    echo "  Total size: " . number_format($totalSize, 2) . " MB\n\n";
}

if (!empty($stats['skip_files'])) {
    echo "FILES TO SKIP (first 10):\n";
    $skipCount = 0;
    foreach ($stats['skip_files'] as $file) {
        if ($skipCount >= 10) {
            echo "  ... and " . (count($stats['skip_files']) - 10) . " more\n";
            break;
        }
        echo "  ✗ {$file['name']} ({$file['reason']})\n";
        $skipCount++;
    }
    echo "\n";
}

echo "To start cleaning, run: php clean_raw_data.php\n";
?>
