<?php

/*
 * Author Louis Perez
 * Created on 27-08-2026-09h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Web\WebLayoutTemplate;

use App\Actions\OrgAction;
use App\Models\Web\WebLayoutTemplate;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class DeleteWebLayoutTemplate extends OrgAction
{
    public function handle(WebLayoutTemplate $template): void
    {
        $template->delete();
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back()->with('notification', [
                'status' => 'success',
                'title' => __('Success!'),
                'description' => __('Template has been deleted successfully'),
            ]);
    }

    public function asController(WebLayoutTemplate $template, ActionRequest $request): void
    {
        $this->initialisationFromGroup(group(), $request);

        $this->handle($template);
    }
}
