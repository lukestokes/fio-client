<?php

print "Adding native blockchain public address mappings...\n";
$max_fee = getMaxFee("add_pub_address",$user["address"]);
try {
    $tx = $client->transaction([
        'actions' => [
            [
                'account' => 'fio.address',
                'name' => 'addaddress',
                'authorization' => [[
                    'actor' => $user['actor'],
                    'permission' => 'active',
                ]],
                'data' => [
                    'fio_address' => $user["address"],
                    'public_addresses' => [
                      [
                        'chain_code' => 'BCH',
                        'token_code' => 'BCH',
                        'public_address' => 'bitcoincash:qzf8zha74ahdh9j0xnwlffdn0zuyaslx3c90q7n9g9',
                      ],
                      [
                        'chain_code' => 'DASH',
                        'token_code' => 'DASH',
                        'public_address' => 'XyCyPKzTWvW2XdcYjPaPXGQDCGk946ywEv',
                      ]
                    ],
                    'max_fee' => $max_fee,
                    'tpid' => '',
                    'actor' => $user['actor'],
                ],
            ]
        ]
    ]);
    echo "Transaction ID: {$tx->transaction_id}\n";
} catch(\Exception $e) {
    print "Error: " . $e->getMessage() . "\n";
}