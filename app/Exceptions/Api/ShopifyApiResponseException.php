<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:25:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Exceptions\Api;

use Exception;

class ShopifyApiResponseException extends Exception
{
    /**
     * The raw response data.
     *
     * @var array|string
     */
    protected $responseData;

    /**
     * The HTTP status code.
     *
     * @var int|null
     */
    protected $statusCode;

    /**
     * The API service name.
     *
     * @var string
     */
    protected $service;

    /**
     * Create a new API response exception.
     *
     * @param string $message
     * @param array|string $responseData
     * @param int|null $statusCode
     * @param string $service
     * @param int $code
     * @param \Throwable|null $previous
     * @return void
     */
    public function __construct(
        string $message,
        $responseData = [],
        ?int $statusCode = null,
        string $service = 'API',
        int $code = 0,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->responseData = $responseData;
        $this->statusCode = $statusCode;
        $this->service = $service;
    }

    /**
     * Get the raw response data.
     *
     * @return array|string
     */
    public function getResponseData()
    {
        return $this->responseData;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int|null
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Get the API service name.
     *
     * @return string
     */
    public function getService(): string
    {
        return $this->service;
    }

    /**
     * Create a new exception instance for Shopify API errors.
     *
     * @param array|string $errors
     * @param int|null $statusCode
     * @return static
     */
    public static function shopify($errors, ?int $statusCode = null): self
    {
        $message = is_string($errors) ? $errors : json_encode($errors);

        return new static(
            "Shopify API error: {$message}",
            $errors,
            $statusCode,
            'Shopify'
        );
    }

    /**
     * Create a new exception instance for Magento API errors.
     *
     * @param string $message
     * @param array|string $responseData
     * @param int|null $statusCode
     * @return static
     */
    public static function magento(string $message, $responseData = [], ?int $statusCode = null): self
    {
        return new static(
            "Magento API error: {$message}",
            $responseData,
            $statusCode,
            'Magento'
        );
    }
}
