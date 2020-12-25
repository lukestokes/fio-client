<?php
require_once __DIR__ . '/../vendor/autoload.php';

use xtype\Fio\Client;
use xtype\Fio\Ecc;

$client = new Client('http://fio.greymass.com');

//$client = new Client('http://testnet.fioprotocol.io');

$lowerBound = 0;
$upperBound = -1;
$limit = 100;

$params = array(
    "json" => true,
    "code" => 'eosio',
    "scope" => 'eosio',
    "table" => 'lockedtokens',
    "limit" => $limit,
    "lower_bound" => $lowerBound,
    "upper_bound" => $upperBound
  );

$results = $client->chain()->getTableRows($params);
$locked_data = array();
$locked_data = processResults($results,$locked_data);
$lowerBound = $results->rows[count($results->rows)-1]->owner;
$has_more_entries = $results->more;
$params['lower_bound'] = $lowerBound;
while ($has_more_entries) {
    $results = $client->chain()->getTableRows($params);
    //var_dump($results);
    //var_dump($results->rows);
    $lowerBound = $results->rows[count($results->rows)-1]->owner;
    $params['lower_bound'] = $lowerBound;
    $locked_data = processResults($results, $locked_data);
    $has_more_entries = $results->more;
}

function processResults($results, $locked_data) {
    foreach ($results->rows as $row) {
        if ($row->grant_type != 4) {
            if ($row->unlocked_period_count > 1) {
                $entry = array();
                $entry['owner'] = $row->owner;
                $entry['unlocked_period_count'] = $row->unlocked_period_count;
                $entry['grant_type'] = $row->grant_type;
                $entry['total_grant_amount'] = $row->total_grant_amount;
                $entry['remaining_locked_amount'] = $row->remaining_locked_amount;
                $entry['inhibit_unlocking'] = $row->inhibit_unlocking;
                $locked_data[] = $entry;
            }
        }
    }
    return $locked_data;
}

print "==== " . count($locked_data) . " Accounts On Second Unlock ====\n";

foreach ($locked_data as $entry) {
    $locked = bcdiv($entry['remaining_locked_amount'],1000000000);

    print $entry['owner'] . ": Grant Type " . $entry['grant_type'] . ", Grant: " . number_format(bcdiv($entry['total_grant_amount'],1000000000)) . " Locked: " . number_format($locked) . " Correct Locked: ";
    $correct_amount = bcsub(bcsub($entry['total_grant_amount'],bcmul($entry['total_grant_amount'],0.06)),bcmul($entry['total_grant_amount'],0.188));
    $correct_amount = bcdiv($correct_amount,1000000000);
    print number_format($correct_amount);
    $difference = $locked - $correct_amount;
    if ($difference < 2) {
        print " CORRECT";
    } else {
        print " Difference: " . number_format($difference);
    }
    print "\n";
}

//var_dump($locked_data);