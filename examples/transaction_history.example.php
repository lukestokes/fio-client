<?php
ini_set('memory_limit','3G');

require_once __DIR__ . '/../vendor/autoload.php';

use xtype\Fio\Client;
use xtype\Fio\Ecc;

$client = new Client('http://fio.greymass.com');
//$client = new Client('https://api.fiosweden.org');
//$client = new Client('http://fio.eosphere.io');
//$client = new Client('https://api.fio.eosdetroit.io');

include "includes/helper_functions.php";

if (!isset($argv[1])) {
    die ("Please include a FIO public key or the number 1 to export foundation wallets.\n");
}

$fio_public_key = $argv[1];

$start_date = "2000-01-01";
$end_date = date("Y-m-d");

if (isset($argv[2])) {
    $start_date = $argv[2];
}
if (isset($argv[3])) {
    $end_date = $argv[3];
}

$fio_public_key = $argv[1];

$transaction_action_processor = function($client, $account_details, $action, $is_fee) {
    if (!$is_fee) {
        $amount = $action->action_trace->act->data->amount;
        if ($action->action_trace->receipt->auth_sequence[0][0] == $account_details["account_name"]) {
            $amount = (0-$amount);
        }
        $amount = bcdiv($amount,1000000000,9);
        $address = $action->action_trace->act->data->payee_public_key;
        if ($action->action_trace->act->data->actor == "eosio") {
            $address = "eosio";
        } else {
            $fio_address = getFIOAddress($client, $action->action_trace->act->data->payee_public_key);
            if ($fio_address != "") {
                $address = $fio_address;
            }
        }
        $transfer = array(
            'date' => $action->block_time,
            'amount' => $amount,
            'address_display' => $address,
            'trx_id' => $action->action_trace->trx_id
        );
        $transfer_date = date('Y-m-d',strtotime($transfer["date"]));
        $account_details["transfers"][] = $transfer;
        if ($amount > 0) {
            $account_details["transfers_in"] = bcadd($account_details["transfers_in"],$amount,9);
        } else {
            $account_details["transfers_out"] = bcadd($account_details["transfers_out"],$amount,9);
        }
    } else {
        $amount = getFIOAmount($action->action_trace->act->data->quantity);
        $address_display = $action->action_trace->act->data->from;
        if ($action->action_trace->act->data->from == $account_details["account_name"]) {
            $amount = (0-$amount);
            $address_display = $action->action_trace->act->data->to;
        }
        $transfer = array(
            'date' => $action->block_time,
            'amount' => $amount,
            'address_display' => $address_display,
            'trx_id' => $action->action_trace->trx_id
        );
        $transfer_date = date('Y-m-d',strtotime($transfer["date"]));
        $account_details["transfers"][] = $transfer;
        if ($amount > 0) {
            $account_details["transfers_in"] = bcadd($account_details["transfers_in"],$amount,9);
        } else {
            $account_details["transfers_out"] = bcadd($account_details["transfers_out"],$amount,9);
        }
    }
    return $account_details;
};

$config = array(
    "client" => $client,
    "start_date" => $start_date,
    "end_date" => $end_date,
    "show_progress" => true,
    "debug_block" => -1,
    "include_fees" => true,
    "transaction_action_processor" => $transaction_action_processor
);

if ($fio_public_key == 1) {
    $foundation_keys = array(
        'treasury_wallet' => 'FIO7WUm6fWGeqHeP9DPriPemdtY1eWZRG9VAhDEWuEX46whAQYLA6',
        'warm_wallet' => 'FIO6pKeeLKKCpHgbrWSLRGA31KKoZbrtV5C1eXGwkz26VNAv8Atjt',
        'managing_director_wallet' => 'FIO8NknCJWs1U66DWj1g7VRXY1gqSQBdn3xw1BaKr1VcxFRWsMTte',
        //'registration_wallet' => 'FIO8NnD5ogVa32MXgTRSfcVrdTVi3RZXNfpMoQYen9c3o4vuyUY8k',
    );
    foreach ($foundation_keys as $name => $foundation_public_key) {
        ob_start();
        $account_details = getAccountDetails($client, $foundation_public_key);
        $account_details = processTransactions($config, $account_details, 0, 1000);
        displayData($account_details);
        createKoinlyFile($account_details, $start_date, $end_date);
        //save buffer in a file
        $buffer = ob_get_flush();
        file_put_contents($foundation_public_key . "_" . $start_date . "_" . $end_date . '.txt', $buffer);
    }
} else {
    $account_details = getAccountDetails($client, $fio_public_key);
    $account_details = processTransactions($config, $account_details, 0, 1000);
    displayData($account_details);
    createKoinlyFile($account_details, $start_date, $end_date);
}

