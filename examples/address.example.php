<?php
require_once __DIR__ . '/../vendor/autoload.php';

use xtype\Fio\Client;
use xtype\Fio\Ecc;

//$client = new Client('http://fio.greymass.com');
$client = new Client('http://testnet.fioprotocol.io');

/*

Instructions:

1. Create a key pair here: https://monitor.testnet.fioprotocol.io/#createKey and add the values to the $user variable below.

2. Hit up the faucet here: https://monitor.testnet.fioprotocol.io/#faucet to give your account some testnet tokens

3. View the transaction results here: https://fio-test.bloks.io/

*/

$user = ["privateKey" => '5KSnYfgM4EGyBv4eoFx7YGWvJvh3RFAqXgaS3pVNnz8tj619aRV',
  "publicKey" => 'FIO8WdwymB8DZ2NhanB1KgDPDo64Vcqj9UQZLaeP4HRwgyRyC9qdL',
  "actor" => 'xegfqq2qiyjt',
  "domain" => 'fioclient',
  "address" => 'testaddress'
];

$get_fees = true;

/*****************************************************************************************/

$random = rand(1,100000);
$user['domain'] .= $random;
$user['address'] .= $random . "@" . $user['domain'];

print "Using account " . $user['actor'] . "...\n";

$client->addPrivateKeys([
    $user['privateKey']
]);

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