<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 27 Jul 2026 11:20:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Helpers;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;

class PlatformResponseFormatter
{
    use AsObject;

    private const MESSAGE_KEYS = [
        'message',
        'error_message',
        'errorMessage',
        'error_description',
        'description',
        'detail',
        'reason',
        'msg',
    ];

    private const CODE_KEYS = [
        'code',
        'errorId',
        'error_id',
        'error_code',
        'errorCode',
    ];

    private const ERROR_CONTAINER_KEYS = [
        'errors',
        'error',
        'userErrors',
        'user_errors',
        'issues',
        'messages',
        'data',
        'body',
    ];

    private const EMPTY_VALUES = ['', '-', '{}', '[]', 'null', 'nil', 'none'];

    private const MAX_MESSAGES = 3;

    private const MAX_FIELD_ERRORS = 5;

    private const MAX_MESSAGE_LENGTH = 300;

    private const HINTS = [
        'woocommerce_product_image_upload_error'  => 'Your store could not save the product images, its uploads folder is not writable. Ask your hosting to fix the permissions of the WordPress uploads folder, then upload this product again.',
        'woocommerce_rest_product_not_created'    => 'A product with this SKU already exists in your store. Match this item with the existing product instead of creating a new one.',
        'product_invalid_sku'                     => 'A product with this SKU already exists in your store. Match this item with the existing product instead of creating a new one.',
        'woocommerce_rest_cannot_create'          => 'The account connected to your store is not allowed to create products. Reconnect the channel using an account with write permissions.',
        'woocommerce_rest_cannot_update'          => 'The account connected to your store is not allowed to edit products. Reconnect the channel using an account with write permissions.',
        'woocommerce_rest_cannot_view'            => 'The account connected to your store is not allowed to read products. Reconnect the channel using an account with read permissions.',
        'woocommerce_rest_authentication_error'   => 'Your store rejected the credentials. Reconnect the channel to generate new ones.',
        'woocommerce_rest_invalid_product_id'     => 'This product no longer exists in your store, upload it again or match it with an existing product.',
    ];

    /**
     * Turns whatever a platform (WooCommerce, Shopify, TikTok, eBay, Amazon, ...) returned
     * into something a customer can read.
     *
     * @return array{message: string|null, hint: string|null, code: string|null, detail: string|null}
     */
    public function format(mixed $response): array
    {
        $entries = $this->extractEntries($response);

        $messages = [];
        $code     = null;

        foreach ($entries as $entry) {
            $message = Arr::get($entry, 'message');

            if ($message && !in_array($message, $messages, true)) {
                $messages[] = $message;
            }

            $code ??= Arr::get($entry, 'code');
        }

        $message = implode("\n", array_slice($messages, 0, self::MAX_MESSAGES)) ?: null;

        if ($message && $this->isTechnicalDump($message)) {
            $message = __('Something went wrong on our side while sending this product, our team can see the technical details.');
        }

        $message = $message ? Str::limit($message, self::MAX_MESSAGE_LENGTH) : null;
        $detail  = $this->detail($response, $message);

        if ($detail && $this->isTechnicalDump($detail)) {
            $detail = null;
        }

        return [
            'message' => $message,
            'hint'    => $this->hint($code, $message, $detail),
            'code'    => $code,
            'detail'  => $detail,
        ];
    }

    public function message(mixed $response): ?string
    {
        return Arr::get($this->format($response), 'message');
    }

    /**
     * @return array<int, array{message: string, code: string|null}>
     */
    private function extractEntries(mixed $value, int $depth = 0, bool $isErrorBag = false): array
    {
        if ($depth > 6 || is_null($value) || is_bool($value)) {
            return [];
        }

        if (is_int($value) || is_float($value)) {
            return [['message' => (string)$value, 'code' => null]];
        }

        if (is_string($value)) {
            return $this->extractFromString($value, $depth);
        }

        if (is_array($value)) {
            return $this->extractFromArray($value, $depth, $isErrorBag);
        }

        return [];
    }

