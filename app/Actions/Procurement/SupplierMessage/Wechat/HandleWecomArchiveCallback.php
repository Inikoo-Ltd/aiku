<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierMessage\Wechat;

use App\Models\SysAdmin\Organisation;
use App\Services\Wecom\WecomArchiveException;
use App\Services\Wecom\WecomCallbackCrypto;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleWecomArchiveCallback
{
    use AsAction;

    /**
     * WeCom calls with GET once, when the URL is saved on the chat archive page, and expects the
     * decrypted echostr back. After that it posts a notice whenever new messages are archived;
     * the notice holds no message, so it only starts a pull, which is queued so WeCom gets its
     * answer straight away. The minute-by-minute pull stays as the fallback for a missed notice.
     */
    public function asController(Organisation $organisation, ActionRequest $request): Response
    {
        $crypto = WecomCallbackCrypto::forProcurement($organisation);

        if (! $crypto) {
            abort(404);
        }

        $signature = (string) $request->query('msg_signature');
        $timestamp = (string) $request->query('timestamp');
        $nonce     = (string) $request->query('nonce');

        try {
            if ($request->isMethod('get')) {
                $echo = (string) $request->query('echostr');

                abort_unless($crypto->hasValidSignature($signature, $timestamp, $nonce, $echo), 403);

                return response($crypto->decrypt($echo));
            }

            $encrypted = (string) $this->xmlValue($request->getContent(), 'Encrypt');

            abort_unless($encrypted !== '' && $crypto->hasValidSignature($signature, $timestamp, $nonce, $encrypted), 403);

            if ($this->xmlValue($crypto->decrypt($encrypted), 'Event') === 'msgaudit_notify') {
                PullProcurementWechatArchive::dispatch($organisation);
            }
        } catch (WecomArchiveException $exception) {
            Log::warning("WeCom chat archive callback refused for $organisation->slug: {$exception->getMessage()}");

            abort(403);
        }

        return response('success');
    }

    private function xmlValue(string $xml, string $node): ?string
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml, options: LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($document === false || ! isset($document->{$node})) {
            return null;
        }

        return (string) $document->{$node};
    }
}
