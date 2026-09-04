<?php

/*
 * Author Louis Perez
 * Created on 03-09-2026-14h-57m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;

class RepairCreateSystemPages
{
    use WithActionUpdate;
    use WithRepairWebpages;

    protected function handle(Website $website, ?Command $command): void
    {
        $loginPage = Webpage::where('website_id', $website->id)
            ->where('type', WebpageTypeEnum::SYSTEM_PAGE)
            ->where('sub_type', WebpageSubTypeEnum::LOGIN_PAGE)
            ->first();

        if (!$loginPage) {
            $loginPage = StoreWebpage::make()->action($website, [
                'url'           => 'login',
                'code'          => 'login',
                'title'         => 'Login',
                'type'          => WebpageTypeEnum::SYSTEM_PAGE,
                'sub_type'      => WebpageSubTypeEnum::LOGIN_PAGE,
            ]);
        }
        
        $registerPage = Webpage::where('website_id', $website->id)
            ->where('type', WebpageTypeEnum::SYSTEM_PAGE)
            ->where('sub_type', WebpageSubTypeEnum::REGISTER_PAGE)
            ->first();

        if (!$registerPage) {
            $registerPage = StoreWebpage::make()->action($website, [
                'url'           => 'register',
                'code'          => 'register',
                'title'         => 'Register',
                'type'          => WebpageTypeEnum::SYSTEM_PAGE,
                'sub_type'      => WebpageSubTypeEnum::REGISTER_PAGE,
            ]);
        }
            
        $forgotPassword = Webpage::where('website_id', $website->id)
            ->where('type', WebpageTypeEnum::SYSTEM_PAGE)
            ->where('sub_type', WebpageSubTypeEnum::FORGOT_PASSWORD_PAGE)
            ->first();

        if (!$forgotPassword) {
            $forgotPassword = StoreWebpage::make()->action($website, [
                'url'           => 'forgot-password',
                'code'          => 'forgot-password',
                'title'         => 'Forgot Password',
                'type'          => WebpageTypeEnum::SYSTEM_PAGE,
                'sub_type'      => WebpageSubTypeEnum::FORGOT_PASSWORD_PAGE,
            ]);
        }


        $website->update([
            'login_page_id'   => $loginPage->id,
            'register_page_id'   => $registerPage->id,
            'forgot_password_page_id'   => $forgotPassword->id
        ]);

        $command->info("Login Page created: {$loginPage->canonical_url}");
        $command->info("Register Page created: {$registerPage->canonical_url}");
        $command->info("Forgot Password Page created: {$forgotPassword->canonical_url}");
    }

    public string $commandSignature = 'repair:create_system_pages {--website_id=}';

    public function asCommand(Command $command): void
    {
        Nightwatch::dontSample();
        $websites = Website::where('status', true)
            ->when(
                $command->option('website_id'),
                fn ($q) => $q->where('id', $command->option('website_id'))
            )
            ->get();

        foreach ($websites as $website) {
            $command->info("-- Processing: {$website->slug}");
            $this->handle($website, $command);
        }
    }
}
