<?php
echo "=== HISTORICAL DATA PROCESSING PREVIEW ===\n";
echo "This script shows which files will be processed vs skipped\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Define the required file types only (based on your screenshot)
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
 * Check if file should be processed based on required file types
 */
function shouldProcessFile($filename, $requiredFileTypes) {
    // Skip temp files and system files
    if (strpos($filename, '.temp.') !== false || 
        strpos($filename, 'index.') !== false || 
        strpos($filename, 'readme.') !== false) {
        return false;
    }
    
    // Check if file starts with any required prefix
    foreach ($requiredFileTypes as $prefix => $type) {
        if (strpos($filename, $prefix) === 0) {
            return $type;
        }
    }
    
    return false;
}

/**
 * Extract date from filename (YYYYMMDD format)
 */
function extractDateFromFilename($filename) {
    if (preg_match('/_(\d{8})\.csv$/', $filename, $matches)) {
        return $matches[1];
    }
    return null;
}

/**
 * Check if date is within our range (2021 to today)
 */
function isDateInRange($dateStr) {
    if (!$dateStr) return false;
    
    $date = DateTime::createFromFormat('Ymd', $dateStr);
    if (!$date) return false;
    
    $startDate = new DateTime('2021-01-01');
    $endDate = new DateTime(); // Today
    
    return $date >= $startDate && $date <= $endDate;
}

$rowDataDir = __DIR__ . '/row-data';

if (!is_dir($rowDataDir)) {
    die("Error: row-data directory not found at: $rowDataDir\n");
}

// Get all location directories
$locationDirs = glob($rowDataDir . '/Location*', GLOB_ONLYDIR);

if (empty($locationDirs)) {
    die("Error: No location directories found in row-data folder\n");
}

echo "REQUIRED FILE TYPES (will be processed):\n";
foreach ($requiredFileTypes as $prefix => $type) {
    echo "  $prefix* -> $type\n";
}
echo "\n";

$totalStats = [
    'locations' => count($locationDirs),
    'total_files' => 0,
    'will_process' => 0,
    'will_skip' => 0,
    'total_size_mb' => 0
];

// Analyze each location
foreach ($locationDirs as $locationPath) {
    $locationName = basename($locationPath);
    echo "LOCATION: $locationName\n";
    echo str_repeat('-', 30) . "\n";
    
    $csvFiles = glob($locationPath . '/*.csv');
    $locationStats = [
        'total' => count($csvFiles),
        'will_process' => 0,
        'will_skip' => 0,
        'process_files' => [],
        'skip_files' => []
    ];
    
    foreach ($csvFiles as $filePath) {
        $filename = basename($filePath);
        $fileSize = filesize($filePath) / (1024 * 1024); // MB
        
        // Check if we should process this file
        $fileType = shouldProcessFile($filename, $requiredFileTypes);
        $dateStr = extractDateFromFilename($filename);
        $dateInRange = isDateInRange($dateStr);
        
        if ($fileType && $dateInRange) {
            $locationStats['will_process']++;
            $locationStats['process_files'][] = [
                'name' => $filename,
                'type' => $fileType,
                'date' => $dateStr,
                'size_mb' => $fileSize
            ];
            $totalStats['total_size_mb'] += $fileSize;
        } else {
            $locationStats['will_skip']++;
            $reason = !$fileType ? 'not required type' : 'date out of range';
            $locationStats['skip_files'][] = [
                'name' => $filename,
                'reason' => $reason,
                'date' => $dateStr
            ];
        }
    }
    
    $totalStats['total_files'] += $locationStats['total'];
    $totalStats['will_process'] += $locationStats['will_process'];
    $totalStats['will_skip'] += $locationStats['will_skip'];
    
    echo "Total files: {$locationStats['total']}\n";
    echo "Will process: {$locationStats['will_process']}\n";
    echo "Will skip: {$locationStats['will_skip']}\n";
    
    if (!empty($locationStats['process_files'])) {
        echo "\nFiles to PROCESS:\n";
        foreach ($locationStats['process_files'] as $file) {
            echo "  ✓ {$file['name']} -> {$file['type']} (date: {$file['date']}, " . 
                 number_format($file['size_mb'], 2) . " MB)\n";
        }
    }
    
    if (!empty($locationStats['skip_files'])) {
        echo "\nFiles to SKIP (showing first 10):\n";
        $skipCount = 0;
        foreach ($locationStats['skip_files'] as $file) {
            if ($skipCount >= 10) {
                echo "  ... and " . (count($locationStats['skip_files']) - 10) . " more\n";
                break;
            }
            echo "  ✗ {$file['name']} ({$file['reason']})\n";
            $skipCount++;
        }
    }
    
    echo "\n";
}

// Overall summary
echo "=== OVERALL SUMMARY ===\n";
echo "Total locations: {$totalStats['locations']}\n";
echo "Total files found: {$totalStats['total_files']}\n";
echo "Files to process: {$totalStats['will_process']}\n";
echo "Files to skip: {$totalStats['will_skip']}\n";
echo "Processing ratio: " . number_format(($totalStats['will_process'] / $totalStats['total_files']) * 100, 1) . "%\n";
echo "Total data size to process: " . number_format($totalStats['total_size_mb'], 2) . " MB\n";

// Estimate processing time (rough: 1MB per minute)
$estimatedMinutes = max(1, intval($totalStats['total_size_mb'] / 1));
echo "Estimated processing time: ~$estimatedMinutes minutes\n\n";

echo "To start processing, run: php process_historical_data.php\n";
?>
