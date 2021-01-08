<?php
/**
 * @see       https://github.com/lukestokes/fio-client for the canonical source repository
 * @copyright https://github.com/lukestokes/fio-php-sdk/blob/main/copyrght-or-license-file
 * @license   https://github.com/lukestokes/fio-php-sdk/blob/main/need-to-pick-a-license
 */

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

include "includes/helper_functions.php";
include "includes/fio.address_regdomain.example.php";
include "includes/fio.address_regaddress.example.php";
include "includes/fio.address_addaddress.example.php";
