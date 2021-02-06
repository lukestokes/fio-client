<?php

namespace xtype\Fio;

use Elliptic\EC;
use Elliptic\EC\Signature as ECSignature;

class Ecc
{
    /**
     * Wif private key To private hex
     * @param $privateKey
     * @return bool|string
     * @throws \Exception
     */
    public static function wifPrivateToPrivateHex($privateKey)
    {
        return substr(Utils::checkDecode($privateKey), 2);
    }

    /**
     * Private hex To  wif private key
     * @param $privateHex
     * @return string
     * @throws \Exception
     */
    public static function privateHexToWifPrivate($privateHex)
    {
        return Utils::checkEncode(hex2bin('80' . $privateHex));
    }

    /**
     * 私钥转公钥
     * @param $privateKey
     * @param string $prefix
     * @return string
     * @throws \Exception
     */
    public static function privateToPublic($privateKey, $prefix = 'FIO')
    {
        // wif private
        $privateHex = self::wifPrivateToPrivateHex($privateKey);
        // var_dump($privateHex);
        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate($privateHex);
        return $prefix . Utils::checkEncode(hex2bin($key->getPublic(true, 'hex')), null);
    }

    public static function publicKeyDecode(string $publicKey, string $prefix = 'FIO')
    {
        return Utils::checkDecode(ltrim($publicKey, $prefix), null);
    }

    /**
     * 随机生成私钥
     * @param bool $wif
     * @return string|null
     * @throws \Exception
     */
    public static function randomKey($wif = true)
    {
        $ec = new EC('secp256k1');
        $kp = $ec->genKeyPair();
        if ($wif) {
            return self::privateHexToWifPrivate($kp->getPrivate('hex'));
        }
        return $kp->getPrivate('hex');
    }

    /**
     * 根据种子生产私钥
     * @param $seed
     * @param bool $wif
     * @return string
     * @throws \Exception
     */
    public static function seedPrivate($seed, $wif = true)
    {
        $secret = hash('sha256', $seed);
        if ($wif) {
            return self::privateHexToWifPrivate($secret);
        }
        return $secret;
    }

