<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\EmailBulkRun;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Str;

class GetHtmlLayout extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;


    public function handle(Mailshot|EmailBulkRun $parent): string
    {
        if ($parent instanceof EmailBulkRun) {
            $compiledLayout = $parent->outbox->emailOngoingRun->email->liveSnapshot->compiled_layout;
        } else {
            $compiledLayout = $parent->email->liveSnapshot->compiled_layout;
        }
        return $compiledLayout;
    }

    public function extractLayout(Mailshot|EmailBulkRun $parent, DispatchedEmail $dispatchedEmail, $unsubscribeUrl): string
    {
        $emailHtmlBody = $parent->email->liveSnapshot->compiled_layout;
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

        return $html;
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
