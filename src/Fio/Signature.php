<?php

namespace xtype\Fio;

use Elliptic\Curve\PresetCurve;
use Elliptic\EC\KeyPair;
use Elliptic\EC\Signature as ECSignature;
use Elliptic\HmacDRBG;
use BN\BN;
use Elliptic\EC;

/**
 * Class Signature
 * @package xtype\Fio
 */
class Signature
{
    public $ec;

    /**
     * Signature constructor.
     */
    public function __construct()
    {
        $this->ec = new EC('secp256k1');
    }

    /**
     * @param $data
     * @param $key
     * @param $i
     * @return ECSignature
     * @throws \Exception
     */
    public function sign($data, $key, $i)
    {
        $key = $this->ec->keyFromPrivate($key, []);
        if ($i) {
            $msg = hash('sha256', hex2bin($data . str_pad('', $i * 2, '0')));
        } else {
            $msg = $data;
        }
        $msg = $this->_truncateToN(new BN($msg, 16));
        $data = $this->_truncateToN(new BN($data, 16));

        // Zero-extend key to provide enough entropy
        $bytes = $this->ec->n->byteLength();
        $bkey = $key->getPrivate()->toArray("be", $bytes);

        // Zero-extend nonce to have the same byte size as N
        $nonce = $msg->toArray("be", $bytes);

        $options = [];
        $kFunc = null;
        if( isset($options["k"]) )
            $kFunc = $options["k"];
        else
        {
            // Instatiate HmacDRBG
            $drbg = new HmacDRBG(array(
                "hash" => $this->ec->hash,
                "entropy" => $bkey,
                "nonce" => $nonce,
                "pers" => "",
                "persEnc" => false
            ));

            $kFunc = function($iter) use ($drbg, $bytes) {
                return new BN($drbg->generate($bytes));
            };
        }

        // Number of bytes to generate
        $ns1 = $this->ec->n->sub(new BN(1));

        $canonical = true;
        for($iter = 0; true; $iter++)
        {
            $k = $kFunc($iter);
            $k = $this->_truncateToN($k, true);
            // var_dump($k);
            if( $k->cmpn(1) <= 0 || $k->cmp($ns1) >= 0 )
                continue;

            $kp = $this->ec->g->mul($k);
            if( $kp->isInfinity() )
                continue;

            $kpX = $kp->getX();
            $r = $kpX->umod($this->ec->n);
            if( $r->isZero() )
                continue;

            $s = $k->invm($this->ec->n)->mul($r->mul($key->getPrivate())->iadd($data));
            $s = $s->umod($this->ec->n);
            if( $s->isZero() )
                continue;

            $recoveryParam = ($kp->getY()->isOdd() ? 1 : 0) | ($kpX->cmp($r) !== 0 ? 2 : 0);

            // Use complement of `s`, if it is > `n / 2`
            if( $canonical && $s->cmp($this->ec->nh) > 0 )
            {
                $s = $this->ec->n->sub($s);
                $recoveryParam ^= 1;
            }

            return new ECSignature(array(
                "r" => $r,
                "s" => $s,
                "recoveryParam" => $recoveryParam
            ));
        }
    }

    public function verify($dataSha256, $signature, $key, $enc = 'hex')
    {
        $i = substr($signature, 0, 2);
        $r = substr($signature, 2, 64);
        $s = substr($signature, 66, 64);

        $m = $dataSha256;

        try {
          $msg = $this->_truncateToN(new BN($m, 16));
        } catch (\Exception $e) {
          $m = hash('sha256', hex2bin($m . str_pad('', $i * 2, '0')));
          $msg = $this->_truncateToN(new BN($msg, 16));
        }

        $key = $this->ec->keyFromPublic($key, $enc);

        $n = $this->ec->n;

        $signature = new ECSignature([
            'r' => $r,
            's' => $s
        ]);

        // Perform primitive values validation
        $r = $signature->r;
        $s = $signature->s;

        if( $r->cmpn(1) < 0 || $r->cmp($n) >= 0 )
            return false;
        if( $s->cmpn(1) < 0 || $s->cmp($n) >= 0 )
            return false;

        // Validate signature
        $sinv = $s->invm($n);
        $u1 = $sinv->mul($msg)->umod($n);
        $u2 = $sinv->mul($r)->umod($n);

        $p = $this->ec->g->mulAdd($u1, $key->getPublic(), $u2);
        if( $p->isInfinity() )
            return false;
        return $p->getX()->umod($n)->cmp($r) === 0;
    }

    /**
     * @param $msg
     * @param bool $truncOnly
     * @return mixed
     */
    private function _truncateToN($msg, $truncOnly = false)
    {
        $delta = intval(($msg->byteLength() * 8) - $this->ec->n->bitLength());
        if( $delta > 0 ) {
            $msg = $msg->ushrn($delta);
        }
        if( $truncOnly || $msg->cmp($this->ec->n) < 0 )
            return $msg;

        return $msg->sub($this->ec->n);
    }
}