    /**
     * 是否是合法公钥
     * @param string $public
     * @param string $prefix
     * @return bool
     */
    public static function isValidPublic($public, $prefix = 'EOS')
    {
        if (strtoupper(substr($public, 0, 3)) == strtoupper($prefix)) {
            try {
                Utils::checkDecode(substr($public, 3), null);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }
        return false;
    }

    /**
     * 是否是合法wif私钥
     * @param string $privateKey
     * @return bool
     */
    public static function isValidPrivate($privateKey)
    {
        try {
            self::wifPrivateToPrivateHex($privateKey);
            return true;
        } catch(\Exception $e) {
            return false;
        }
    }

    /**
     * 签名
     * @param string $data
     * @param string $privateKey
     * @return string
     * @throws \Exception
     */
    public static function sign($data, $privateKey)
    {
        $dataSha256 = hash('sha256', hex2bin($data));
        return self::signHash($dataSha256, $privateKey);
    }

    /**
     * 对hash进行签名
     * @param string $dataSha256
     * @param string $privateKey
     * @return string
     * @throws \Exception
     */
    public static function signHash($dataSha256, $privateKey)
    {
        $privHex = self::wifPrivateToPrivateHex($privateKey);
        $ecdsa = new Signature();

        $nonce = 0;
        while (true) {
            // Sign message (can be hex sequence or array)
            $signature = $ecdsa->sign($dataSha256, $privHex, $nonce);
            // der
            $der = $signature->toDER('hex');
            // Switch der
            $lenR = hexdec(substr($der, 6, 2));
            $lenS = hexdec(substr($der, (5 + $lenR) * 2, 2));
            // Need 32
            if ($lenR == 32 && $lenS == 32) {
                $r = $signature->r->toString('hex');
                $s = $signature->s->toString('hex');
                $i = dechex($signature->recoveryParam + 4 + 27);
                break;
            }

            $nonce++;
            if ($nonce % 10 == 0) {
                throw new \Exception('Signature failed', 1);
            }
        }

        return 'SIG_K1_' . Utils::checkEncode(hex2bin($i . str_pad($r, 64, '0', STR_PAD_LEFT) . str_pad($s, 64, '0', STR_PAD_LEFT)), 'K1');
    }

    /**
     * Verify signed data.
     */
    public static function verify(string $data, string $signature, string $pubkey, string $prefix = 'FIO')
    {
        $data = hash('sha256', hex2bin($data));
        return self::verifyHash($data, $signature, $pubkey);
    }

    public static function verifyHash(string $dataSha256, string $signature, string $pubkey, string $prefix = 'FIO')
    {
        $keyString = substr($signature, 7);
        $signature = Utils::checkDecode($keyString, 'K1');
        $pubkey = self::publicKeyDecode($pubkey, $prefix);

        $ecdsa = new Signature();

        return $ecdsa->verify($dataSha256, $signature, $pubkey);
    }

    /**
     * Recover the public key used to create the signature.
     */
    public static function recover()
    {
        // TODO::
    }

    /**
     * Recover hash
     */
    public static function recoverHash()
    {
        // TODO::
    }

    /**
     * sha256 hash
     * @param $data
     * @param string $encoding
     */
    public static function sha256($data, $encoding = 'hex')
    {
        $rawOutput = false;
        if ($encoding != 'hex') {
            $rawOutput = true;
        }
        return hash('sha256', $data, $rawOutput);
    }

    public static function getSharedKey($ec, $FIOPrivateKeyString, $FIOPublicKeyString) {
        $userPrivateKey = $ec->keyFromPrivate(self::wifPrivateToPrivateHex($FIOPrivateKeyString));
        $publicKeyHex = Utils::checkDecode(substr($FIOPublicKeyString, 3), null);
        $user2PubKey = $ec->keyFromPublic($publicKeyHex, "hex");
        $shared = $userPrivateKey->derive($user2PubKey->getPublic());
        $bin = Utils::hex2bin( $shared->toString("hex") );
        
        // should this sha512 happen twice?

        return hash("sha512", $bin, true);
    }

    // Begin added (and modified) from https://github.com/XB0CT/eceis/blob/master/src/ECIES.php

    public static function makeIV() {
        $efforts = 0;
        $maxEfforts = 50;
        $wasItSecure = false;

        do {
            $efforts+=1;
            $iv = openssl_random_pseudo_bytes(16, $wasItSecure);
            if ($efforts == $maxEfforts) {
                    throw new Exception('Unable to genereate secure iv.');
                    break;
            }
        } while (!$wasItSecure);

        return $iv;
    }

    public static function encrypt($message, $sharedKey, $iv) {
        $kE = Utils::substring($sharedKey, 0, 32);
        $c = $iv . Utils::aes256CbcPkcs7Encrypt($message, $kE, $iv);
        $kM = Utils::substring($sharedKey, 32, 64);
        $d = Utils::hmacSha256($kM, $c);
        $d = Utils::substring($d, 0, 4);
        $encbuf = $c . $d;

        return $encbuf;
    }

    public static function decrypt($encbuf, $sharedKey) {
        $offset = 0;
        $tagLength = 4;
        $c = Utils::substring($encbuf, $offset, strlen($encbuf) - $tagLength);
        $d = Utils::substring($encbuf, strlen($encbuf) - $tagLength, strlen($encbuf));
        $kM = Utils::substring($sharedKey, 32, 64);
        $d2 = Utils::hmacSha256($kM, $c);
        /*
        var_dump([
            bin2hex($c),
            bin2hex($d),
            bin2hex($this->getkM()),
            bin2hex(Utils::substring($d2, 0, 4))
        ]);
        */
        $d2 = Utils::substring($d2, 0, 4);
        $equal = true;
        for ($i = 0; $i < strlen($d); $i++) {
            $equal &= ($d[$i] === $d2[$i]);
        }
        if (!$equal) {
            throw new \Exception("Invalid checksum");
        }
        $kE = Utils::substring($sharedKey, 0, 32);

        return Utils::aes256CbcPkcs7Decrypt(Utils::substring($c, 16, strlen($c)), $kE, Utils::substring($c, 0, 16));
    }

    // End added (and modified) from https://github.com/XB0CT/eceis/blob/master/src/ECIES.php

}
