<?php
require_once 'config.php';

ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');

$table_data = [
    ['id' => 6, 'meevo_tid' => 200515, 'meevo_location' => 201555],
    ['id' => 7, 'meevo_tid' => 200515, 'meevo_location' => 201557],
    ['id' => 8, 'meevo_tid' => 200515, 'meevo_location' => 201567],
    ['id' => 9, 'meevo_tid' => 200515, 'meevo_location' => 201568],
    ['id' => 10, 'meevo_tid' => 200515, 'meevo_location' => 201560],
    ['id' => 12, 'meevo_tid' => 200515, 'meevo_location' => 201566],
    ['id' => 13, 'meevo_tid' => 200515, 'meevo_location' => 201559],
    ['id' => 14, 'meevo_tid' => 200515, 'meevo_location' => 201564],
    ['id' => 15, 'meevo_tid' => 200515, 'meevo_location' => 201562],
    ['id' => 16, 'meevo_tid' => 200515, 'meevo_location' => 201556],
    ['id' => 17, 'meevo_tid' => 200515, 'meevo_location' => 201561], 
    ['id' => 18, 'meevo_tid' => 200515, 'meevo_location' => 203519]
];


function getUserId($meevo_tid, $meevo_location, $table_data) {
    foreach ($table_data as $row) {
        if ($row['meevo_tid'] == $meevo_tid && $row['meevo_location'] == $meevo_location) {
            return $row['id']; 
        }
    }
    return null; 
}

$sql = "SELECT response FROM sales_api_master";
$qry1 = $conn->query($sql);

$responseDataArray = []; 
$cutoffDate = '2025-01-01';
$arr_sale = [];
$successfullyInsertedRecords = [];

