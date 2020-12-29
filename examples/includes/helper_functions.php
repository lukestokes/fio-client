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