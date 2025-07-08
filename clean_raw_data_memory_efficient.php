<?php
require_once 'csv-data-parser.php';

ini_set('max_execution_time', 0);
ini_set('memory_limit', '4G'); // Increased memory limit

echo "=== MEMORY EFFICIENT RAW DATA CLEANER ===\n";
echo "Converting raw pipe-delimited data to clean CSV format\n";
echo "Output folder: historical-cleaned\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Configuration - Change these as needed
$SINGLE_LOCATION = 'Location201565'; // Set to specific location or null for all locations
$OUTPUT_FOLDER = 'historical-cleaned'; // Output folder name

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
    'start_time' => time(),
    'locations' => []
];

/**
 * Memory efficient CSV generation function
 */
function parseAndGenerateCSVMemoryEfficient($rawData, $fileType, $targetFolder, $outputFolder) {
    // Convert encoding
    $rawData = mb_convert_encoding($rawData, 'UTF-8', 'UTF-16LE');

    // Create output directory
    $cleanedCsvDir = __DIR__ . "/" . $outputFolder . "/" . $targetFolder . "/";
    if (!is_dir($cleanedCsvDir)) {
        mkdir($cleanedCsvDir, 0777, true);
    }

    $outputFileName = $cleanedCsvDir . $fileType . '.csv';

    // Parse the pipe-delimited data
    $records = array_filter(explode('$|', $rawData));
    
    if (empty($records)) {
        echo "    Warning: No records found in file\n";
        return false;
    }

    // Header processing
    $headerRow = [];
    if (!empty($records[0])) {
        $headerRow = array_map('trim', explode('|@|', preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $records[0])));
    }

    if (empty($headerRow)) {
        echo "    Warning: No headers found in file\n";
        return false;
    }

    // Open CSV file for writing
    $csvFile = fopen($outputFileName, 'w');
    if (!$csvFile) {
        echo "    Error: Could not create output file\n";
        return false;
    }

    // Write headers
    fputcsv($csvFile, $headerRow);

    // Process data rows in chunks to save memory
    $processedRows = 0;
    $chunkSize = 1000; // Process 1000 rows at a time
    
    for ($i = 1; $i < count($records); $i += $chunkSize) {
        $chunk = array_slice($records, $i, $chunkSize);
        
        foreach ($chunk as $record) {
            $dataFields = array_map('trim', explode('|@|', $record));
            
            // Clean fields and ensure we have the right number of columns
            $cleanedFields = [];
            for ($j = 0; $j < count($headerRow); $j++) {
                $field = isset($dataFields[$j]) ? $dataFields[$j] : '';
                $cleanedFields[] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $field);
            }
            
            fputcsv($csvFile, $cleanedFields);
            $processedRows++;
        }
        
        // Clear memory
        unset($chunk);
        
        // Show progress for large files
        if ($processedRows % 10000 == 0) {
            echo "    Progress: $processedRows rows processed...\n";
        }
    }

    fclose($csvFile);
    
    echo "    Success: $processedRows rows written to " . basename($outputFileName) . "\n";
    return $outputFileName;
}

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
 * Clean files in a single location folder
 */
function cleanLocationFiles($locationPath, $requiredFileTypes, $outputFolder) {
    global $stats;
    
    $locationName = basename($locationPath);
    echo "Cleaning location: $locationName\n";
    
    $locationStats = [
        'name' => $locationName,
        'files_found' => 0,
        'files_processed' => 0,
        'files_skipped' => 0
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
        
        // Check file size and warn for large files
        $fileSize = filesize($filePath) / (1024 * 1024); // MB
        if ($fileSize > 100) {
            echo "  Processing large file: $filename (" . number_format($fileSize, 1) . " MB) - this may take a while...\n";
        } else {
            echo "  Cleaning: $filename -> $fileType (date: $dateStr, " . number_format($fileSize, 1) . " MB)\n";
        }
        
        try {
            // Read raw file content
            $rawData = file_get_contents($filePath);
            if ($rawData === false) {
                echo "    Error: Could not read file\n";
                $locationStats['files_skipped']++;
                $stats['total_files_skipped']++;
                continue;
            }
            
            // Use memory efficient CSV generation
            $result = parseAndGenerateCSVMemoryEfficient($rawData, $fileType, $locationName, $outputFolder);
            
            if ($result) {
                $locationStats['files_processed']++;
                $stats['total_files_processed']++;
            } else {
                $locationStats['files_skipped']++;
                $stats['total_files_skipped']++;
            }
            
            // Clear memory
            unset($rawData);
            
        } catch (Exception $e) {
            echo "    Error: " . $e->getMessage() . "\n";
            $locationStats['files_skipped']++;
            $stats['total_files_skipped']++;
        }
        
        // Force garbage collection
        gc_collect_cycles();
    }
    
    echo "  Location summary: {$locationStats['files_processed']}/{$locationStats['files_found']} files cleaned\n\n";
    
    $stats['locations'][] = $locationStats;
    return $locationStats;
}

