<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 29 Sep 2023 10:17:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\Comms\Ses\SendSesEmail;
use App\Models\SysAdmin\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class SendLinkResetPassword
{
    use AsAction;

    public function handle(string $token, User $user): void
    {
        $url = route('grp.email.reset-password.show', [
            'token' => $token,
            'email' => $user->email,
        ]);

        $user->notify(new ResetPasswordNotification($url));
    }

    public function getEmailData(string $subject, string $sender, string $email, string $html, string $url): array
    {
        if (preg_match_all('/{{(.*?)}}/', $html, $matches)) {
            foreach ($matches[1] as $i => $placeholder) {
                $placeholder = $this->replaceMergeTags($placeholder, $url);
                $html = str_replace($matches[0][$i], sprintf('%s', $placeholder), $html);
            }
        }

        if (preg_match_all("/\[(.*?)]/", $html, $matches)) {
            foreach ($matches[1] as $i => $placeholder) {
                $placeholder = $this->replaceMergeTags($placeholder, $url);
                $html = str_replace($matches[0][$i], sprintf('%s', $placeholder), $html);
            }
        }

        return SendSesEmail::make()->getEmailData($subject, $sender, $email, $html);
    }

    private function replaceMergeTags($placeholder, $url): string
    {
        $placeholder = Str::kebab(trim($placeholder));

        return match ($placeholder) {
            'reset-password-url' => $url,
            default => ''
        };
    }
}
