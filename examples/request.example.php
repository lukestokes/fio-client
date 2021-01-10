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
$iv = xtype\Fio\Ecc::makeIV();
$content_buffer = $iv;
$shared = xtype\Fio\Ecc::getSharedKey($ec, $user['privateKey'],$user2['publicKey']);
//print $shared . "\n";

print "Data to encrypt: " . $content_string . "\n";

$encrypted_content = xtype\Fio\Ecc::encrypt($content_string,$shared,$iv);

$content_buffer .= $encrypted_content;

// print "encrypted content: " . $encrypted_content . "\n";

$decrypted_content = xtype\Fio\Ecc::decrypt($encrypted_content,$shared);

print "Decrypted Content: " . $decrypted_content . "\n";


/*


* Create a buffer to store each part
* Create a random 16 byte IV
  - write to the buffer
* Get the shared key and hmac key:
  - A key is built through scalar multiplication of the pub and priv keys. (aka ECDH key exchange)
  - This key is hashed _two times_ using sha512
  - the first 32 bytes are used as the AES key, the second are the hmac key
* ABI encode the json

do I need to do this or does the transaction library do it already? Is this just the content json or the whole json for the transaction?

* Add pkcs7 padding to align with AES-256 block size (16 bytes I think?)

not sure if my encrypt method already does this or not

* Encrypt using AES-256-CBC, with the first 32 bytes from the shared secret and the IV
  - write the result to the buffer

I think I've got this part correct

* Create a SHA-512 hmac (sign the entire buffer, including the IV) using the last 32 bytes from the shared secret as the key.
  - write the result to the buffer


  
* Base64 encode the buffer, and that's what goes into the 'content' field of the action data.



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