<?php
require_once __DIR__ . '/../vendor/autoload.php';

use xtype\Fio\Client;
use xtype\Fio\Ecc;
use Elliptic\EC;

//$client = new Client('http://fio.greymass.com');
$client = new Client('http://testnet.fioprotocol.io');

/*

Instructions:

1. Create a key pair here: https://monitor.testnet.fioprotocol.io/#createKey and add the values to the $user variable below.

2. Hit up the faucet here: https://monitor.testnet.fioprotocol.io/#faucet to give your account some testnet tokens

3. View the transaction results here: https://fio-test.bloks.io/

*/

$user = ["privateKey" => '5J4R86EPMAjzLdfqJNgUDhY3vyAsbmZDXcgymQbkk9KbwZYHQR9',
  "publicKey" => 'FIO61ACkKcKBAT4ScNFsoJzAHKBwEi1JSeEGwjLS9ynQxCEufz3GV',
  "actor" => 'mruq3poorrab',
  "domain" => 'fioclient',
  "address" => 'testaddress'
];

$user2 = ["privateKey" => '5JoC7ruiRCBUYWs6jcY4BJzDMgD7R91mgDrcf5PcabibW5rpE5o',
  "publicKey" => 'FIO7BfdgWdvBQK15BnU14YMKLLgN8gfWETvt2i38Bwz4BE1xRkLMp',
  "actor" => 'i5gfypfk3ydv',
  "domain" => 'fioclient',
  "address" => 'testaddress'
];


$get_fees = true;

/*****************************************************************************************/

$random = rand(1,100000);
$user['domain'] .= $random;
$user['address'] .= $random . "@" . $user['domain'];

$random = rand(1,100000);
$user2['domain'] .= $random;
$user2['address'] .= $random . "@" . $user2['domain'];

print "Using account " . $user['actor'] . " and " . $user2['actor'] . "...\n";

$client->addPrivateKeys([
    $user['privateKey'],
    $user2['privateKey']
]);

include "includes/helper_functions.php";
/*
include "includes/fio.address_regdomain.example.php";
include "includes/fio.address_regaddress.example.php";
$user_backup = $user;
$user = $user2;
include "includes/fio.address_regdomain.example.php";
include "includes/fio.address_regaddress.example.php";
$user = $user_backup;
*/

print "Creating New Funds Request from address " . $user['address'] . " to " . $user2['address'] . "...\n";
$max_fee = getMaxFee("new_funds_request",$user["address"]);
$content = [
    'payee_public_address' => $user['publicKey'],
    'amount' => '1',
    'chain_code' => 'FIO',
    'token_code' => 'FIO',
    'memo' => "SHOW ME THE MONEY!!!",
    'hash' => null,
    'offline_url' => null
];
$content_string = json_encode($content);

print "Generating shared key using " . $user['actor'] . "'s private key and " . $user2['actor'] . "'s public key.\n";
$ec = new Elliptic\EC('secp256k1');
$shared = xtype\Fio\Ecc::getSharedKey($ec, $user['privateKey'],$user2['publicKey']);
//print $shared . "\n";

print "Data to encrypt: " . $content_string . "\n";

$encrypted_content = xtype\Fio\Ecc::encrypt($content_string,$shared);

// print "encrypted content: " . $encrypted_content . "\n";

$decrypted_content = xtype\Fio\Ecc::decrypt($encrypted_content,$shared);

print "Decrypted Content: " . $decrypted_content . "\n";


/*
try {
    $tx = $client->transaction([
        'actions' => [
            [
                'account' => 'fio.reqobt',
                'name' => 'newfundsreq',
                'authorization' => [[
                    'actor' => $user['actor'],
                    'permission' => 'active',
                ]],
                'data' => [
                     "payer_fio_address" => $user["address"],
                     "payee_fio_address" => $user2["address"],
                     "content" => "",
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
*/