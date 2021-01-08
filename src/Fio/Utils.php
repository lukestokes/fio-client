<?php

/**
 * @see       https://github.com/lukestokes/fio-client for the canonical source repository
 * @copyright https://github.com/lukestokes/fio-php-sdk/blob/main/copyrght-or-license-file
 * @license   https://github.com/lukestokes/fio-php-sdk/blob/main/need-to-pick-a-license
 */

namespace xtype\Fio;

use StephenHill\Base58;

class Utils
{
    /**
     * 检查并返回十六进制私钥
     * @param $key
     * @param string $keyType
     * @return string
     * @throws \Exception
     */
    public static function checkDecode($key, $keyType = 'sha256x2')
    {
        $b58 = new Base58();
        $keyBin = $b58->decode($key);
        $key = substr($keyBin, 0, -4);
        // check
        $checksum = substr($keyBin, -4);

        if ($keyType === 'sha256x2') {
            // legacy
            $newCheck = substr(hash('sha256', hash('sha256', $key, true), true), 0, 4);
        } else {
            $check = $key;
            if ($keyType) {
                $check .= $keyType;
            }
            $newCheck = substr(hash('ripemd160', $check, true), 0, 4); //PVT
        }
        if ($checksum !== $newCheck) {
            throw new \Exception('The private key is error.', 1);
        }

        return bin2hex($key);
    }

    /**
     * @param $bin
     * @param string $keyType
     * @return string
     * @throws \Exception
     */
    public static function checkEncode($bin, $keyType = 'sha256x2')
    {
        $b58 = new Base58();
        if ($keyType === 'sha256x2') {
            // legacy
            $checksum = substr(hash('sha256', hash('sha256', $bin, true), true), 0, 4);

            return $b58->encode($bin . $checksum);
        } else {
            $check = $bin;
            if ($keyType) {
                $check .= $keyType;
            }
            $_checksum = substr(hash('ripemd160', $check, true), 0, 4); //PVT

            return $b58->encode($bin . $_checksum);
        }
    }


    // Begin added from https://github.com/XB0CT/eceis/blob/master/src/ECIES.php

    /**
     * This is a "safe" hex2bin(), which otherwise yields PHP_ERROR on odd-length input
     *
     * @param  string $str
     *
     * @return string
     */
    public static function hex2bin(string $str): string {
        return hex2bin(strlen($str) % 2 == 1 ? "0" . $str : $str);
    }

    /**
     * The parameter names imply this function yields, from a subject string,
     * the substring beginning at position 'start' and ending at position 'end',
     * but that is not what it does.
     *
     * Is 'end' supposed to be the ending character position, or the length of
     * the substring? See the unit tests in tests/Fio/UtilsTest.php
     *
     * The result is a harder to use version of substring than the native substr()
     *
     * @see tests/Fio/UtilsTest.php
     *
     * @param   string  $str
     * @param   int     $start
     * @param   int     $end
     *
     * @return string
     */
    public static function substring(string $str, int $start, int $end): string {
        return substr($str, $start, $end - $start);
    }

    public static function hmacSha256(string $key, string $data): string {
        return hash_hmac("sha256", $data, $key, true);
    }

    /**
     * Beware. This method will happily use a cryptographically weak
     * initialization vector
     *
     * @todo Make sure Utils at least warns when the IV is weak, or prevents
     *       it's use.
     *
     * @param $key
     * @param $data
     * @param $iv
     *
     * @return string
     */
    public static function aes256CbcPkcs7Encrypt(string $data, string $key, string $iv): string {
        return openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * @param $data
     * @param $key
     * @param $iv
     *
     * @return string
     */
    public static function aes256CbcPkcs7Decrypt(string $data, string $key, string $iv): string {
        /*
        var_dump([
            'method' => 'aes256CbcPkcs7Decrypt',
            'data' => bin2hex($data),
            'key' => bin2hex($key),
            'iv' => bin2hex($iv)
        ]);
        */
        return openssl_decrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }

    // End added from https://github.com/XB0CT/eceis/blob/master/src/ECIES.php

}
