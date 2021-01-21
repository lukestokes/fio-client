<?php

// Helper Functions

function getMaxFee($endpoint,$fio_address) {
    global $get_fees, $client;
    $max_fee = 800000000000;
    if ($get_fees) {
        try {
            $result = $client->chain()->getFee(["end_point" => $endpoint, "fio_address" => $fio_address]);
            $max_fee = $result->fee;
        } catch(\Exception $e) {
            print "getFee Error: " . $e->getMessage() . "\n";
        }
    }
    return $max_fee;
}

function getFIOAddress($client, $fio_public_key) {
    $fio_address = "";
    $params = array(
        "fio_public_key" => $fio_public_key,
        "limit" => 1,
        "offeset" => 0
    );
    try {
        $addresses = $client->chain()->getFioAddresses($params);
        $fio_address = $addresses->fio_addresses[0]->fio_address;
    } catch(\Exception $e) { }
    return $fio_address;
}

function checkAvailAndPrint($fio_name) {
    global $get_fees, $client;
    print "Is $fio_name available? ";
    try {
        $result = $client->chain()->availCheck(["fio_name" => $fio_name]);
    } catch(\Exception $e) {
        print "availCheck Error: " . $e->getMessage() . "\n";
    }
    if (!$result->is_registered) {
        print "YES";
    } else {
        print "NO";
    }
    print "\n";
}

function getFIOAmount($quantity) {
    $amount = explode(" ", $quantity);
    $amount = $amount[0];
    return $amount;
}

function getAccountDetails($client, $fio_public_key) {
    $params = array(
        "fio_public_key" => $fio_public_key
    );
    try {
        $response = $client->chain()->getActor($params);
    } catch(\Exception $e) { }
    $account_name = $response->actor;
    $params = array(
        "json" => true,
        "code" => 'fio.token',
        "account" => $account_name,
        "symbol" => 'FIO'
      );
    $response = $client->chain()->getCurrencyBalance($params);
    $current_balance = getFIOAmount($response[0]);
    $account_details = array(
        "account_name" => $account_name,
        "created_in_block_num" => 0,
        "fio_public_key" => $fio_public_key,
        "current_balance" => $current_balance,
        "fio_address" => "",
        "display_name" => $account_name,
        "transfers_in" => 0,
        "transfers_out" => 0,
        "transfers" => array()
    );
    $account_details["fio_address"] = getFIOAddress($client, $fio_public_key);
    if ($account_details["fio_address"] != "") {
        $account_details["display_name"] = $account_details["fio_address"];
    }
    return $account_details;
}

/*
Config looks like:

$config = array(
"client" => $client,
"start_date" => $start_date,
"end_date" => $end_date
"show_progress" => false,
"debug_block" => -1,
"include_fees" => false,
"transaction_action_processor" => $transaction_action_processor // anonymous function
)

$transaction_action_processor = function($config['client'], $account_details, $action, $is_fee);

Account Details have the following structure (from getAccountDetails call)

$account_details = array(
    "account_name" => $account_name,
    "created_in_block_num" => 0,
    "fio_public_key" => $fio_public_key,
    "current_balance" => $current_balance,
    "fio_address" => "",
    "display_name" => $account_name,
    "transfers_in" => 0,
    "transfers_out" => 0,
    "transfers" => array()
);

With transfers looking like:

$transfer = array(
    'date' => $action->block_time,
    'amount' => $amount,
    'address_display' => $address,
    'trx_id' => $action->action_trace->trx_id
);

*/
function processTransactions($config, $account_details, $pos, $offset) {
    if ($config['show_progress']) {
        print $pos . "...";
    }
    $params = array(
        "account_name" => $account_details["account_name"],
        "pos" => $pos,
        "offset" => $offset
      );
    $actions = $config['client']->history()->getActions($params);
    // for debugging. Set $config['debug_block'] to a block number to dump the actions and exit.
    $block_found = false;
    if (count($actions->actions) > 0) {
        foreach ($actions->actions as $action) {
            // BEGIN for debugging (dump block and exit)
            if ($config['debug_block'] != -1) {
                if ($action->block_num == $config['debug_block']) {
                    if (!$block_found) {
                        $block_found = $config['debug_block'];
                    }
                    var_dump($action);
                }
                if ($block_found && $action->block_num != $block_found) {
                    die();
                }
                if ($block_found) {
                    $block_found = $action->block_num;
                }
            }
            // END debugging
            $include_action = true;
            if (date("Y-m-d",strtotime($action->block_time)) < $config['start_date']) {
                $include_action = false;
            }
            if (date("Y-m-d",strtotime($action->block_time)) >= $config['end_date']) {
                $include_action = false;
            }
            if ($include_action) {
                if ($action->action_trace->act->account == "fio.token"
                        && $action->action_trace->act->name == "trnsfiopubky") {
                    $include = false;
                    if ($action->action_trace->receipt->receiver == $account_details["account_name"]) {
                        $include = true;
                        if (!$account_details["created_in_block_num"]) {
                            $account_details["created_in_block_num"] = $action->block_num;
                        }
                    }
                    if (!$include && !$account_details["created_in_block_num"]) {
                        $include = true;
                        $account_details["created_in_block_num"] = $action->block_num;
                    }
                    if ($include) {
                        $account_details = $config['transaction_action_processor']($config['client'], $account_details, $action, false);
                    }
                } elseif ($action->action_trace->act->account == "fio.token"
                    && $action->action_trace->act->name == "transfer"
                    && $action->action_trace->receipt->receiver == $account_details["account_name"]
                    && $config['include_fees']) {
                    $account_details = $config['transaction_action_processor']($config['client'], $account_details, $action, true);
                }
            }
        }
        $pos = $pos + count($actions->actions);
        $account_details = processTransactions($config, $account_details, $pos, $offset);
    }
    return $account_details;
}

