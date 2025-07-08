<?php
require 'vendor/autoload.php'; 
require_once 'sftp_connection.php';
// require_once 'config.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');

$filePath = __DIR__ . "/cleaned-csv/Location201568/final_data.csv";

if (!file_exists($filePath)) {
    die("File not found: " . $filePath);
}


$sftp = getSFTPConnection();

if (!$sftp) {
    die('Failed to connect to SFTP server');
}

//to fetch teh user id location and tid wise 
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

$sftp_directory = '/Tenant200515'; 
$targetFolder = 'Location201568';

preg_match('/Tenant(\d+)/', $sftp_directory, $tidMatch);
preg_match('/Location(\d+)/', $targetFolder, $locMatch);

$meevo_tid = isset($tidMatch[1]) ? (int)$tidMatch[1] : null;
$meevo_location = isset($locMatch[1]) ? (int)$locMatch[1] : null;

$user_id = getUserId($meevo_tid, $meevo_location, $table_data);

$spreadsheet = IOFactory::load($filePath);
$sheet = $spreadsheet->getActiveSheet();
$data = $sheet->toArray(null, true, true, true);

$headers = array_values($data[1]); // First row as headers
$formattedData = [];
$successfullyInsertedRecords = [];
// Processing data
foreach ($data as $rowIndex => $row) {
    if ($rowIndex === 1) continue; // Skip headers

    $rowData = [];
    $columnIndex = 0;
    foreach ($row as $key => $value) {
        if ($value == '#N\/A' || $value == '#N/A') {
            $value = ''; 
        }
        $rowData[$headers[$columnIndex] ?? "Column_$key"] = $value;
        $columnIndex++;
    }
    $formattedData[] = $rowData;
}
// header('Content-Type: application/json'); 
// echo json_encode($formattedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$notInsertedSales = [];
$notInsertedCount = 0;
$sale_count = 0; 
foreach ($formattedData as $sale) {
    $sale_count++;
    $arr_sale = [];

    $arr_sale['TenantId'] = $sale['TenantId'];
    $arr_sale['EntityId'] = $sale['EntityId'];
    $arr_sale['LocationId'] = $sale['LocationId'];
    $cr_date = $sale['TransactionDateTime'];
    $arr_sale['guid'] = $sale['ComputedGuid'];
    $arr_sale['clientID'] = $sale['Client_TId'];
    $arr_sale['price'] = $sale['FinalPrice'];
    $arr_sale['qty'] = $sale['Quantity'];

    if (
        isset($sale['UsedSaleLine_Seq'], $sale['SaleLine_Seq']) &&
        !empty($sale['UsedSaleLine_Seq']) &&
        !empty($sale['SaleLine_Seq']) &&
        $sale['UsedSaleLine_Seq'] == $sale['SaleLine_Seq']
    ) {
       // **Check if the record exists in sdk_payments**
       $checkQuery = "SELECT COUNT(*) FROM sdk_payments WHERE guid = ?";
       $checkStmt = $conn->prepare($checkQuery);
       $checkStmt->bind_param('s', $arr_sale['guid']);
       $checkStmt->execute();
       $checkStmt->bind_result($recordCount);
       $checkStmt->fetch();
       $checkStmt->close();

       // **If the record exists, delete it**
       if ($recordCount > 0) {
           $deleteQuery = "DELETE FROM sdk_payments WHERE guid = ?";
           $deleteStmt = $conn->prepare($deleteQuery);
           $deleteStmt->bind_param('s', $arr_sale['guid']);
           $deleteStmt->execute();
           $deleteStmt->close();
       }
       continue; // Skip this record and move to the next iteration
   }


    $query = "INSERT IGNORE INTO sdk_payments (user_id, customer_id, cinvoiceno, nprice, nquantity, cservice, appointment_service_id, iempid, meevo_tenant, meevo_location, tdatetime, type, guid, high) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
    $command = $conn->prepare($query);


    if (!empty($arr_sale['guid']) && !empty($arr_sale['clientID']) && !empty($arr_sale['qty'])) {

        if (isset($sale['Variant_TId']) && !empty($sale['Variant_TId'])) {
            $arr_sale['type'] = 'product';
            $arr_sale['meevo_id'] = $sale['Variant_TId'];
        }

        if (isset($sale['Service_TId']) && !empty($sale['Service_TId'])) {
            $arr_sale['type'] = 'service';
            $arr_sale['meevo_id'] = $sale['Service_TId'];
        }

        if (isset($sale['ClientGiftCard_TId']) && !empty($sale['ClientGiftCard_TId'])) {
            $arr_sale['type'] = 'gift';
            $arr_sale['meevo_id'] = $sale['ClientGiftCard_TId'];
        }

        if (isset($sale['Membership_TId']) && !empty($sale['Membership_TId'])) {
            $arr_sale['type'] = 'membership';
            $arr_sale['meevo_id'] = $sale['Membership_TId'];
        }
        

        // if (isset($arr_sale['SaleLineTax_Seq']) && !empty($arr_sale['SaleLineTax_Seq'])) {
        //     $arr_sale['tax'] = $arr_sale['SaleLineTaxFinalAmount'];
        // }

        // if (isset($arr_sale['UsedSaleLine_Seq']) && !empty($arr_sale['UsedSaleLine_Seq']) && 
        // isset($arr_sale['SaleLine_Seq']) && !empty($arr_sale['SaleLine_Seq'])) {
        //     if($arr_sale['UsedSaleLine_Seq']==$arr_sale['SaleLine_Seq']){
                
           
    //    echo "<pre>----arra_sale";
    //    print_r($arr_sale);
    //    echo "<pre>";
            $user_id = $user_id; 
            $clientID = $arr_sale['clientID'];
            $price = isset($arr_sale['price']) ? $arr_sale['price'] : NULL;
            if(isset($arr_sale['tax']) && !empty($price)){
                $price = $price - $arr_sale['tax'];
            }            
            $qty = isset($arr_sale['qty']) ? $arr_sale['qty'] : NULL;
            $service = isset($arr_sale['meevo_id']) ? $arr_sale['meevo_id'] : NULL;
            $emp = isset($sale['CreatedBy_User_TId']) ? $sale['CreatedBy_User_TId']: NULL;
            $type = isset($arr_sale['type']) ? $arr_sale['type'] : 'service';
            $guid = isset($arr_sale['guid']) ? $arr_sale['guid'] : md5(time());
            $appointment_service_id = isset($sale['AppointService_LId']) ? $sale['AppointService_LId'] : NULL;
            $transaction_id = isset($sale['TransactionId']) ? (string) $sale['TransactionId'] : NULL;
            
            $command->bind_param('sssssssssssss', 
                $user_id, 
                $clientID, 
                $transaction_id, 
                $price, 
                $qty, 
                $service, 
                $appointment_service_id, 
                $emp, 
                $sale['TenantId'], 
                $sale['LocationId'], 
                $cr_date, 
                $type, 
                $guid
            );
            // Execute the command
            if (!$command->execute()) {
                $notInsertedCount++; 
                $notInsertedSales[] = "Error: " . $command->error . " | TenantId: {$arr_sale['TenantId']}, LocationId: {$arr_sale['LocationId']}, Date: {$cr_date}, GUID: {$arr_sale['guid']}";
            }else{
                $successfullyInsertedRecords[] = $guid; 
            }
        
    } else {
        $price = isset($sale['SalepaymentAmount']) ? $sale['SalepaymentAmount'] : NULL ;
        if(!empty($price) && isset($sale['Amount'])){
            $tip = !empty($sale['Amount']) ? $sale['Amount'] : 0;
            $price = $price - $tip;
        }

        if(!empty($price) && isset($sale['SaleLineTaxFinalAmount'])){
            $_tx = !empty($sale['SaleLineTaxFinalAmount']) ? $sale['SaleLineTaxFinalAmount'] : 0;
            $price = $price - $_tx;
        }
        $qty = isset($sale['Quantity']) ? $sale['Quantity'] : NULL;
        $service = isset($sale['Service_TId']) ? $sale['Service_TId'] : NULL;
        $emp = isset($sale['Employee_User_TId']) ? $sale['Employee_User_TId'] : NULL;
        $appointment_service_id = isset($sale['AppointService_LId']) ? $sale['AppointService_LId'] : NULL;
        $type = 'service';
        $guid = $sale['ComputedGuid'];
        $command->bind_param('sssssssssssss', $user_id, $sale['Client_TId'], $sale['TransactionId'], 
        $price, $qty, $service, $appointment_service_id, $emp, $sale['TenantId'], $sale['LocationId'], $cr_date, $type, $guid);
        if (!$command->execute()) {
            $notInsertedCount++;
            echo "Error: " . $command->error . "<br>";
        }else{
            $successfullyInsertedRecords[] = $guid; 
        }
    }

}
header('Content-Type: application/json');
echo json_encode(['success' => true, 'inserted_records' => $successfullyInsertedRecords]);
// echo "Total sales processed: " . $sale_count;
// echo "Total Not Inserted Records: $notInsertedCount <br>";
// if (!empty($notInsertedSales)) {
//     echo "List of Not Inserted Records: <br>";
//     echo implode("<br>", $notInsertedSales);
// }
?>
