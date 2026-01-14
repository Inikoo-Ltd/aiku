<?php

/*
 * author Louis Perez
 * created on 13-01-2026-09h-53m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\UI\Profile;

use App\Actions\UI\WithInertia;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use PragmaRX\Google2FAQRCode\Google2FA;

class View2FAProfile
{
    use AsAction;
    use WithInertia;

    public function handle(User $user): array
    {
        $google2fa = new Google2FA();

        $secret = $user->google2fa_secret;

        // If secret doesn't exist on DB, generate new one
        if (!$secret) {
            $secret = $google2fa->generateSecretKey(32);
        }

        // Generate the URL (for the QR)
        $google2fa->getQRCodeUrl(
            env('APP_NAME'),
            $user->email,
            $secret
        );
        // Generate the QR
        $qrInline = $google2fa->getQRCodeInline(
            env('APP_NAME'),
            $user->email,
            $secret,
            360
        );

        return [
            'qrUrl'     => $qrInline,
            'secretKey' => $secret
        ];
    }

    public function asController(ActionRequest $request): array
    {
        return $this->handle($request->user());
    }

    public function jsonResponse(array $data2fa): array
    {
        return $data2fa;
    }
}
