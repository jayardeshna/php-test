<?php
require_once 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

ini_set('max_execution_time', 0);
ini_set('memory_limit', '2G');

echo "=== FINAL DATA CREATOR ===\n";
echo "Combining cleaned CSV files into final_data.csv\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Configuration
$LOCATION = 'Location201565'; // Change this to process different locations
$INPUT_FOLDER = 'historical-cleaned';
$OUTPUT_FOLDER = 'historical-cleaned';

// Define the files we need to combine (based on your final_data.php)
$requiredFiles = [
    'saleline' => 'saleline.csv',
    'sale' => 'sale.csv',
    'clientgiftcard' => 'clientgiftcard.csv',
    'salelineproduct' => 'salelineproduct.csv',
    'salelineservice' => 'salelineservice.csv',
    'clientmembership' => 'clientmembership.csv',
    'clientservice' => 'clientservice.csv',
    'sale_line_employee' => 'sale_line_employee.csv',
    'sale_line_payment' => 'sale_line_payment.csv',
    'sale_line_tax' => 'sale_line_tax.csv',
    'sale_employee_tip' => 'sale_employee_tip.csv'
];

/**
 * Read CSV file and return data as array
 */
function readCSVFile($filePath) {
    if (!file_exists($filePath)) {
        echo "  Warning: File not found - " . basename($filePath) . "\n";
        return [];
    }
    
    try {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, true);
        
        if (empty($data)) {
            echo "  Warning: Empty file - " . basename($filePath) . "\n";
            return [];
        }
        
        // Convert to associative array with headers
        $headers = array_values($data[1]); // First row as headers
        $formattedData = [];
        
        foreach ($data as $rowIndex => $row) {
            if ($rowIndex === 1) continue; // Skip headers
            
            $rowData = [];
            $columnIndex = 0;
            foreach ($row as $key => $value) {
                // Handle #N/A values
                if ($value == '#N\/A' || $value == '#N/A') {
                    $value = '';
                }
                $rowData[$headers[$columnIndex] ?? "Column_$key"] = $value;
                $columnIndex++;
            }
            $formattedData[] = $rowData;
        }
        
        echo "  Loaded: " . basename($filePath) . " (" . count($formattedData) . " records)\n";
        return $formattedData;
        
    } catch (Exception $e) {
        echo "  Error reading " . basename($filePath) . ": " . $e->getMessage() . "\n";
        return [];
    }
}

/**
 * Combine all CSV data into final structure
 */
