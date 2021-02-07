<?php

/**
 * @see       https://github.com/lukestokes/fio-client for the canonical source repository
 * @copyright https://github.com/lukestokes/fio-php-sdk/blob/main/copyrght-or-license-file
 * @license   https://github.com/lukestokes/fio-php-sdk/blob/main/need-to-pick-a-license
 */

namespace xtypeTest\Fio;

use xtype\Fio\Ecc;
use PHPUnit\Framework\TestCase;

class EccTest extends TestCase
{
    public function testSignAndVerify()
    {
        $privKey = '5KQvfsPJ9YvGuVbLRLXVWPNubed6FWvV8yax6cNSJEzB4co3zFu';
        $public = Ecc::privateToPublic($privKey, 'FIO');
        $string = 'I like turtles';
        $sha256 = Ecc::sha256($string);
        $gensig = Ecc::signHash($sha256, $privKey);
        $this->assertTrue(Ecc::verifyHash($sha256, $gensig, $public, "FIO"));
        $goodsig = "SIG_K1_KYzSWVRXhNJtNZa5pwuFqoMi1J12n2hVsQv4bKxxFSSUa2MiGNCFuBP1wARST7wWDTCSJx19ey9cvpGKwX3MxKzhcfVNb2";
        $this->assertTrue(Ecc::verifyHash($sha256, $goodsig, $public, "FIO"));
        $badsig = "SIG_K1_KjmDiKnkgyQBt5r1oC9ANffNuN8UtpWsRca4X899nzDPoyNZnFk1yp5R8m4pT3zUpcvgCpeVnzAiZTsTFSvZaAErgfQc4n";
        $this->assertFalse(Ecc::verifyHash($sha256, $badsig, $public, "FIO"));
    }
}