<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Nov 2026 13:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Helpers;

use Sentry\Breadcrumb;
use Sentry\Event;

/**
 * Fio's API takes the account token in the URL path (fioapi.fio.cz/v1/rest/periods/{token}/...), and Sentry records
 * the URL of every outgoing request in breadcrumbs and tracing spans.
 */
class SentryScrubber
{
    public static function redact(string $text): string
    {
        return preg_replace('#(fioapi\.fio\.cz/v1/rest/[\w-]+/)[^/\s]+#', '$1***', $text);
    }

    public static function beforeBreadcrumb(Breadcrumb $breadcrumb): Breadcrumb
    {
        $url = $breadcrumb->getMetadata()['url'] ?? null;

        return is_string($url) ? $breadcrumb->withMetadata('url', self::redact($url)) : $breadcrumb;
    }

    public static function beforeSendTransaction(Event $event): Event
    {
        foreach ($event->getSpans() as $span) {
            if ($span->getDescription() !== null) {
                $span->setDescription(self::redact($span->getDescription()));
            }
            $url = $span->getData('url');
            if (is_string($url)) {
                $span->setData(['url' => self::redact($url)]);
            }
        }

        return $event;
    }
}
