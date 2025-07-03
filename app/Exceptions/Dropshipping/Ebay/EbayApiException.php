<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Jul 2025 09:18:08 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Exceptions\Dropshipping\Ebay;

use Exception;

class EbayApiException extends Exception
{
    /**
     * Create a new EbayApiException instance.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     * @return void
     */
    public function __construct(string $message = "", int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}