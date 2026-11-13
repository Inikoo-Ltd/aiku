<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryFollowUp;

use App\Actions\OrgAction;
use App\Models\GoodsIn\StockDeliveryFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

/**
 * Its author corrects a feedback already sent.
 */
class UpdateStockDeliveryFeedback extends OrgAction
{
    private StockDeliveryFeedback $feedback;

    public function handle(StockDeliveryFeedback $feedback, array $modelData): StockDeliveryFeedback
    {
        $feedback->update(Arr::only($modelData, ['rating', 'topics', 'comment']));

        return $feedback;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->asAction || $request->user()->id === $this->feedback->author_id;
    }

    public function rules(): array
    {
        return collect(StoreStockDeliveryFeedback::feedbackRules())
            ->map(fn (array $rules, string $field) => str_contains($field, '.') ? $rules : ['sometimes', ...$rules])
            ->all();
    }

    public function asController(StockDeliveryFeedback $stockDeliveryFeedback, ActionRequest $request): StockDeliveryFeedback
    {
        $this->feedback = $stockDeliveryFeedback;
        $this->initialisation($stockDeliveryFeedback->organisation, $request);

        return $this->handle($stockDeliveryFeedback, $this->validatedData);
    }

    public function action(StockDeliveryFeedback $stockDeliveryFeedback, array $modelData): StockDeliveryFeedback
    {
        $this->asAction = true;
        $this->feedback = $stockDeliveryFeedback;
        $this->initialisation($stockDeliveryFeedback->organisation, $modelData);

        return $this->handle($stockDeliveryFeedback, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
