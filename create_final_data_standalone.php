<?php
require_once 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

ini_set('max_execution_time', 0);
ini_set('memory_limit', '4G');

echo "=== STANDALONE FINAL DATA CREATOR ===\n";
echo "Combining cleaned CSV files into final_data.csv\n";
echo "No database connection required\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Configuration
$LOCATION = 'Location201555';
$INPUT_FOLDER = 'historical-cleaned';
$OUTPUT_FOLDER = 'historical-cleaned';

// Define the files we need to combine
$requiredFiles = [
    'sale' => 'sale.csv',
    'saleline' => 'saleline.csv',
    'salelineproduct' => 'salelineproduct.csv',
    'salelineservice' => 'salelineservice.csv',
    'sale_line_employee' => 'sale_line_employee.csv',
    'sale_line_payment' => 'sale_line_payment.csv',
    'sale_line_tax' => 'sale_line_tax.csv',
    'sale_employee_tip' => 'sale_employee_tip.csv',
    'clientgiftcard' => 'clientgiftcard.csv',
    'clientmembership' => 'clientmembership.csv',
    'clientservice' => 'clientservice.csv'
];

/**
 * Read CSV file efficiently
 */
function readCSVFileEfficient($filePath) {
    if (!file_exists($filePath)) {
        echo "  Warning: File not found - " . basename($filePath) . "\n";
        return [];
    }
    
    try {
        $data = [];
        $handle = fopen($filePath, 'r');
        
        if ($handle === false) {
            echo "  Error: Could not open file - " . basename($filePath) . "\n";
            return [];
        }
        
        // Read header
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            echo "  Warning: No headers found - " . basename($filePath) . "\n";
            return [];
        }
        
        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) == count($headers)) {
                $data[] = array_combine($headers, $row);
                $rowCount++;
            }
        }
        
        fclose($handle);
        echo "  Loaded: " . basename($filePath) . " ($rowCount records)\n";
        return $data;
        
    } catch (Exception $e) {
        echo "  Error reading " . basename($filePath) . ": " . $e->getMessage() . "\n";
        return [];
    }
}

/**
 * Create final data by combining CSV files
 */
function createFinalDataEfficient($locationPath, $requiredFiles) {
    echo "Processing location: " . basename($locationPath) . "\n";
    
    $fileData = [];
    
    // Read all required files
    foreach ($requiredFiles as $fileKey => $fileName) {
        $filePath = $locationPath . '/' . $fileName;
        $fileData[$fileKey] = readCSVFileEfficient($filePath);
    }
    
    // Determine main driver (use the one with most records)
    $mainDataKey = 'sale';
    $maxRecords = 0;
    
    foreach (['sale', 'saleline'] as $key) {
        if (!empty($fileData[$key]) && count($fileData[$key]) > $maxRecords) {
            $mainDataKey = $key;
            $maxRecords = count($fileData[$key]);
        }
    }
    
    echo "Using '$mainDataKey' as main driver ($maxRecords records)\n";
    
    if (empty($fileData[$mainDataKey])) {
        echo "No main data found!\n";
        return [];
    }
    
    $combinedData = [];
    $processedCount = 0;
    
    // Process each main record
    foreach ($fileData[$mainDataKey] as $mainRow) {
        $finalRow = $mainRow; // Start with main data
        
        // Add related data from other files
        foreach ($requiredFiles as $fileKey => $fileName) {
            if ($fileKey == $mainDataKey || empty($fileData[$fileKey])) {
                continue;
            }
            
            // Find matching records
            foreach ($fileData[$fileKey] as $relatedRow) {
                $match = false;
                
                // Try different matching strategies
                if (isset($mainRow['EntityId']) && isset($relatedRow['EntityId']) && 
                    $mainRow['EntityId'] == $relatedRow['EntityId']) {
                    $match = true;
                } elseif (isset($mainRow['SaleLine_Seq']) && isset($relatedRow['SaleLine_Seq']) && 
                          $mainRow['SaleLine_Seq'] == $relatedRow['SaleLine_Seq']) {
                    $match = true;
                } elseif (isset($mainRow['TenantId']) && isset($relatedRow['TenantId']) &&
                          isset($mainRow['LocationId']) && isset($relatedRow['LocationId']) &&
                          $mainRow['TenantId'] == $relatedRow['TenantId'] &&
                          $mainRow['LocationId'] == $relatedRow['LocationId']) {
                    $match = true;
                }
                
                if ($match) {
                    // Add fields with prefix to avoid conflicts
                    foreach ($relatedRow as $key => $value) {
                        $prefixedKey = $fileKey . '_' . $key;
                        if (!isset($finalRow[$prefixedKey])) {
                            $finalRow[$prefixedKey] = $value;
                        }
                    }
                    break; // Take first match only
                }
            }
        }
        
        $combinedData[] = $finalRow;
        $processedCount++;
        
        // Show progress for large datasets
        if ($processedCount % 5000 == 0) {
            echo "  Progress: $processedCount records processed...\n";
        }
    }
    
    echo "Combined $processedCount records\n";
    return $combinedData;
}

