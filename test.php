<?php
require_once 'config.php';

ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');

$staticClientTId = '5de9c5f7-f64f-4b58-8828-b00400ff7368';
$cutoffDate = '2025-01-01';

$sql = "SELECT response FROM sales_api_master";
$qry1 = mysqli_query($conn, $sql);

$filteredResults = [];

if ($qry1) {
    while ($row = mysqli_fetch_assoc($qry1)) {
        $responseData = json_decode($row['response'], true);

        if (isset($responseData['data']) && is_array($responseData['data'])) {
            foreach ($responseData['data'] as $item) {
                if (isset($item['Sale_L']['TransactionDateTime'])) {
                    $startDate = substr($item['Sale_L']['TransactionDateTime'], 0, 10);

                    if ($startDate >= $cutoffDate) {
                        if (isset($item['Sale_L']['SaleLine_L'][0])) {
                            foreach ($item['Sale_L']['SaleLine_L'] as $saleData) {
                                $clientID = $saleData['Client_TId'] ?? null;

                                if (!empty($clientID) && $clientID === $staticClientTId) {
                                    $filteredResults[] = $item;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if (!empty($filteredResults)) {
        echo "<pre>";
        print_r($filteredResults);
    } else {
        echo "No matching data found for Client_TId: $staticClientTId after the cutoff date.";
    }
} else {
    echo "Error fetching data: " . mysqli_error($conn);
}
?>
