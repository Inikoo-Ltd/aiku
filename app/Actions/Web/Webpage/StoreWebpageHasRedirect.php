<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 11-10-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Web\Webpage;
use App\Models\Web\WebpageHasRedirect;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StoreWebpageHasRedirect extends OrgAction
{
    use WithActionUpdate;

    private Webpage $webpage;

    public function handle(Webpage $webpage, array $modelData): array
    {
        // $redirectStr = Arr::get($modelData, 'data.redirecturl');

        // if ($redirectStr) {
        //     $redirects = explode(',', $redirectStr);

        //     DB::transaction(function () use ($webpage, $redirects) {
        //         $webpage->redirects()->delete($webpage->id);

        //         $redirectData = array_map(function ($redirect) {
        //             return new WebpageHasRedirect(['redirect' => $redirect]);
        //         }, $redirects);

        //         if (!empty($redirectData)) {
        //             $webpage->redirects()->saveMany($redirectData);
        //         }
        //     });

        //     // if(count($redirects) >= 10) {
        //     //     // remove the redirects data
        //     // }
        //     // $webpage->redirects()->sync($webpage->id, ['redirect' => $redirect]);
        //     // foreach($redirects as $redirect) {
        //     // }
        //     Arr::forget($modelData, 'data.redirecturl');
        //     // dd($webpage->redirects);
        // }
        // $this->update($webpage->redirects->saveMany)
        return $modelData;
    }

    public function action(Webpage $webpage, array $modelData): array
    {
        // Arr::forget($modelData, 'data.redirecturl');
        return $this->handle($webpage, $modelData);
    }
}
