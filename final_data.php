<?php
require_once 'config.php';
ini_set('memory_limit', '5G');
$rootDir = __DIR__ . "/cleaned-csv/Location201562/";

$files = [
    'saleline' => 'saleline.csv',
    'sale' => 'sale.csv',
    'clientgiftcard' => 'clientgiftcard.csv',
    'salelineproduct' => 'salelineproduct.csv',
    'salelineservice' => 'salelineservice.csv',
    'clientmembership' => 'clientmembership.csv',
    'clientservice' => 'clientservice.csv',
    'sale_employee_tip' => 'sale_employee_tip.csv',
    'sale_line_employee' => 'sale_line_employee.csv',
    'sale_line_payment' => 'sale_line_payment.csv',
    'sale_line_tax' => 'sale_line_tax.csv',
];

$storedData = [];
$response = [];

if (isset($files['sale'])) {
    $file = fopen($rootDir . $files['sale'], 'r');
    $headers = fgetcsv($file);
    $headers = array_map(function($header) {
        return trim($header, '"');
    }, $headers);

    while (($row = fgetcsv($file)) !== false) {
        $rowData = array_combine($headers, $row);
        $date = strtotime($rowData['SaleDate']);

        if ($date >= strtotime("01-01-2025")) {
            $entityId = $rowData['EntityId'];  
            $storedData[$entityId] = $rowData;
        }
    }
    fclose($file);
}

foreach (array_keys($files) as $fileKey) {
    if (isset($files[$fileKey])) {
        $file = fopen($rootDir . $files[$fileKey], 'r');
        $headers = fgetcsv($file);
        $headers = array_map(function($header) {
            return trim($header, '"');
        }, $headers);

        while (($row = fgetcsv($file)) !== false) {
            $rowData = array_combine($headers, $row);
            $entityId = $rowData['EntityId'];  

            if (isset($storedData[$entityId])) {
                if (!isset($response[$entityId])) {
                    $response[$entityId] = [];
                    $response[$entityId]['Sale_L'] = $storedData[$entityId];
                    $response[$entityId]['Sale_L']['SaleLine_L'] = [];
                    $response[$entityId]['Sale_L']['SaleLine_Discount_L'] = [];
                    $response[$entityId]['Sale_L']['SaleLine_Tax_L'] = [];
                    $response[$entityId]['Sale_L']['SalePayment_L'] = [];
                    $response[$entityId]['Sale_L']['SaleLine_Employee_L'] = [];
                    $response[$entityId]['Sale_L']['SaleLine_Service_L'] = [];
                    $response[$entityId]['Sale_L']['Sale_Employee_Tip_L'] = [];
                    $response[$entityId]['Sale_L']['ClientGiftCard_T'] = [];
                    $response[$entityId]['Sale_L']['ClientService_T'] = [];
                }

                // Append the data to the relevant key
                if ($fileKey === 'saleline') {
                    $response[$entityId]['Sale_L']['SaleLine_L'][] = $rowData;
                } elseif ($fileKey === 'clientservice') {
                    $response[$entityId]['Sale_L']['ClientService_T'][] = $rowData;
                } elseif ($fileKey === 'clientgiftcard') {
                    $response[$entityId]['Sale_L']['ClientGiftCard_T'][] = $rowData;
                } elseif ($fileKey === 'sale_employee_tip') {
                    $response[$entityId]['Sale_L']['Sale_Employee_Tip_L'][] = $rowData;
                } elseif ($fileKey === 'sale_line_payment') {
                    $response[$entityId]['Sale_L']['SalePayment_L'][] = $rowData;
                } elseif ($fileKey === 'sale_line_tax') {
                    $response[$entityId]['Sale_L']['SaleLine_Tax_L'][] = $rowData;
                } elseif ($fileKey === 'sale_line_employee') {
                    $response[$entityId]['Sale_L']['SaleLine_Employee_L'][] = $rowData;
                } elseif ($fileKey === 'salelineservice') {
                    $response[$entityId]['Sale_L']['SaleLine_Service_L'][] = $rowData;
                } elseif ($fileKey === 'clientmembership') {
                    $response[$entityId]['Sale_L']['ClientMembership_T'][] = $rowData;
                } elseif ($fileKey === 'salelineproduct') {
                    $response[$entityId]['Sale_L']['SaleLine_Product_L'][] = $rowData;
                }
            }
        }
        fclose($file);
    }
}

