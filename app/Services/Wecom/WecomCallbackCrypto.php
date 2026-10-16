<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Services\Wecom;

use App\Actions\Procurement\Settings\UpdateProcurementSettings;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;

/**
 * WeCom signs every callback with the token and encrypts its content with the EncodingAESKey,
 * both set on the chat archive page. The plain text carries 16 random bytes, its own length and
 * the Corp ID it was meant for, which is checked too: a message for another company is refused.
 */
class WecomCallbackCrypto
{
    private const int BLOCK_SIZE = 32;

    public function __construct(private readonly string $token, private readonly string $encodingAesKey, private readonly string $corpId)
    {
    }

    public static function forProcurement(Organisation $organisation): ?self
    {
        $wechat = Arr::get($organisation->settings, 'procurement.wechat', []);

        if (! UpdateProcurementSettings::usesWechat($organisation) || blank(Arr::get($wechat, 'corp_id')) || blank(Arr::get($wechat, 'callback_token')) || blank(Arr::get($wechat, 'encoding_aes_key'))) {
            return null;
        }

        return new self(Crypt::decryptString($wechat['callback_token']), Crypt::decryptString($wechat['encoding_aes_key']), $wechat['corp_id']);
    }

    public function hasValidSignature(string $signature, string $timestamp, string $nonce, string $encrypted): bool
    {
        $parts = [$this->token, $timestamp, $nonce, $encrypted];
        sort($parts, SORT_STRING);

        return hash_equals(sha1(implode('', $parts)), $signature);
    }

    public function decrypt(string $encrypted): string
    {
        $key       = (string) base64_decode($this->encodingAesKey.'=');
        $plainText = openssl_decrypt((string) base64_decode($encrypted), 'AES-256-CBC', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, substr($key, 0, 16));

        if ($plainText === false || $plainText === '') {
            throw new WecomArchiveException('The WeCom callback could not be decrypted; check the EncodingAESKey');
        }

        $padding = ord(substr($plainText, -1));

        if ($padding < 1 || $padding > self::BLOCK_SIZE) {
            throw new WecomArchiveException('The WeCom callback has invalid padding');
        }

        $content = substr($plainText, 16, -$padding);
        $length  = unpack('N', substr($content, 0, 4))[1];
        $message = substr($content, 4, $length);

        if (substr($content, 4 + $length) !== $this->corpId) {
            throw new WecomArchiveException('The WeCom callback is for another Corp ID');
        }

        return $message;
    }

    /**
     * Encrypts the way WeCom does, so the decryption can be tested without WeCom.
     */
    public function encrypt(string $message, string $random = '0123456789abcdef'): string
    {
        $key       = (string) base64_decode($this->encodingAesKey.'=');
        $plainText = $random.pack('N', strlen($message)).$message.$this->corpId;
        $padding   = self::BLOCK_SIZE - (strlen($plainText) % self::BLOCK_SIZE);
        $plainText .= str_repeat(chr($padding), $padding);

        return base64_encode((string) openssl_encrypt($plainText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, substr($key, 0, 16)));
    }
}
