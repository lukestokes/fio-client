<?php

/**
 * @see       https://github.com/lukestokes/fio-client for the canonical source repository
 * @copyright https://github.com/lukestokes/fio-php-sdk/blob/main/copyrght-or-license-file
 * @license   https://github.com/lukestokes/fio-php-sdk/blob/main/need-to-pick-a-license
 */

namespace xtypeTest\Fio;

use xtype\Fio\Utils;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    private $keyTypes = [
        'sha256x2',
        'badKeyType',
    ]; // @todo are there other valid keytypes to test with?

    private $algorithms = ['sha256', 'ripemd160', 'badAlgorithm']; // @todo others?

    public function testHex2BinCorrectlyPadsOddLengthInput()
    {
        // conveniently, Decimal 10 / ASCII A is line feed (\n)
        $this->assertSame(
            "\nAn odd length input string",
            Utils::hex2bin('a416e206f6464206c656e67746820696e70757420737472696e67')
        );

        $this->assertSame(
            'An input string of even length',
            Utils::hex2bin('416e20696e70757420737472696e67206f66206576656e206c656e677468')
        );
    }

    public function testSubstringReturnsWhat()
    {
        // Is 'end' parameter supposed to be the ending character position, or the length of the substring?
        $this->assertSame('abcde', Utils::substring('abcdef', 0, 5));
        $this->assertSame('e', Utils::substring('abcdef', 4,5));

        $this->assertSame('f', Utils::substring('abcdef', -1, 0));
        // But if I want 'f", substring parm names imply you'd call:
        // Utils::substring('abcdef', 5, 5);
        // which actually yields an empty string
        $this->assertSame('', Utils::substring('abcdef', 5, 5));
    }

    public function testHmacSha256()
    {
        $plaintext = 'Make sure it works correctly';
        $sharedSecretKey = 'donttell';
        $hashedString = hash_hmac('sha256', $plaintext, $sharedSecretKey, true);
        $this->assertSame($hashedString, Utils::hmacSha256($sharedSecretKey, $plaintext));
    }

    public function testAes256CbcPkcs7EncryptHappyPath()
    {
        $cipherAlgo = 'AES-256-CBC';
        $plainText = 'Make sure it works correctly';
        $passphrase = 'donttell';
        $initVectorLength = openssl_cipher_iv_length($cipherAlgo);
        $cryptographicallyStrong = false;

        $initVector = openssl_random_pseudo_bytes(
            $initVectorLength, $cryptographicallyStrong
        );
        $this->assertTrue($cryptographicallyStrong);

        $encryptedText = openssl_encrypt(
            $plainText,
            $cipherAlgo,
            $passphrase,
            OPENSSL_RAW_DATA,
            $initVector
        );

        $this->assertSame(
            $encryptedText,
            Utils::aes256CbcPkcs7Encrypt($plainText, $passphrase, $initVector)
        );
    }

    /** Beware. The class method will happily use a cryptographically weak
     * initialization vector
     *
     * @todo Make sure the class at least warns when the IV is weak
     */
    public function testAes256CbcPkcs7EncryptWithWeakInitVector()
    {
        $cipherAlgo = 'AES-256-CBC';
        $plainText = 'Make sure it works correctly';
        $passphrase = 'donttell';
        $initVectorLength = openssl_cipher_iv_length($cipherAlgo);

        $crappyInitVector = str_repeat(' ', $initVectorLength);

        $encryptedText = openssl_encrypt(
            $plainText,
            $cipherAlgo,
            $passphrase,
            OPENSSL_RAW_DATA,
            $crappyInitVector
        );

        $this->assertSame(
            $encryptedText,
            Utils::aes256CbcPkcs7Encrypt($plainText, $passphrase, $crappyInitVector)
        );
    }

}
