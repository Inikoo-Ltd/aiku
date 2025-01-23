<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 Dec 2023 13:11:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Traits;

use App\Actions\Comms\Ses\SendSesEmail;
use App\Models\Comms\DispatchedEmail;
use App\Models\CRM\WebUser;
use Illuminate\Support\Str;

trait WithSendBulkEmails
{
    public function sendEmailWithMergeTags(DispatchedEmail $dispatchedEmail, string $sender, string $subject, string $emailHtmlBody, string $unsubscribeUrl, string $passwordToken = null)
    {
        $html = $emailHtmlBody;

        $html = $this->processStyles($html);


        if (preg_match_all("/{{(.*?)}}/", $html, $matches)) {
            foreach ($matches[1] as $i => $placeholder) {
                $placeholder = $this->replaceMergeTags($placeholder, $dispatchedEmail, $unsubscribeUrl, $passwordToken);
                $html        = str_replace($matches[0][$i], sprintf('%s', $placeholder), $html);
            }
        }
        if (preg_match_all("/\[(.*?)]/", $html, $matches)) {
            foreach ($matches[1] as $i => $tag)
            {
                $placeholder = $this->replaceMergeTags($tag, $dispatchedEmail, $unsubscribeUrl, $passwordToken);
                $html        = str_replace($matches[0][$i], sprintf('%s', $placeholder), $html);
            }
        }

        return SendSesEmail::run(
            subject: $subject,
            emailHtmlBody: $html,
            dispatchedEmail: $dispatchedEmail,
            sender: $sender,
            unsubscribeUrl: $unsubscribeUrl
        );
    }

    private function replaceMergeTags($placeholder, $dispatchedEmail, $unsubscribeUrl = null, $passwordToken = null): ?string
    {
        $originalPlaceholder = $placeholder;

        $placeholder = Str::kebab(trim($placeholder));


        if($dispatchedEmail->recipient instanceof WebUser){
            $customerName = $dispatchedEmail->recipient->customer->name;
        }else{
            $customerName = $dispatchedEmail->recipient->name;
        }


        return match ($placeholder) {

            'customer-name' => $customerName,
            'reset_-password_-u-r-l' => $passwordToken,
            'unsubscribe' => sprintf(
                "<a ses:no-track href=\"$unsubscribeUrl\">%s</a>",
                __('Unsubscribe')
            ),
            default => $originalPlaceholder,
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
