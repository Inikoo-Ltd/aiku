<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Grp;

use Illuminate\Http\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Tighten\Ziggy\Ziggy;

/**
 * The grp app's route list for route() in the browser: 4,000 routes, 900 KB. Inlined by
 * @routes it was built, sent and parsed again on every full page load; as its own script it
 * is cached until the routes change (INI-145). Only where routes are cached, as in production:
 * a dev machine keeps @routes inline, since its routes change without a new worker.
 *
 * The version is a hash of the script itself, so a page can only ask for the list it was built
 * with. During a deploy, a server still on the old release answers a new page's version with
 * its old list: that answer is not cached, or the old list would stay under the new version.
 */
class ShowGrpRoutesScript
{
    use AsAction;

    /**
     * Per worker and host. Routes only change with a new release, which starts new workers.
     *
     * @var array<string, string>
     */
    private static array $scripts = [];

    public static function isServed(): bool
    {
        return app()->routesAreCached();
    }

    public static function version(): string
    {
        return substr(md5(self::script()), 0, 12);
    }

    private static function script(): string
    {
        return self::$scripts[url('/')] ??= 'const Ziggy='.(new Ziggy('grp'))->toJson().';'
            .file_get_contents(base_path('vendor/tightenco/ziggy/dist/route.umd.js'));
    }

    public function asController(ActionRequest $request): Response
    {
        return response(self::script(), 200, [
            'Content-Type'  => 'text/javascript; charset=UTF-8',
            'Cache-Control' => $request->query('v') === self::version() ? 'public, max-age=31536000, immutable' : 'no-store',
        ]);
    }
}
