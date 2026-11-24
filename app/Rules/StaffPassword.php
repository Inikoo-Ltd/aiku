<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Rules;

use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

class StaffPassword implements ValidationRule
{
    public const int MIN_LENGTH = 12;

    public function __construct(private readonly ?User $user = null, private readonly bool $lenient = false)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;

        if (!$this->lenient) {
            if (mb_strlen($password) < self::MIN_LENGTH) {
                $fail(__('The password must be at least :min characters.', ['min' => self::MIN_LENGTH]));

                return;
            }

            if (!preg_match('/\pL/u', $password) || !preg_match('/\pN/u', $password)) {
                $fail(__('The password must contain both letters and numbers.'));

                return;
            }
        }

        if ($this->user && $this->isRecentlyUsed($password)) {
            $fail(__('You cannot reuse any of your last :count passwords.', ['count' => User::PASSWORD_HISTORY_SIZE]));
        }
    }

    private function isRecentlyUsed(string $password): bool
    {
        $hashes = array_filter([$this->user->password, ...($this->user->password_history ?? [])]);

        foreach ($hashes as $hash) {
            if (Hash::check($password, $hash)) {
                return true;
            }
        }

        return false;
    }
}
