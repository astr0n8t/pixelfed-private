<?php

namespace Tests\Unit;

use phpseclib3\Crypt\RSA;
use Tests\TestCase;

class CryptoTest extends TestCase
{
    /**
     * A basic test to check if PHPSecLib is installed.
     *
     * @return void
     */
    public function testLibraryInstalled()
    {
        $this->assertTrue(class_exists('\phpseclib3\Crypt\RSA'));
    }

    public function testRSASigning()
    {
        $private = RSA::createKey();
        $publicKey = $private->getPublicKey();

        $plaintext = 'pixelfed rsa test';
        $signature = $private->sign($plaintext);

        $this->assertTrue($publicKey->verify($plaintext, $signature));
    }
}