if ($qry1) {
    while ($row = $qry1->fetch_assoc()) {
        $responseDataArray[] = json_decode($row['response'], true);
    }

    $filteredData['Sale_L'] = [];

    // Prepare statement
    $query = "INSERT IGNORE INTO sdk_payments (user_id, customer_id, cinvoiceno, nprice, nquantity, cservice, appointment_service_id, iempid, meevo_tenant, meevo_location, tdatetime, type, guid, high) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
    $command = $conn->prepare($query);

    foreach ($responseDataArray as $responseData) {
        if (isset($responseData['data']) && is_array($responseData['data'])) {
            foreach ($responseData['data'] as $item) {
                if (isset($item['Sale_L']['SaleDate'])) {
                    $startDate = substr($item['Sale_L']['SaleDate'], 0, 10);

                    if ($startDate > $cutoffDate) { 
                        $arr_sale = [];

                        if (isset($item['Sale_L']['SaleLine_L']) && is_array($item['Sale_L']['SaleLine_L'])) {
                            foreach ($item['Sale_L']['SaleLine_L'] as $saleData) {
                                $arr_sale[$saleData['SaleLine_Seq']] = [
                                    'guid' => $saleData['ComputedGuid'],
                                    'clientID' => $saleData['Client_TId'],
                                    'price' => $saleData['FinalPrice'],
                                    'qty' => $saleData['Quantity'],
                                    'SaleLine_Seq' => $saleData['SaleLine_Seq']
                                ];
                            }
                        }

                        if (!empty($arr_sale)) {
                            $dataTypes = ['SaleLine_Product_L' => 'product', 'SaleLine_Service_L' => 'service', 
                                          'ClientGiftCard_T' => 'gift', 'ClientMembership_T' => 'membership'];

                            foreach ($dataTypes as $key => $type) {
                                if (isset($item['Sale_L'][$key]) && is_array($item['Sale_L'][$key])) {
                                    foreach ($item['Sale_L'][$key] as $data) {
                                        if (isset($arr_sale[$data['SaleLine_Seq']])) {
                                            $arr_sale[$data['SaleLine_Seq']]['type'] = $type;
                                            $arr_sale[$data['SaleLine_Seq']]['meevo_id'] = $data[$key === 'ClientGiftCard_T' ? 'ClientGiftCard_TId' : 'Variant_TId'] ?? null;
                                        }
                                    }
                                }
                            }

                            if (isset($item['Sale_L']['ClientService_T']) && is_array($item['Sale_L']['ClientService_T'])) {
                                foreach ($item['Sale_L']['ClientService_T'] as $clientServiceT) {
                                    $arr_sale[$clientServiceT['UsedSaleLine_Seq']]['UsedSaleLine_Seq'] = $clientServiceT['UsedSaleLine_Seq'];
                                }
                            }

                            $meevo_tid = $item['Sale_L']['TenantId'] ?? null;
                            $meevo_location = $item['Sale_L']['LocationId'] ?? null;
                            $user_id = getUserId($meevo_tid, $meevo_location, $table_data);


                            if (!empty($arr_sale)) {
                                foreach ($arr_sale as $_loop) {
                                    if (
                                        isset($_loop['UsedSaleLine_Seq'], $_loop['SaleLine_Seq']) &&
                                        !empty($_loop['UsedSaleLine_Seq']) &&
                                        !empty($_loop['SaleLine_Seq']) &&
                                        $_loop['UsedSaleLine_Seq'] == $_loop['SaleLine_Seq']
                                    ) {
                                        // Check if the record exists
                                        $checkQuery = "SELECT COUNT(*) FROM sdk_payments WHERE guid = ?";
                                        $checkStmt = $conn->prepare($checkQuery);
                                        $checkStmt->bind_param('s', $_loop['guid']);
                                        $checkStmt->execute();
                                        $checkStmt->bind_result($recordCount);
                                        $checkStmt->fetch();
                                        $checkStmt->close();

                                        // If record exists, delete it
                                        if ($recordCount > 0) {
                                            $deleteQuery = "DELETE FROM sdk_payments WHERE guid = ?";
                                            $deleteStmt = $conn->prepare($deleteQuery);
                                            $deleteStmt->bind_param('s', $_loop['guid']);
                                            $deleteStmt->execute();
                                            $deleteStmt->close();
                                        }
                                        continue;
                                    }
                                    
                                    $price = $_loop['price'] ?? NULL;
                                    // $price = isset($_loop['tax']) ? $price - $_loop['tax'] : $price;
                                    $qty = $_loop['qty'] ?? NULL;
                                    $service = $_loop['meevo_id'] ?? NULL;
                                    $emp = $item['Sale_L']['CreatedBy_User_TId'] ?? NULL;
                                    $clientID = $_loop['clientID'] ?? NULL;
                                    $type = $_loop['type'] ?? 'service';
                                    $guid = $_loop['guid'] ?? md5(time());
                                    $appointment_service_id = $item['Sale_L']['SaleLine_Service_L'][0]['AppointService_LId'] ?? NULL;

                                    $updateQuery = "UPDATE sdk_payments SET nprice = ?, high = 0, type = ? WHERE guid = ?";
                                    $stmt = $conn->prepare($updateQuery);
                                    $stmt->bind_param('dss', $price, $type, $guid);  // 'd' for decimal/float, 's' for strings
                                    $stmt->execute();

                                    // // Bind parameters
                                    // $command->bind_param('sssssssssssss', 
                                    //     $user_id, $clientID, $item['Sale_L']['TransactionId'], 
                                    //     $price, $qty, $service, $appointment_service_id, 
                                    //     $emp, $item['Sale_L']['TenantId'], $item['Sale_L']['LocationId'], 
                                    //     $cr_date, $type, $guid);

                                    // // $command->execute();
                                    // if ($command->execute()) {
                                    //     $successfullyInsertedRecords[] = $guid; 
                                    // }
                                }
                            }
                        } else {
                            $price = $item['Sale_L']['SalePayment_L'][0]['Amount'] ?? NULL;
                            // if (!empty($price)) {
                            //     $tip = $item['Sale_L']['Sale_Employee_Tip_L'][0]['Amount'] ?? 0;
                            //     $price -= $tip;
                            //     $_tx = $item['Sale_L']['SaleLine_Tax_L'][0]['FinalAmount'] ?? 0;
                            //     $price -= $_tx;
                            // }

                            $qty = $item['Sale_L']['SaleLine_L'][0]['Quantity'] ?? NULL;
                            $service = $item['Sale_L']['SaleLine_Service_L'][0]['Service_TId'] ?? NULL;
                            $emp = $item['Sale_L']['SaleLine_Employee_L'][0]['Employee_User_TId'] ?? NULL;
                            $appointment_service_id = $item['Sale_L']['SaleLine_Service_L'][0]['AppointService_LId'] ?? NULL;
                            $type = 'service';
                            $guid = $item['Sale_L']['SaleLine_L'][0]['ComputedGuid'] ?? md5(time());

                            $updateQuery = "UPDATE sdk_payments SET nprice = ?, high = 0, type = ? WHERE guid = ?";
                            $stmt = $conn->prepare($updateQuery);
                            $stmt->bind_param('dss', $price, $type, $guid);  // 'd' for decimal/float, 's' for strings
                            $stmt->execute();
                            // Bind parameters
                            // $command->bind_param('sssssssssssss', 
                            //     $user_id, $item['Sale_L']['SaleLine_L'][0]['Client_TId'], $item['Sale_L']['TransactionId'], 
                            //     $price, $qty, $service, $appointment_service_id, 
                            //     $emp, $item['Sale_L']['TenantId'], $item['Sale_L']['LocationId'], 
                            //     $cr_date, $type, $guid);

                            //     if ($command->execute()) {
                            //         $successfullyInsertedRecords[] = $guid; 
                            //     }
                        }
                    }
                }
            }
        }
    }
    
    // Close statement
    $command->close();

} else {
    echo "Error fetching data: " . $conn->error;
}

// Close connection
$conn->close();
?>
