<?php

namespace App\Actions\Traits;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

trait WithPassKeyAction
{
    use AsAction;
    use WithAttributes;

    protected function generatePasskeyOptions()
   {
        $generatePassKeyOptionsAction = app(GeneratePasskeyRegisterOptionsAction::class);

        return $generatePassKeyOptionsAction->execute(auth()->user());
    }

    protected function storePasskey()
    {
      $data = request()->validate([
        'passkey' => 'required|json',
        'options' => 'required|json',
        ]);

        $user = auth()->user();
        $storePasskeyAction = app(StorePasskeyAction::class);

        try {
            $storePasskeyAction->execute(
                $user,
                $data['passkey'],
                $data['options'],
                request()->getHost(),
                ['name' => Str::random(10)],
            );

	        // Redirect back
	        return redirect()->back();

        } catch (Throwable $e) {
            throw ValidationException::withMessages([
            'name' => __('passkeys::passkeys.error_something_went_wrong_generating_the_passkey'),
            ]);
        }
    }
}
