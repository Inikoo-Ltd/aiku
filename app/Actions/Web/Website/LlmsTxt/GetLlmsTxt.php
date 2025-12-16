<?php

namespace App\Actions\Web\Website\LlmsTxt;

use App\Models\Web\Website;
use App\Models\Web\WebsiteLlmsTxt;
use Lorisleiva\Actions\Concerns\AsAction;

class GetLlmsTxt
{
    use AsAction;

    public function handle(Website $website): ?string
    {
        $llmsTxt = WebsiteLlmsTxt::getActiveForWebsite($website);

        if ($llmsTxt && $llmsTxt->content) {
            return $llmsTxt->content;
        }

        if ($llmsTxt && !$llmsTxt->use_fallback) {
            return null;
        }

        return $this->getGlobalFallback($website);
    }

    protected function getGlobalFallback(Website $website): ?string
    {
        $orgFallback = $website->organisation->settings['llms_txt']['content'] ?? null;
        if ($orgFallback) {
            return $orgFallback;
        }

        $groupFallback = $website->group->settings['llms_txt']['content'] ?? null;
        if ($groupFallback) {
            return $groupFallback;
        }

        return $this->getDefaultContent($website);
    }

    protected function getDefaultContent(Website $website): string
    {
        return 'if you see this, your .txt file was not uploaded successfully.';
    }
}