    /**
     * @return array<int, array{message: string, code: string|null}>
     */
    private function extractFromString(string $value, int $depth): array
    {
        $value = $this->stripDebugPrefix(trim($value));

        if ($this->isEmpty($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return $this->extractEntries($decoded, $depth + 1);
        }

        if ($this->isHtml($value)) {
            return [[
                'message' => __('The store answered with a web page instead of data, it may be down, in maintenance mode or blocking the connection.'),
                'code'    => null,
            ]];
        }

        $message = $this->tidy($value);

        return $message === '' ? [] : [['message' => $message, 'code' => null]];
    }

    /**
     * @return array<int, array{message: string, code: string|null}>
     */
    private function extractFromArray(array $value, int $depth, bool $isErrorBag): array
    {
        if ($value === []) {
            return [];
        }

        if (array_is_list($value)) {
            $entries = [];
            foreach ($value as $item) {
                $entries = array_merge($entries, $this->extractEntries($item, $depth + 1, $isErrorBag));
            }

            return $entries;
        }

        foreach (self::MESSAGE_KEYS as $key) {
            if (!Arr::exists($value, $key)) {
                continue;
            }

            $entries = $this->extractEntries($value[$key], $depth + 1, $isErrorBag);

            if ($entries !== []) {
                return [[
                    'message' => Arr::get($entries, '0.message'),
                    'code'    => $this->codeOf($value) ?? Arr::get($entries, '0.code'),
                ]];
            }
        }

        foreach (self::ERROR_CONTAINER_KEYS as $key) {
            if (!Arr::exists($value, $key)) {
                continue;
            }

            $entries = $this->extractEntries($value[$key], $depth + 1, true);

            if ($entries !== []) {
                return $entries;
            }
        }

        return $isErrorBag ? $this->extractFromFieldErrors($value) : [];
    }

    /**
     * @return array<int, array{message: string, code: string|null}>
     */
    private function extractFromFieldErrors(array $value): array
    {
        if (count($value) > self::MAX_FIELD_ERRORS) {
            return [];
        }

        $entries = [];

        foreach ($value as $field => $fieldValue) {
            foreach (Arr::wrap($fieldValue) as $fieldMessage) {
                if (!is_string($fieldMessage)) {
                    return [];
                }

                $fieldMessage = $this->tidy($fieldMessage);

                if ($fieldMessage === '') {
                    continue;
                }

                $entries[] = [
                    'message' => is_string($field) ? Str::headline($field).': '.$fieldMessage : $fieldMessage,
                    'code'    => null,
                ];
            }
        }

        return $entries;
    }

    private function codeOf(array $value): ?string
    {
        foreach (self::CODE_KEYS as $key) {
            $code = Arr::get($value, $key);

            if (is_string($code) && !$this->isEmpty($code)) {
                return $code;
            }

            if (is_int($code)) {
                return (string)$code;
            }
        }

        return null;
    }

    private function detail(mixed $response, ?string $message): ?string
    {
        $decoded = is_string($response)
            ? json_decode($this->stripDebugPrefix(trim($response)), true)
            : $response;

        if (is_array($decoded)) {
            $decoded = $this->decodeNestedJson($decoded);
            $detail  = $this->hasContent($decoded) ? $this->encode($decoded) : null;
        } else {
            $detail = is_string($response) ? trim($response) : null;
        }

        if ($detail === null || $this->isEmpty($detail) || $detail === $message) {
            return null;
        }

        return $detail;
    }

    private function hasContent(array $value): bool
    {
        foreach ($value as $item) {
            if (is_array($item) ? $this->hasContent($item) : !$this->isEmpty((string)$item)) {
                return true;
            }
        }

        return false;
    }

    private function decodeNestedJson(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_string($item)) {
                $decoded = json_decode($item, true);

                if (is_array($decoded)) {
                    $value[$key] = $this->decodeNestedJson($decoded);
                }

                continue;
            }

            if (is_array($item)) {
                $value[$key] = $this->decodeNestedJson($item);
            }
        }

        return $value;
    }

    private function encode(mixed $value): ?string
    {
        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? null : $encoded;
    }

    private function hint(?string $code, ?string $message, ?string $detail): ?string
    {
        if ($code && Arr::exists(self::HINTS, $code)) {
            return __(self::HINTS[$code]);
        }

        foreach (self::HINTS as $hintCode => $hint) {
            if ($detail && str_contains($detail, $hintCode)) {
                return __($hint);
            }
        }

        if (!$message && $detail) {
            return __('The platform did not explain what went wrong, check the details or try again.');
        }

        return null;
    }

    private function stripDebugPrefix(string $value): string
    {
        return (string)preg_replace('/^E\d+:\s*/', '', $value);
    }

    private function isTechnicalDump(string $value): bool
    {
        return (bool)preg_match(
            '/SQLSTATE\[|Stack trace:|#\d+ \/|\\\\Illuminate\\\\|Exception: |Trying to access array offset|Attempt to read property|Call to a member function|Undefined (variable|property|array key|method)/',
            $value
        );
    }

    private function isHtml(string $value): bool
    {
        return (bool)preg_match('/^<(!doctype|html|\?xml|body|head)/i', trim($value));
    }

    private function isEmpty(string $value): bool
    {
        return in_array(strtolower(trim($value)), self::EMPTY_VALUES, true);
    }

    private function tidy(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5);

        return trim((string)preg_replace('/\s+/', ' ', $value));
    }
}
