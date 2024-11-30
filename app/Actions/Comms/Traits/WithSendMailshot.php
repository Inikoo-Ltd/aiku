<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Traits;

use App\Actions\Comms\SenderEmail\SendAlibabaEmail;
use App\Actions\Comms\Ses\SendSesEmail;
use App\Models\Comms\DispatchedEmail;
use Illuminate\Support\Str;

trait WithSendMailshot
{
    public function sendEmailWithMergeTags(
        DispatchedEmail $dispatchedEmail,
        string $sender,
        string $subject,
        string $emailHtmlBody,
        string $unsubscribeUrl,
    ) {
        $html = $emailHtmlBody;

        $html = $this->processStyles($html);

        if (preg_match_all("/{{(.*?)}}/", $html, $matches)) {
            foreach ($matches[1] as $i => $placeholder) {
                $placeholder = $this->replaceMergeTags($placeholder, $dispatchedEmail, $unsubscribeUrl);
                $html        = str_replace($matches[0][$i], sprintf('%s', $placeholder), $html);
            }
        }

        if (preg_match_all("/\[(.*?)]/", $html, $matches)) {
            foreach ($matches[1] as $i => $placeholder) {
                $placeholder = $this->replaceMergeTags($placeholder, $dispatchedEmail, $unsubscribeUrl);
                $html        = str_replace($matches[0][$i], sprintf('%s', $placeholder), $html);
            }
        }

        match ('Alibaba') {
            'Ses' => SendSesEmail::run(
                subject: $subject,
                emailHtmlBody: $html,
                dispatchedEmail: $dispatchedEmail,
                sender: $sender,
                unsubscribeUrl: $unsubscribeUrl
            ),
            'Alibaba' => SendAlibabaEmail::run(
                subject: $subject,
                emailHtmlBody: $html,
                dispatchedEmail: $dispatchedEmail,
                sender: $sender,
                unsubscribeUrl: $unsubscribeUrl
            )
        };
    }

    private function replaceMergeTags($placeholder, $dispatchedEmail, $unsubscribeUrl): string
    {
        $placeholder = Str::kebab(trim($placeholder));

        return match ($placeholder) {
            'name'        => $dispatchedEmail->getName(),
            'unsubscribe' => sprintf(
                "<a ses:no-track href=\"$unsubscribeUrl\">%s</a>",
                __('Unsubscribe')
            ),
            default => ''
        };
    }

    public function processStyles($html): array|string|null
    {
        $html = preg_replace_callback('/<[^>]+style=["\'](.*?)["\'][^>]*>/i', function ($match) {
            $style = $match[1];

            // Find and modify color values within the style attribute
            $style = preg_replace_callback('/color\s*:\s*([^;]+);/i', function ($colorMatch) {
                $colorValue    = $colorMatch[1];
                $modifiedColor = $colorValue . ' !important';
                return 'color: ' . $modifiedColor . ';';
            }, $style);

            // Update the style attribute in the HTML tag
            return str_replace($match[1], $style, $match[0]);
        }, $html);

        // Remove <style> tags and their content
        return preg_replace('/<style(.*?)>(.*?)<\/style>/is', '', $html);
    }
}
