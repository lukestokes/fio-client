<?php

print "Registering address " . $user['address'] . "...\n";
$max_fee = getMaxFee("register_fio_address",$user["address"]);
checkAvailAndPrint($user["address"]);
try {
    $tx = $client->transaction([
        'actions' => [
            [
                'account' => 'fio.address',
                'name' => 'regaddress',
                'authorization' => [[
                    'actor' => $user['actor'],
                    'permission' => 'active',
                ]],
                'data' => [
                     "fio_address" => $user["address"],
                     "owner_fio_public_key" => $user["publicKey"],
                     "max_fee" => $max_fee,
                     "tpid" => '',
                     "actor" => $user['actor'],
                ],
            ]
        ]
    ]);
    echo "Transaction ID: {$tx->transaction_id}\n";
} catch(\Exception $e) {
    print "Error: " . $e->getMessage() . "\n";
}
