<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 08 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Mail;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class MailshotClickedLinkResource extends JsonResource
{
    public function toArray($request): array
    {
        $url = (string) $this->url;
        parse_str((string) parse_url($url, PHP_URL_QUERY), $parameters);

        return [
            'url'               => $url,
            'label'             => (string) parse_url($url, PHP_URL_HOST).(string) parse_url($url, PHP_URL_PATH),
            'element'           => Arr::get($parameters, 'utm_content'),
            'campaign'          => Arr::get($parameters, 'utm_campaign'),
            'number_clicks'     => (int) $this->number_clicks,
            'number_recipients' => (int) $this->number_recipients,
        ];
    }
}