/**
 * Save final data to CSV efficiently
 */
function saveFinalDataCSVEfficient($data, $outputPath) {
    if (empty($data)) {
        echo "No data to save!\n";
        return false;
    }
    
    try {
        $handle = fopen($outputPath, 'w');
        if ($handle === false) {
            echo "Error: Could not create output file\n";
            return false;
        }
        
        // Get all unique headers
        $allHeaders = [];
        foreach ($data as $row) {
            $allHeaders = array_merge($allHeaders, array_keys($row));
        }
        $allHeaders = array_unique($allHeaders);
        
        // Write headers
        fputcsv($handle, $allHeaders);
        
        // Write data
        $rowCount = 0;
        foreach ($data as $dataRow) {
            $outputRow = [];
            foreach ($allHeaders as $header) {
                $outputRow[] = isset($dataRow[$header]) ? $dataRow[$header] : '';
            }
            fputcsv($handle, $outputRow);
            $rowCount++;
            
            // Show progress
            if ($rowCount % 10000 == 0) {
                echo "  Writing progress: $rowCount rows written...\n";
            }
        }
        
        fclose($handle);
        echo "Final data saved: $outputPath ($rowCount records)\n";
        return true;
        
    } catch (Exception $e) {
        echo "Error saving final data: " . $e->getMessage() . "\n";
        return false;
    }
}

// Main execution
try {
    $inputPath = __DIR__ . '/' . $INPUT_FOLDER . '/' . $LOCATION;
    $outputPath = __DIR__ . '/' . $OUTPUT_FOLDER . '/' . $LOCATION;
    
    if (!is_dir($inputPath)) {
        die("Error: Input directory not found: $inputPath\n");
    }
    
    // Create output directory if needed
    if (!is_dir($outputPath)) {
        mkdir($outputPath, 0777, true);
    }
    
    echo "Input directory: $inputPath\n";
    echo "Output directory: $outputPath\n\n";
    
    // Create final data
    $finalData = createFinalDataEfficient($inputPath, $requiredFiles);
    
    if (!empty($finalData)) {
        $finalDataPath = $outputPath . '/final_data.csv';
        
        if (saveFinalDataCSVEfficient($finalData, $finalDataPath)) {
            echo "\n=== SUCCESS ===\n";
            echo "Final data created successfully!\n";
            echo "Records combined: " . count($finalData) . "\n";
            echo "Output file: $finalDataPath\n";
            echo "\nFile size: " . number_format(filesize($finalDataPath) / (1024*1024), 2) . " MB\n";
            echo "\nNext step: Use csv-data-reader.php to process final_data.csv into database\n";
        }
    } else {
        echo "No data was combined. Check if input files exist and contain data.\n";
    }
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== FINAL DATA CREATION COMPLETED ===\n";
?>
