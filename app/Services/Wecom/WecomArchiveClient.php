<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Services\Wecom;

use App\Actions\Procurement\Settings\UpdateProcurementSettings;
use App\Models\SysAdmin\Organisation;
use FFI;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;

/**
 * WeCom hands archived chats out only through its C library, never over HTTP, so this reads
 * them through FFI. FFI is allowed on the command line only (ffi.enable=preload), which is why
 * everything that calls this runs in queue workers or artisan, never in a web request.
 */
class WecomArchiveClient
{
    private const string HEADER = <<<'C'
        typedef struct WeWorkFinanceSdk_t WeWorkFinanceSdk_t;
        typedef struct Slice_t { char* buf; int len; } Slice_t;
        typedef struct MediaData { char* outindexbuf; int out_len; char* data; int data_len; int is_finish; } MediaData_t;
        WeWorkFinanceSdk_t* NewSdk();
        int Init(WeWorkFinanceSdk_t* sdk, const char* corpid, const char* secret);
        void DestroySdk(WeWorkFinanceSdk_t* sdk);
        int GetChatData(WeWorkFinanceSdk_t* sdk, unsigned long long seq, unsigned int limit, const char* proxy, const char* passwd, int timeout, Slice_t* chatDatas);
        int DecryptData(const char* encrypt_key, const char* encrypt_msg, Slice_t* msg);
        int GetMediaData(WeWorkFinanceSdk_t* sdk, const char* indexbuf, const char* sdkFileid, const char* proxy, const char* passwd, int timeout, MediaData_t* media_data);
        Slice_t* NewSlice();
        void FreeSlice(Slice_t* slice);
        char* GetContentFromSlice(Slice_t* slice);
        int GetSliceLen(Slice_t* slice);
        MediaData_t* NewMediaData();
        void FreeMediaData(MediaData_t* media_data);
        char* GetOutIndexBuf(MediaData_t* media_data);
        char* GetData(MediaData_t* media_data);
        int GetIndexLen(MediaData_t* media_data);
        int GetDataLen(MediaData_t* media_data);
        int IsMediaDataFinish(MediaData_t* media_data);
        C;

    private ?FFI $ffi = null;

    private mixed $sdk = null;

    public function __construct(private readonly string $corpId, private readonly string $secret, private readonly string $privateKey)
    {
    }

    /**
     * Resolved through the container so tests can swap in a client that needs no library.
     */
    public static function forProcurement(Organisation $organisation): ?self
    {
        $wechat = Arr::get($organisation->settings, 'procurement.wechat', []);

        if (! UpdateProcurementSettings::usesWechat($organisation) || blank(Arr::get($wechat, 'corp_id')) || blank(Arr::get($wechat, 'archive_secret')) || blank(Arr::get($wechat, 'private_key'))) {
            return null;
        }

        return app(self::class, [
            'corpId'     => $wechat['corp_id'],
            'secret'     => Crypt::decryptString($wechat['archive_secret']),
            'privateKey' => Crypt::decryptString($wechat['private_key']),
        ]);
    }

    /**
     * @return array<int, array{seq: int, msgid: string, publickey_ver: int, encrypt_random_key: string, encrypt_chat_msg: string}>
     */
    public function chatData(int $seq, int $limit): array
    {
        $ffi   = $this->ffi();
        $slice = $ffi->NewSlice();

        try {
            $result = $ffi->GetChatData($this->sdk(), $seq, $limit, '', '', (int) config('wecom.archive.timeout'), $slice);

            if ($result !== 0) {
                throw new WecomArchiveException("WeCom refused the chat archive request (error $result)");
            }

            $response = json_decode($this->sliceContent($slice), true);
        } finally {
            $ffi->FreeSlice($slice);
        }

        if (! is_array($response) || (int) Arr::get($response, 'errcode', 0) !== 0) {
            throw new WecomArchiveException('WeCom refused the chat archive request: '.Arr::get($response, 'errmsg', 'unreadable response'));
        }

        return Arr::get($response, 'chatdata', []);
    }

    /**
     * Each message carries its own random key, encrypted with the public key uploaded to WeCom;
     * the private key opens it and the library then decrypts the message with it.
     *
     * @param  array{encrypt_random_key: string, encrypt_chat_msg: string}  $chatData
     * @return array<string, mixed>
     */
    public function decrypt(array $chatData): array
    {
        $randomKey = '';

        if (! openssl_private_decrypt((string) base64_decode($chatData['encrypt_random_key']), $randomKey, $this->privateKey, OPENSSL_PKCS1_PADDING)) {
            throw new WecomArchiveException('The private key does not open this message; check that it matches the public key uploaded to WeCom');
        }

        $ffi   = $this->ffi();
        $slice = $ffi->NewSlice();

        try {
            $result = $ffi->DecryptData($randomKey, $chatData['encrypt_chat_msg'], $slice);

            if ($result !== 0) {
                throw new WecomArchiveException("WeCom could not decrypt the message (error $result)");
            }

            $message = json_decode($this->sliceContent($slice), true);
        } finally {
            $ffi->FreeSlice($slice);
        }

        if (! is_array($message)) {
            throw new WecomArchiveException('A decrypted message is not readable JSON');
        }

        return $message;
    }

    /**
     * Files come in chunks; each answer says where the next one starts.
     */
    public function media(string $sdkFileId): string
    {
        $ffi       = $this->ffi();
        $content   = '';
        $indexBuf  = '';

        do {
            $mediaData = $ffi->NewMediaData();

            try {
                $result = $ffi->GetMediaData($this->sdk(), $indexBuf, $sdkFileId, '', '', (int) config('wecom.archive.timeout'), $mediaData);

                if ($result !== 0) {
                    throw new WecomArchiveException("WeCom refused the file download (error $result)");
                }

                $content  .= FFI::string($ffi->GetData($mediaData), $ffi->GetDataLen($mediaData));
                $indexBuf = FFI::string($ffi->GetOutIndexBuf($mediaData), $ffi->GetIndexLen($mediaData));
                $finished = $ffi->IsMediaDataFinish($mediaData) === 1;
            } finally {
                $ffi->FreeMediaData($mediaData);
            }
        } while (! $finished);

        return $content;
    }

    public function __destruct()
    {
        if ($this->sdk !== null) {
            $this->ffi->DestroySdk($this->sdk);
        }
    }

    private function ffi(): FFI
    {
        if ($this->ffi) {
            return $this->ffi;
        }

        $path = (string) config('wecom.archive.sdk_path');

        if (! extension_loaded('ffi') || ! is_file($path)) {
            throw new WecomArchiveException("The WeCom chat archive library is not installed at $path, or PHP FFI is off");
        }

        return $this->ffi = FFI::cdef(self::HEADER, $path);
    }

    private function sdk(): mixed
    {
        if ($this->sdk !== null) {
            return $this->sdk;
        }

        $ffi = $this->ffi();
        $sdk = $ffi->NewSdk();

        $result = $ffi->Init($sdk, $this->corpId, $this->secret);

        if ($result !== 0) {
            $ffi->DestroySdk($sdk);

            throw new WecomArchiveException("WeCom rejected the Corp ID or chat archive secret (error $result)");
        }

        return $this->sdk = $sdk;
    }

    private function sliceContent(mixed $slice): string
    {
        $ffi = $this->ffi();

        return FFI::string($ffi->GetContentFromSlice($slice), $ffi->GetSliceLen($slice));
    }
}
