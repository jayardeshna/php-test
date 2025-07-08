<?php
require_once 'config.php';
require_once 'csv-data-parser.php';

ini_set('max_execution_time', 0);
ini_set('memory_limit', '2G');

echo "=== HISTORICAL DATA PROCESSOR ===\n";
echo "Processing data from row-data folder (2021 to today)\n";
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

// Statistics tracking
$stats = [
    'total_locations' => 0,
    'total_files_found' => 0,
    'total_files_processed' => 0,
    'total_files_skipped' => 0,
    'total_records' => 0,
    'start_time' => time(),
    'locations' => []
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

/**
 * Process a single location folder
 */
function processLocation($locationPath, $requiredFileTypes) {
    global $stats;
    
    $locationName = basename($locationPath);
    echo "Processing location: $locationName\n";
    
    $locationStats = [
        'name' => $locationName,
        'files_found' => 0,
        'files_processed' => 0,
        'files_skipped' => 0,
        'records_processed' => 0
    ];
    
    // Get all CSV files in location
    $csvFiles = glob($locationPath . '/*.csv');
    $locationStats['files_found'] = count($csvFiles);
    $stats['total_files_found'] += count($csvFiles);
    
    foreach ($csvFiles as $filePath) {
        $filename = basename($filePath);
        
        // Check if we should process this file
        $fileType = shouldProcessFile($filename, $requiredFileTypes);
        if (!$fileType) {
            $locationStats['files_skipped']++;
            $stats['total_files_skipped']++;
            echo "  Skipping: $filename (not required file type)\n";
            continue;
        }
        
        // Check date range
        $dateStr = extractDateFromFilename($filename);
        if (!isDateInRange($dateStr)) {
            $locationStats['files_skipped']++;
            $stats['total_files_skipped']++;
            echo "  Skipping: $filename (date out of range: $dateStr)\n";
            continue;
        }
        
        // Process the file
        echo "  Processing: $filename -> $fileType (date: $dateStr)\n";
        
        try {
            // Read raw file content
            $rawData = file_get_contents($filePath);
            if ($rawData === false) {
                echo "    Error: Could not read file\n";
                $locationStats['files_skipped']++;
                $stats['total_files_skipped']++;
                continue;
            }
            
            // Use existing parseAndGenerateCSV function
            parseAndGenerateCSV($rawData, $fileType, $locationName);
            
            $locationStats['files_processed']++;
            $stats['total_files_processed']++;
            
            // Count records (rough estimate)
            $recordCount = substr_count($rawData, '$|');
            $locationStats['records_processed'] += $recordCount;
            $stats['total_records'] += $recordCount;
            
            echo "    Success: ~$recordCount records processed\n";
            
        } catch (Exception $e) {
            echo "    Error: " . $e->getMessage() . "\n";
            $locationStats['files_skipped']++;
            $stats['total_files_skipped']++;
        }
    }
    
    echo "  Location summary: {$locationStats['files_processed']}/{$locationStats['files_found']} files processed\n\n";
    
    $stats['locations'][] = $locationStats;
    return $locationStats;
}

/**
 * Main processing function
 */
function processAllLocations($requiredFileTypes) {
    global $stats;
    
    $rowDataDir = __DIR__ . '/row-data';
    
    if (!is_dir($rowDataDir)) {
        die("Error: row-data directory not found at: $rowDataDir\n");
    }
    
    // Get all location directories
    $locationDirs = glob($rowDataDir . '/Location*', GLOB_ONLYDIR);
    $stats['total_locations'] = count($locationDirs);
    
    if (empty($locationDirs)) {
        die("Error: No location directories found in row-data folder\n");
    }
    
    echo "Found " . count($locationDirs) . " location directories\n";
    echo "Required file types: " . implode(', ', array_keys($requiredFileTypes)) . "\n\n";
    
    // Process each location
    foreach ($locationDirs as $locationPath) {
        processLocation($locationPath, $requiredFileTypes);
    }
}

/**
 * Generate summary report
 */
function generateSummaryReport() {
    global $stats;
    
    $duration = time() - $stats['start_time'];
    $successRate = $stats['total_files_found'] > 0 ? 
        ($stats['total_files_processed'] / $stats['total_files_found']) * 100 : 0;
    
    echo "=== PROCESSING SUMMARY ===\n";
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
    echo "Duration: " . gmdate('H:i:s', $duration) . "\n\n";
    
    echo "OVERALL STATISTICS:\n";
    echo "- Locations processed: {$stats['total_locations']}\n";
    echo "- Files found: {$stats['total_files_found']}\n";
    echo "- Files processed: {$stats['total_files_processed']}\n";
    echo "- Files skipped: {$stats['total_files_skipped']}\n";
    echo "- Success rate: " . number_format($successRate, 1) . "%\n";
    echo "- Records processed: ~" . number_format($stats['total_records']) . "\n\n";
    
    echo "LOCATION BREAKDOWN:\n";
    foreach ($stats['locations'] as $location) {
        $locationSuccessRate = $location['files_found'] > 0 ? 
            ($location['files_processed'] / $location['files_found']) * 100 : 0;
        
        echo "  {$location['name']}:\n";
        echo "    Files: {$location['files_processed']}/{$location['files_found']} processed\n";
        echo "    Success rate: " . number_format($locationSuccessRate, 1) . "%\n";
        echo "    Records: ~" . number_format($location['records_processed']) . "\n";
    }
    
    echo "\nCleaned CSV files are available in: ./cleaned-csv/\n";
}

// Main execution
try {
    processAllLocations($requiredFileTypes);
    generateSummaryReport();
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== PROCESSING COMPLETED ===\n";
?>
