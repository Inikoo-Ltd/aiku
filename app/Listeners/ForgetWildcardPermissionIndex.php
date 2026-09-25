<?php

namespace App\Listeners;

use Spatie\Permission\PermissionRegistrar;

class ForgetWildcardPermissionIndex
{
    public function handle(object $event): void
    {
        $event->sandbox->make(PermissionRegistrar::class)->forgetWildcardPermissionIndex();
    }
}