function displayData($account_details) {
    print "\n\n========================== " . $account_details["display_name"] . " =========================\n\n";
    print "Current Balance: " . number_format($account_details["current_balance"],9) . "\n";
    print "Transfers In: " . number_format($account_details["transfers_in"],9) . ", Transfers Out: " . number_format($account_details["transfers_out"],9) . ", Difference: " . number_format(bcadd($account_details["transfers_in"],$account_details["transfers_out"],9),9) . "\n";
    foreach ($account_details["transfers"] as $transfer) {
        $direction = " from ";
        if ($transfer["amount"] < 0) {
            $direction = " to ";
        }
        print "    " . $transfer["date"] . ": " . number_format($transfer["amount"],9) . $direction . $transfer["address_display"] . " " . $transfer["trx_id"] . "\n";
    }
}

function createKoinlyFile($account_details, $start_date, $end_date) {
    $deposits_by_day = array();
    $withdrawals_by_day = array();

    foreach ($account_details["transfers"] as $transfer) {
        $date = strtotime($transfer["date"]);
        $day_string = date("Y-m-d", $date);
        if ($transfer["amount"] < 0) {
            if (!array_key_exists($day_string, $withdrawals_by_day)) {
                $withdrawals_by_day[$day_string] = array(
                    "Date" => $day_string,
                    "Sent Amount" => 0,
                    "Sent Currency" => "FIO",
                    "Received Amount" => 0,
                    "Received Currency" => ""
                );
            }
            $withdrawals_by_day[$day_string]['Sent Amount'] = bcadd(
                $withdrawals_by_day[$day_string]['Sent Amount'],
                bcsub(0,$transfer['amount'],9),
                9
            );
        } else {
            if (!array_key_exists($day_string, $deposits_by_day)) {
                $deposits_by_day[$day_string] = array(
                    "Date" => $day_string,
                    "Sent Amount" => 0,
                    "Sent Currency" => "",
                    "Received Amount" => 0,
                    "Received Currency" => "FIO"
                );
            }
            $deposits_by_day[$day_string]['Received Amount'] = bcadd(
                $deposits_by_day[$day_string]['Received Amount'],
                $transfer['amount'],
                9
            );
        }
    }
    //var_dump($deposits_by_day);
    //var_dump($withdrawals_by_day);
    $summary_csv_data = array();
    foreach ($deposits_by_day as $date => $transfer) {
        $summary_csv_data[] = $transfer;
    }
    foreach ($withdrawals_by_day as $date => $transfer) {
        $summary_csv_data[] = $transfer;
    }

    $output_file = "FIO_Koinly_" . $account_details["account_name"] . "_" . $start_date . "_" . $end_date . ".csv";
    $output_data = "Date,Sent Amount,Sent Currency,Received Amount,Received Currency,Fee Amount,Fee Currency,Net Worth Amount,Net Worth Currency,Label,Description,TxHash\n";
    foreach ($summary_csv_data as $key => $transfer) {
        $output_data .= $transfer['Date'] . ",";
        if ($transfer['Sent Amount'] > 0) {
            $output_data .= str_replace(",","","" . number_format($transfer['Sent Amount'],9)) . ",";
            $output_data .= $transfer['Sent Currency'] . ",";
        } else {
            $output_data .= ",,";
        }
        if ($transfer['Received Amount'] > 0) {
            $output_data .= str_replace(",","","" . number_format($transfer['Received Amount'],9)) . ",";
            $output_data .= $transfer['Received Currency'] . ",";
        } else {
            $output_data .= ",,";
        }
        $output_data .= ",,,,,,\n";
    }
    file_put_contents($output_file, $output_data);
}