<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Docs;

use App\Models\Web\Website;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

class ShowIrisDocs
{
    use AsAction;
    use WithIrisDocs;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(Website $website): array
    {
        $this->ensureDropshippingWebsite($website);

        $everything = $this->everything();
        $language   = $this->language($website);

        return $everything
            ->where('lang', 'en')
            ->filter(fn (array $doc) => $this->isForWebsite($doc, $website))
            ->map(fn (array $doc) => $this->summary($this->inLanguage($doc, $language, $everything), $website))
            ->sortBy([['category', 'asc'], ['series', 'asc'], ['order', 'asc'], ['title', 'asc']])
            ->values()
            ->all();
    }

    public function asController(ActionRequest $request): Response
    {
        /** @var Website $website */
        $website = $request->input('website');

        $response = Inertia::render('Docs/DocsIndex', [
            'docs' => $this->handle($website),
        ])->withViewData([
            'browserTitle' => __('Help and guides').' | '.$website->name,
        ])->toResponse($request);

        return $this->cacheable($response, $website);
    }
}