function createFinalData($locationPath, $requiredFiles) {
    echo "Processing location: " . basename($locationPath) . "\n";
    
    $combinedData = [];
    $fileData = [];
    
    // Read all required files
    foreach ($requiredFiles as $fileKey => $fileName) {
        $filePath = $locationPath . '/' . $fileName;
        $fileData[$fileKey] = readCSVFile($filePath);
    }
    
    // Use sale data as the main driver since saleline might be empty
    $mainData = !empty($fileData['saleline']) ? $fileData['saleline'] : $fileData['sale'];
    $mainDataType = !empty($fileData['saleline']) ? 'saleline' : 'sale';

    echo "Using '$mainDataType' as main driver (" . count($mainData) . " records)\n";

    if (!empty($mainData)) {
        foreach ($mainData as $mainRow) {
            $finalRow = [];

            // Start with main data
            foreach ($mainRow as $key => $value) {
                $finalRow[$key] = $value;
            }

            // Add sale data if we're using saleline as main driver
            if ($mainDataType == 'saleline' && !empty($fileData['sale'])) {
                foreach ($fileData['sale'] as $saleRow) {
                    // Match by TenantId and EntityId
                    if (isset($mainRow['TenantId']) && isset($saleRow['TenantId']) &&
                        isset($mainRow['EntityId']) && isset($saleRow['EntityId']) &&
                        $mainRow['TenantId'] == $saleRow['TenantId'] &&
                        $mainRow['EntityId'] == $saleRow['EntityId']) {

                        // Add sale fields with prefix to avoid conflicts
                        foreach ($saleRow as $key => $value) {
                            if (!isset($finalRow[$key])) {
                                $finalRow['Sale_' . $key] = $value;
                            }
                        }
                        break;
                    }
                }
            }
            
            // Add product data if available
            if (!empty($fileData['salelineproduct'])) {
                foreach ($fileData['salelineproduct'] as $productRow) {
                    // Try to match by SaleLine_Seq first, then by EntityId
                    $matchFound = false;
                    if (isset($mainRow['SaleLine_Seq']) && isset($productRow['SaleLine_Seq']) &&
                        $mainRow['SaleLine_Seq'] == $productRow['SaleLine_Seq']) {
                        $matchFound = true;
                    } elseif (isset($mainRow['EntityId']) && isset($productRow['EntityId']) &&
                              $mainRow['EntityId'] == $productRow['EntityId']) {
                        $matchFound = true;
                    }

                    if ($matchFound) {
                        
                        foreach ($productRow as $key => $value) {
                            if (!isset($finalRow[$key])) {
                                $finalRow['Product_' . $key] = $value;
                            }
                        }
                        break;
                    }
                }
            }
            
            // Add service data if available
            if (!empty($fileData['salelineservice'])) {
                foreach ($fileData['salelineservice'] as $serviceRow) {
                    // Try to match by SaleLine_Seq first, then by EntityId
                    $matchFound = false;
                    if (isset($mainRow['SaleLine_Seq']) && isset($serviceRow['SaleLine_Seq']) &&
                        $mainRow['SaleLine_Seq'] == $serviceRow['SaleLine_Seq']) {
                        $matchFound = true;
                    } elseif (isset($mainRow['EntityId']) && isset($serviceRow['EntityId']) &&
                              $mainRow['EntityId'] == $serviceRow['EntityId']) {
                        $matchFound = true;
                    }

                    if ($matchFound) {
                        
                        foreach ($serviceRow as $key => $value) {
                            if (!isset($finalRow[$key])) {
                                $finalRow['Service_' . $key] = $value;
                            }
                        }
                        break;
                    }
                }
            }
            
            // Add employee data if available
            if (!empty($fileData['sale_line_employee'])) {
                foreach ($fileData['sale_line_employee'] as $empRow) {
                    // Try to match by SaleLine_Seq first, then by EntityId
                    $matchFound = false;
                    if (isset($mainRow['SaleLine_Seq']) && isset($empRow['SaleLine_Seq']) &&
                        $mainRow['SaleLine_Seq'] == $empRow['SaleLine_Seq']) {
                        $matchFound = true;
                    } elseif (isset($mainRow['EntityId']) && isset($empRow['EntityId']) &&
                              $mainRow['EntityId'] == $empRow['EntityId']) {
                        $matchFound = true;
                    }

                    if ($matchFound) {
                        
                        foreach ($empRow as $key => $value) {
                            if (!isset($finalRow[$key])) {
                                $finalRow['Employee_' . $key] = $value;
                            }
                        }
                        break;
                    }
                }
            }
            
            // Add payment data if available
            if (!empty($fileData['sale_line_payment'])) {
                foreach ($fileData['sale_line_payment'] as $paymentRow) {
                    if (isset($saleLineRow['SaleLine_Seq']) && isset($paymentRow['SaleLine_Seq']) &&
                        $saleLineRow['SaleLine_Seq'] == $paymentRow['SaleLine_Seq']) {
                        
                        foreach ($paymentRow as $key => $value) {
                            if (!isset($finalRow[$key])) {
                                $finalRow['Payment_' . $key] = $value;
                            }
                        }
                        break;
                    }
                }
            }
            
            $combinedData[] = $finalRow;
        }
    }
    
    return $combinedData;
}

/**
 * Save final data to CSV
 */
function saveFinalDataCSV($data, $outputPath) {
    if (empty($data)) {
        echo "No data to save!\n";
        return false;
    }
    
    try {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Get all unique headers from all rows
        $allHeaders = [];
        foreach ($data as $row) {
            $allHeaders = array_merge($allHeaders, array_keys($row));
        }
        $allHeaders = array_unique($allHeaders);
        
        // Write headers
        $colIndex = 1;
        foreach ($allHeaders as $header) {
            $sheet->setCellValue([$colIndex, 1], $header);
            $colIndex++;
        }

        // Write data
        $rowIndex = 2;
        foreach ($data as $dataRow) {
            $colIndex = 1;
            foreach ($allHeaders as $header) {
                $value = isset($dataRow[$header]) ? $dataRow[$header] : '';
                $sheet->setCellValue([$colIndex, $rowIndex], $value);
                $colIndex++;
            }
            $rowIndex++;
        }
        
        // Save as CSV
        $writer = new Csv($spreadsheet);
        $writer->setDelimiter(',');
        $writer->setEnclosure('"');
        $writer->setLineEnding("\r\n");
        $writer->save($outputPath);
        
        echo "Final data saved: $outputPath (" . count($data) . " records)\n";
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
    
    // Create output directory if it doesn't exist
    if (!is_dir($outputPath)) {
        mkdir($outputPath, 0777, true);
    }
    
    echo "Input directory: $inputPath\n";
    echo "Output directory: $outputPath\n\n";
    
    // Create final data
    $finalData = createFinalData($inputPath, $requiredFiles);
    
    if (!empty($finalData)) {
        $finalDataPath = $outputPath . '/final_data.csv';
        saveFinalDataCSV($finalData, $finalDataPath);
        
        echo "\n=== SUCCESS ===\n";
        echo "Final data created successfully!\n";
        echo "Records combined: " . count($finalData) . "\n";
        echo "Output file: $finalDataPath\n";
        echo "\nNext step: Use csv-data-reader.php to process final_data.csv into database\n";
    } else {
        echo "No data was combined. Check if input files exist and contain data.\n";
    }
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== FINAL DATA CREATION COMPLETED ===\n";
?>
