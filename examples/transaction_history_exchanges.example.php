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

$exchanges = array(
'bitmax' => 'FIO6unQkWD5s6NoeSBSez1uPYVhT7YnxNFQAXsokLEcHxboYBV6hA',
'binance' => 'FIO8iPiMWognLyWoiwMqG1cTufzKb8xafUGXDQwaPDfio2hY83Bk5',
'bithumb' => 'FIO67aY89JfZ2NviXiGkasLgYgAUvHESZf8yA7y7tb6ji7jYXKDs4',
'liquid1' => 'FIO55cGmzkKCMRxFVbLgCQjYpk8iUTGqwTxrKebs9t8uuCAdhRSvL',
'liquid2' => 'FIO5DXzFyFnkBf71g7EgGx1sFAiefTGmYksCqNqGyYL7DhmJDYcda',
'liquid3' => 'FIO5C17bAtDUf4MTnp8NM5nNmNdW2BFiniTKFXZCjYW7og9VahRXq'
);

$start_date = "2000-01-01";
$end_date = date("Y-m-d");

if (isset($argv[2])) {
    $start_date = $argv[2];
}
if (isset($argv[3])) {
    $end_date = $argv[3];
}

$balances = array();
$key_to_account = array();
$key_to_account_filename = "key_to_account_data.txt";
$data = @file_get_contents($key_to_account_filename);
if ($data) {
    $key_to_account = unserialize($data);
}
$account_to_fio_address = array();
$account_to_fio_address_filename = "account_to_fio_address_data.txt";
$data = @file_get_contents($account_to_fio_address_filename);
if ($data) {
    $account_to_fio_address = unserialize($data);
}

$total_to_show = 200;

$transaction_action_processor = function($client, $account_details, $action, $is_fee) use (&$balances, &$key_to_account) {
    if (!$is_fee) {
        $amount = $action->action_trace->act->data->amount;
        $amount = bcdiv($amount,1000000000,9);
        if ($action->action_trace->receipt->auth_sequence[0][0] == $account_details["account_name"]) {
            // withdrawal from exchange
            if (!array_key_exists($action->action_trace->act->data->payee_public_key, $key_to_account)) {
                $params = array(
                    "fio_public_key" => $action->action_trace->act->data->payee_public_key
                );
                try {
                    $response = $client->chain()->getActor($params);
                    $account_name = $response->actor;
                    $key_to_account[$action->action_trace->act->data->payee_public_key] = $account_name;
                } catch(\Exception $e) { }
            } else {
                $account_name = $key_to_account[$action->action_trace->act->data->payee_public_key];
            }
            if (!array_key_exists($account_name, $balances)) {
                $balances[$account_name] = 0;
            }
            $balances[$account_name] = bcadd($balances[$account_name], $amount, 9);
            $account_details["transfers_out"] = bcsub($account_details["transfers_out"],$amount,9);
        } else {
            // deposit to exchange
            if (!array_key_exists($action->action_trace->act->data->actor, $balances)) {
                $balances[$action->action_trace->act->data->actor] = 0;
            }
            $balances[$action->action_trace->act->data->actor] = bcsub($balances[$action->action_trace->act->data->actor], $amount, 9);
            $account_details["transfers_in"] = bcadd($account_details["transfers_in"],$amount,9);
        }
    }
    return $account_details;
};

$config = array(
    "client" => $client,
    "start_date" => $start_date,
    "end_date" => $end_date,
    "show_progress" => false,
    "debug_block" => -1,
    "include_fees" => false,
    "transaction_action_processor" => $transaction_action_processor
);

print "# Exchange Transfers as of " . date('Y-m-d') . "\n\n";

foreach ($exchanges as $name => $fio_public_key) {
    $account_details = getAccountDetails($client, $fio_public_key);
    $account_details = processTransactions($config, $account_details, 0, 1000);

    print $name . " (" . getBloksLink($account_details['account_name'], $account_details['display_name']) . ") In: " . number_format($account_details['transfers_in']) . " Out: " . number_format($account_details['transfers_out']) . " Balance: " . number_format($account_details['current_balance']) . "  \n";
}

print "\n";


asort($balances);
$deposits = array_filter($balances, function ($v) {
  return $v < 0;
});
$withdraws = array_filter($balances, function ($v) {
  return $v > 0;
});
arsort($withdraws);

print "Accounts withdrawing from exchanges: " . count($withdraws) . "  \n";
print "Average withdrawal amount: " . number_format(array_sum($withdraws) / count($withdraws)) . "  \n";
print "Median withdrawal amount: " . number_format(array_median($withdraws)) . "  \n";
print "  \n";
print "Accounts depositing to exchanges: " . count($deposits) . "  \n";
print "Average deposit amount: " . number_format(array_sum($deposits) / count($withdraws)) . "  \n";
print "Median deposit amount: " . number_format(array_median($deposits)) . "  \n";
print "  \n";
print "Ratio of deposits to withdrawals: " . number_format(count($deposits)/count($withdraws),2) . "  \n";