echo "<pre>";
print_r($response);




// // // // $sales=json_encode($response);
// // // // $cr_datetime = date('Ymd') . '-' . $meevo_location . '-' . $i;
// // // // // $sql_json = "INSERT INTO sales_api_master (response,datetime) VALUES ('".$sales."', '".$cr_datetime."') ON DUPLICATE KEY UPDATE response='".$sales."'";
// // // // // $json_save = mysqli_query($conn,$sql_json);
// // // // echo "<pre>";
// // // // print_r($cr_datetime);
// // // // echo "<pre>";
// // // // print_r($sales);

// $table_data = [
//     ['id' => 6, 'meevo_tid' => 200515, 'meevo_location' => 201555],
//     ['id' => 7, 'meevo_tid' => 200515, 'meevo_location' => 201557],
//     ['id' => 8, 'meevo_tid' => 200515, 'meevo_location' => 201567],
//     ['id' => 9, 'meevo_tid' => 200515, 'meevo_location' => 201568],
//     ['id' => 10, 'meevo_tid' => 200515, 'meevo_location' => 201560],
//     ['id' => 12, 'meevo_tid' => 200515, 'meevo_location' => 201566],
//     ['id' => 13, 'meevo_tid' => 200515, 'meevo_location' => 201559],
//     ['id' => 14, 'meevo_tid' => 200515, 'meevo_location' => 201564],
//     ['id' => 15, 'meevo_tid' => 200515, 'meevo_location' => 201562],
//     ['id' => 16, 'meevo_tid' => 200515, 'meevo_location' => 201556],
//     ['id' => 17, 'meevo_tid' => 200515, 'meevo_location' => 201561], 
//     ['id' => 18, 'meevo_tid' => 200515, 'meevo_location' => 203519]
// ];


// function getUserId($meevo_tid, $meevo_location, $table_data) {
//     foreach ($table_data as $row) {
//         if ($row['meevo_tid'] == $meevo_tid && $row['meevo_location'] == $meevo_location) {
//             return $row['id']; 
//         }
//     }
//     return null; 
// }

// $arr_sale =[];
// $query = "INSERT IGNORE INTO sdk_payments (user_id, customer_id, cinvoiceno, nprice, nquantity, cservice, appointment_service_id, iempid, meevo_tenant, meevo_location, tdatetime, type, guid, high) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
// $command = $conn->prepare($query);
// foreach ($response as $entityId => $sale_array) {    

//     if (isset($sale_array['Sale_L']['SaleLine_L']) && is_array($sale_array['Sale_L']['SaleLine_L'])) {
//         foreach ($sale_array['Sale_L']['SaleLine_L'] as $saleData) {
//             $arr_sale[$saleData['SaleLine_Seq']] = [
//                 'guid' => $saleData['ComputedGuid'],
//                 'clientID' => $saleData['Client_TId'],
//                 'price' => $saleData['FinalPrice'],
//                 'qty' => $saleData['Quantity'],
//                 'SaleLine_Seq' => $saleData['SaleLine_Seq']
//             ];
//         }
//     }
    

//     if (!empty($arr_sale)) {
//         $dataTypes = ['SaleLine_Product_L' => 'product', 'SaleLine_Service_L' => 'service', 
//                       'ClientGiftCard_T' => 'gift', 'ClientMembership_T' => 'membership'];
                      
//                       foreach ($dataTypes as $key => $type) {
//                         if (isset($sale_array['Sale_L'][$key]) && is_array($sale_array['Sale_L'][$key])) {
//                             foreach ($sale_array['Sale_L'][$key] as $index => $data) {
//                                 $cleanedKey = str_replace('|', '', $key);
                                
