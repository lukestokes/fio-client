<?php

print "Creating domain " . $user['domain'] . "...\n";
$max_fee = getMaxFee("register_fio_domain","");
checkAvailAndPrint($user['domain']);
try {
    $tx = $client->transaction([
        'actions' => [
            [
                'account' => 'fio.address',
                'name' => 'regdomain',
                'authorization' => [[
                    'actor' => $user['actor'],
                    'permission' => 'active',
                ]],
                'data' => [
                     "fio_domain" => $user["domain"],
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