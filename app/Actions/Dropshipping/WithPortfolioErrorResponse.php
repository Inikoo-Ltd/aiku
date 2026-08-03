<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 27 Jul 2026 16:48:19 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping;

use App\Helpers\PlatformResponseFormatter;

trait WithPortfolioErrorResponse
{
    /**
     * @return array{message: string}|null
     */
    protected function portfolioErrorResponse(mixed $response): ?array
    {
        $message = $this->portfolioErrorMessage($response);

        return $message ? ['message' => $message] : null;
    }

    protected function portfolioErrorMessage(mixed $response): ?string
    {
        return PlatformResponseFormatter::make()->message($response);
    }
}