//                                 $cleanedData = [];
//                                 foreach ($data as $dataKey => $dataValue) {
//                                     $cleanedData[str_replace('|', '', $dataKey)] = str_replace('|', '', $dataValue);
//                                 }
                                
//                                 if (isset($arr_sale[$cleanedData['SaleLine_Seq']])) {
//                                     $arr_sale[$cleanedData['SaleLine_Seq']]['type'] = $type;
//                                     $arr_sale[$cleanedData['SaleLine_Seq']]['meevo_id'] = $cleanedData[$cleanedKey === 'ClientGiftCard_T' ? 'ClientGiftCard_TId' : 'Variant_TId'] ?? null;
//                                 }
//                             }
//                         }
//                     }
                    



//                     if (isset($sale_array['Sale_L']['ClientService_T']) && is_array($sale_array['Sale_L']['ClientService_T'])) {
//                         foreach ($sale_array['Sale_L']['ClientService_T'] as $clientServiceT) {
//                             $cleanedClientServiceT = [];
//                             foreach ($clientServiceT as $dataKey => $dataValue) {
//                                 $cleanedKey = str_replace(['|', '"'], '', $dataKey);
//                                 $cleanedValue = str_replace(['|', '"'], '', $dataValue);
//                                 $cleanedClientServiceT[$cleanedKey] = $cleanedValue;
//                             }
//                             // echo "<pre>";print_r($cleanedClientServiceT);exit;
//                             if (isset($cleanedClientServiceT['UsedSaleLine_Seq'])) {
//                                 $seq = $cleanedClientServiceT['UsedSaleLine_Seq'];
//                                 if (isset($arr_sale[$seq])) {
//                                     $arr_sale[$seq]['UsedSaleLine_Seq'] = $seq;
//                                 }
//                             }else{
//                                 $arr_sale[]['UsedSaleLine_Seq'] = '';
//                             }
//                         }
//                     }
                    
                    

//         $meevo_tid = $sale_array['TenantId'] ?? null;
//         $meevo_location = $sale_array['LocationId'] ?? null;
//         $user_id = getUserId($meevo_tid, $meevo_location, $table_data);

//         if (!empty($arr_sale)) {
//             foreach ($arr_sale as $_loop) {
//                 // echo "<pre>"; print_r($_loop);
//                 if (
//                     isset($_loop['UsedSaleLine_Seq'], $_loop['SaleLine_Seq']) &&
//                     !empty($_loop['UsedSaleLine_Seq']) &&
//                     !empty($_loop['SaleLine_Seq']) &&
//                     $_loop['UsedSaleLine_Seq'] == $_loop['SaleLine_Seq']
//                 ) {
//                     // Check if the record exists
//                     $checkQuery = "SELECT COUNT(*) FROM sdk_payments WHERE guid = ?";
//                     $checkStmt = $conn->prepare($checkQuery);
//                     $checkStmt->bind_param('s', $_loop['guid']);
//                     $checkStmt->execute();
//                     $checkStmt->bind_result($recordCount);
//                     $checkStmt->fetch();
//                     $checkStmt->close();

//                     // If record exists, delete it
//                     if ($recordCount > 0) {
//                         $deleteQuery = "DELETE FROM sdk_payments WHERE guid = ?";
//                         $deleteStmt = $conn->prepare($deleteQuery);
//                         $deleteStmt->bind_param('s', $_loop['guid']);
//                         $deleteStmt->execute();
//                         $deleteStmt->close();
//                     }
//                     continue;
//                 }
                
//                 $price = $_loop['price'] ?? NULL;
//                 // $price = isset($_loop['tax']) ? $price - $_loop['tax'] : $price;
//                 $qty = $_loop['qty'] ?? NULL;
//                 $service = $_loop['meevo_id'] ?? NULL;
//                 $emp = $sale_array['Sale_L']['CreatedBy_User_TId'] ?? NULL;
//                 $clientID = $_loop['clientID'] ?? NULL;
//                 $type = $_loop['type'] ?? 'service';
//                 $guid = $_loop['guid'] ?? md5(time());
//                 $appointment_service_id = $sale_array['Sale_L']['SaleLine_Service_L'][0]['AppointService_LId'] ?? NULL;