// get top accounts
$top_withdraws = array_slice($withdraws, 0, $total_to_show);
$top_deposits = array_slice($deposits, 0, $total_to_show);

$header = "|    |           Account|    Net Transfer Amount   |\n";
$header .= "|:--:|:----------------:|:------------------------:|\n";

print "\n## TOP $total_to_show WITHDRAWING FROM EXCHANGES\n\n";
print $header;
$count = 0;
foreach ($top_withdraws as $account => $amount) {
    $count++;
    $fio_address = "";
    if (!array_key_exists($account, $account_to_fio_address)) {
        $key = array_search($account,$key_to_account);
        if ($key !== false) {
            $fio_address = getFIOAddress($client, $key);
        }
        if ($fio_address != "") {
            $account_to_fio_address[$account] = $fio_address;
        }
    } else {
        $fio_address = $account_to_fio_address[$account];
    }
    print '|' . $count . '|' . getBloksLink($account,$fio_address) . ': | ' . number_format($amount) . '|';
    print "\n";
}
print "\n## TOP $total_to_show DEPOSITING TO EXCHANGES\n\n";

$header = "|    |           Account|    Net Transfer Amount   | Inbound Accounts |\n";
$header .= "|:--:|:----------------:|:------------------------:|:-----------:|\n";

print $header;
$count = 0;
foreach ($top_deposits as $account => $amount) {
    $count++;
    $fio_public_key = "";
    $key = array_search($account,$key_to_account);
    if ($key !== false) {
        $fio_public_key = $key;
    } else {
        $fio_public_key = getActiveKey($client, $account);
        if ($fio_public_key != "") {
            $key_to_account[$fio_public_key] = $account;
        }
    }
    $fio_address = "";
    if (!array_key_exists($account, $account_to_fio_address)) {
        $fio_address = getFIOAddress($client, $fio_public_key);
        if ($fio_address != "") {
            $account_to_fio_address[$account] = $fio_address;
        }
    } else {
        $fio_address = $account_to_fio_address[$account];
    }
    print '|' . $count . '|' . getBloksLink($account,$fio_address) . ': | ' . number_format($amount) . '|';

    $balances = array();
    $account_details = getAccountDetails($client, $fio_public_key);
    $account_details = processTransactions($config, $account_details, 0, 1000);
    asort($balances);
    $deposits = array_filter($balances, function ($v) {
      return $v < 0;
    });
    $top_inbound = array_slice($deposits, 0, 3);
    foreach ($top_inbound as $inbound_account => $inbound_amount) {
        $inbound_fio_address = "";
        if (!array_key_exists($inbound_account, $account_to_fio_address)) {
            $inbound_public_key = "";
            $key = array_search($inbound_account,$key_to_account);
            if ($key !== false) {
                $inbound_public_key = $key;
            } else {
                $inbound_public_key = getActiveKey($client, $inbound_account);
                if ($inbound_public_key != "") {
                    $key_to_account[$inbound_public_key] = $inbound_account;
                }
            }
            if ($inbound_public_key != "") {
                $inbound_fio_address = getFIOAddress($client, $inbound_public_key);
                if ($inbound_fio_address != "") {
                    $account_to_fio_address[$inbound_account] = $inbound_fio_address;
                }
            }
        } else {
            $inbound_fio_address = $account_to_fio_address[$inbound_account];
        }
        print getBloksLink($inbound_account,$inbound_fio_address) . " (" . number_format($inbound_amount) . ")<br />";
    }
    print "|\n";

}

$data_for_file = serialize($account_to_fio_address);
file_put_contents($account_to_fio_address_filename,$data_for_file);

$data_for_file = serialize($key_to_account);
file_put_contents($key_to_account_filename,$data_for_file);

function getBloksLink($account, $display = "") {
    if ($display == "") {
        $display = $account;
    }
    return "[" . $display . "](https://fio.bloks.io/account/" . $account. ")";
}

function array_median($arr) {
    $count = count($arr); //total numbers in array
    $middleval = floor(($count-1)/2); // find the middle value, or the lowest middle value
    if($count % 2) { // odd number, middle is the median
        $median = array_slice($arr, $middleval, 1);
        $median = array_pop($median);
    } else { // even number, calculate avg of 2 medians
        $low = array_slice($arr, $middleval, 1);
        $low = array_pop($low);
        $high = array_slice($arr, $middleval+1, 1);
        $high = array_pop($high);
        $median = (($low+$high)/2);
    }
    return $median;
}
