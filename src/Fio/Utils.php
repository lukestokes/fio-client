<?php

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
     * @param string $str
     */
    public static function hex2bin($str) {
        return hex2bin(strlen($str) % 2 == 1 ? "0" . $str : $str);
    }

    /**
     * @param string $str
     * @param int $start
     * @param int $end
     */
    public static function substring($str, $start, $end) {
        return substr($str, $start, $end - $start);
    }

    /**
     * @param array $array
     * @param $key
     * @param bool $default
     */
    public static function arrayValue($array, $key, $default = false) {
        return array_key_exists($key, $array) ? $array[$key] : $default;
    }

    /**
     * @param $key
     * @param $data
     */
    public static function hmacSha256($key, $data) {
        return hash_hmac("sha256", $data, $key, true);
    }

    /**
     * @param $key
     * @param $data
     * @param $iv
     */
    public static function aes256CbcPkcs7Encrypt($data, $key, $iv) {
        return openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * @param $data
     * @param $key
     * @param $iv
     */
    public static function aes256CbcPkcs7Decrypt($data, $key, $iv) {
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