//                 $selectQuery = "SELECT nprice, type FROM sdk_payments WHERE guid = ? AND DATE(tdatetime) >= '2025-01-01'";
//                 $stmt = $conn->prepare($selectQuery);
//                 $stmt->bind_param('s', $guid);
//                 $stmt->execute();
//                 $result = $stmt->get_result();
//                 $row = $result->fetch_assoc();

//                 if ($row) { // If a record is found
//                     $existingPrice = $row['nprice'];
//                     $existingType = $row['type'];

//                     // Compare prices and check if type is 'membership'
//                     if ($existingType == 'gift') {
//                         $updateQuery = "UPDATE sdk_payments SET nprice = ?, high = 0, type = ? WHERE guid = ?";
//                         $stmt = $conn->prepare($updateQuery);
//                         $stmt->bind_param('dss', $price, $type, $guid);
//                         $stmt->execute();
//                     }
//                 }

//                 // // Bind parameters
//                 // $command->bind_param('sssssssssssss', 
//                 //     $user_id, $clientID, $sale_array['TransactionId'], 
//                 //     $price, $qty, $service, $appointment_service_id, 
//                 //     $emp, $sale_array['TenantId'], $sale_array['LocationId'], 
//                 //     $cr_date, $type, $guid);

//                 // // $command->execute();
//                 // if ($command->execute()) {
//                 //     $successfullyInsertedRecords[] = $guid; 
//                 // }
//             }
//         }
//         }else
//         { 
//             // echo "<pre>";print_r($_loop);
//         // $price = $sale_array['Sale_L']['SalePayment_L'][0]['Amount'] ?? NULL;
//         // $qty = $sale_array['Sale_L']['SaleLine_L'][0]['Quantity'] ?? NULL;
//         // $service = $sale_array['Sale_L']['SaleLine_Service_L'][0]['Service_TId'] ?? NULL;
//         // $emp = $sale_array['Sale_L']['SaleLine_Employee_L'][0]['Employee_User_TId'] ?? NULL;
//         // $appointment_service_id = $sale_array['Sale_L']['SaleLine_Service_L'][0]['AppointService_LId'] ?? NULL;
//         // $type = 'service';
//         // $guid = $sale_array['Sale_L']['SaleLine_L'][0]['ComputedGuid'] ?? md5(time());

//         // // Bind parameters
//         // $command->bind_param('sssssssssssss', 
//         //     $user_id, $sale_array['Sale_L']['SaleLine_L'][0]['Client_TId'], $sale_array['Sale_L']['TransactionId'], 
//         //     $price, $qty, $service, $appointment_service_id, 
//         //     $emp, $sale_array['Sale_L']['TenantId'], $sale_array['Sale_L']['LocationId'], 
//         //     $cr_date, $type, $guid);

//         //     if ($command->execute()) {
//         //         $successfullyInsertedRecords[] = $guid; 
//         //     }

//         // // $selectQuery = "SELECT nprice, type FROM sdk_payments WHERE guid = ? AND DATE(tdatetime) >= '2025-01-01'";
//         // // $stmt = $conn->prepare($selectQuery);
//         // // $stmt->bind_param('s', $guid);
//         // // $stmt->execute();
//         // // $result = $stmt->get_result();
//         // // $row = $result->fetch_assoc();

//         // // if ($row) { // If a record is found
//         // //     $existingPrice = $row['nprice'];
//         // //     $existingType = $row['type'];

//         // //     // Compare prices and check if type is 'membership'
//         // //     if ($existingPrice != $price && $existingType == 'membership') {
//         // //         $updateQuery = "UPDATE sdk_payments SET nprice = ?, high = 0, type = ? WHERE guid = ?";
//         // //         $stmt = $conn->prepare($updateQuery);
//         // //         $stmt->bind_param('dss', $price, $type, $guid);
//         // //         $stmt->execute();
//         // //     }
//         // // }
//     }

//     } 
//     $command->close();

//  ?>
