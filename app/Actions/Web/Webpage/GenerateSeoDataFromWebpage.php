<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Webpage;

use App\Actions\Helpers\AI\Traits\WithAIBot;
use App\Actions\Helpers\AI\Traits\WithPromptAI;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateSeoDataFromWebpage extends OrgAction
{
    use AsAction;
    use WithNoStrictRules;
    use WithAIBot;
    use WithPromptAI;

    private Website $website;
    /**
     * @throws \Throwable
     */
    public function handle(Webpage $webpage, array $modelData)
    {

        $res = $this->askActionDeepseek(
            $this->promptSeo("Which is the longest river in the world? The Nile River."),
        );

        dd($res);

        // give me the seo description and keywords from this link, {$webpage->getFullUrl()}
        // dd($res);

        // return $data;
    }


    public function action(Webpage $webpage, array $modelData, bool $strict = true)
    {
        $this->strict   = $strict;
        $this->asAction = true;
        $this->setRawAttributes($modelData);
        $validatedData = $this->validateAttributes();

        return $this->handle($webpage, $validatedData);
    }


    public string $commandSignature = 'xxx';

    public function asCommand($command)
    {
        $a = Webpage::where('shop_id', 15)->get();
        foreach ($a as $b) {
            $this->action($b, []);
        }
    }


}
