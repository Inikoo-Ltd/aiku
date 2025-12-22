<?php

namespace App\Actions\Web\Website;

use App\Actions\OrgAction;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Storage;

class SaveWebsiteRobotsTxt extends OrgAction
{
    public function handle(Website $website): void
    {
        $scheme  = app()->environment('production') ? 'https' : 'http';
        $baseUrl = $scheme . '://' . $website->domain;

        $groups = [
            'products',
            'departments',
            'sub_departments',
            'families',
            'contents',
            'blogs',
            'pages',
            'collections',
        ];

        $lines = [
            'User-agent: *',
            'Disallow: /*.pdf$',
            'Disallow: /return_policy',
            'Disallow: /privacy_policy',
            'Disallow: /cookie_policy',
            'Disallow: /cookies',
            'Disallow: /attachment.php*',
            'Disallow: /asset_label*',
            'Disallow: /page.php*',
            'Disallow: /*.sys$',
            'Disallow: /ethics',
            'Disallow: /image_root*',
            'Disallow: /app/login',
            'Disallow: /app/register',
            'Disallow: /app/favourites',
            'Disallow: /app/dashboard',
            'Disallow: /app/basket',
            'Disallow: /app/back-in-stocks',
            'Disallow: /email-protection',
            '',

            "Sitemap: {$baseUrl}/sitemap.xml",
        ];


        foreach ($groups as $group) {
            $lines[] = "Sitemap: {$baseUrl}/sitemaps/{$group}.xml";
        }

        Storage::disk('local')->put(
            "robots/robots_{$website->id}.txt",
            implode(PHP_EOL, $lines)
        );
    }
}