/**
 * Main cleaning function
 */
function cleanAllLocations($requiredFileTypes, $singleLocation = null, $outputFolder = 'cleaned-csv') {
    global $stats;
    
    $rowDataDir = __DIR__ . '/row-data';
    
    if (!is_dir($rowDataDir)) {
        die("Error: row-data directory not found at: $rowDataDir\n");
    }
    
    if ($singleLocation) {
        // Process only one specific location
        $locationPath = $rowDataDir . '/' . $singleLocation;
        if (!is_dir($locationPath)) {
            die("Error: Location directory not found: $locationPath\n");
        }
        
        echo "Processing single location: $singleLocation\n";
        echo "Output folder: $outputFolder\n";
        echo "Required file types: " . implode(', ', array_keys($requiredFileTypes)) . "\n";
        echo "Date range: 2021-01-01 to " . date('Y-m-d') . "\n\n";
        
        $stats['total_locations'] = 1;
        cleanLocationFiles($locationPath, $requiredFileTypes, $outputFolder);
        
    } else {
        // Process all locations
        $locationDirs = glob($rowDataDir . '/Location*', GLOB_ONLYDIR);
        $stats['total_locations'] = count($locationDirs);
        
        if (empty($locationDirs)) {
            die("Error: No location directories found in row-data folder\n");
        }
        
        echo "Found " . count($locationDirs) . " location directories\n";
        echo "Required file types: " . implode(', ', array_keys($requiredFileTypes)) . "\n";
        echo "Date range: 2021-01-01 to " . date('Y-m-d') . "\n\n";
        
        // Clean files in each location
        foreach ($locationDirs as $locationPath) {
            cleanLocationFiles($locationPath, $requiredFileTypes, $outputFolder);
        }
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
    
    echo "=== CLEANING SUMMARY ===\n";
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
    echo "Duration: " . gmdate('H:i:s', $duration) . "\n\n";
    
    echo "OVERALL STATISTICS:\n";
    echo "- Locations processed: {$stats['total_locations']}\n";
    echo "- Files found: {$stats['total_files_found']}\n";
    echo "- Files cleaned: {$stats['total_files_processed']}\n";
    echo "- Files skipped: {$stats['total_files_skipped']}\n";
    echo "- Success rate: " . number_format($successRate, 1) . "%\n\n";
    
    echo "LOCATION BREAKDOWN:\n";
    foreach ($stats['locations'] as $location) {
        $locationSuccessRate = $location['files_found'] > 0 ? 
            ($location['files_processed'] / $location['files_found']) * 100 : 0;
        
        echo "  {$location['name']}:\n";
        echo "    Files: {$location['files_processed']}/{$location['files_found']} cleaned\n";
        echo "    Success rate: " . number_format($locationSuccessRate, 1) . "%\n";
    }
    
    echo "\nCleaned CSV files are available in: ./historical-cleaned/\n";
    echo "Each location has its own subfolder with cleaned CSV files.\n";
}

// Main execution
try {
    cleanAllLocations($requiredFileTypes, $SINGLE_LOCATION, $OUTPUT_FOLDER);
    generateSummaryReport();
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== DATA CLEANING COMPLETED ===\n";
echo "Next step: Use create_final_data.php to combine CSV files\n";
?>
