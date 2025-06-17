<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 29-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\PollReply;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\CRM\Poll\PollTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\PollReply;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class StoreMultiPollReply extends OrgAction
{
    use WithCRMEditAuthorisation;
    use WithNoStrictRules;


    public function handle(array $modelData): void
    {
        $pollReplies = Arr::pull($modelData, 'poll_replies');

        $pollRepliesData = [];

        foreach ($pollReplies as $pollReply) {
            $pollId = Arr::pull($pollReply, 'id');
            $type = PollTypeEnum::tryFrom(Arr::pull($pollReply, 'type'));
            $answer = Arr::pull($pollReply, 'answer');

            $replyData = [
                'customer_id' => $modelData['customer_id'],
                'poll_id'     => $pollId,
            ];

            if ($type == PollTypeEnum::OPEN_QUESTION) {
                $replyData['value'] = (string) $answer;
                $replyData['poll_option_id'] = null;
            } else {
                $replyData['value'] = null;
                $replyData['poll_option_id'] = (int) $answer;
            }

            $pollRepliesData[] = $replyData;
        }

        PollReply::insert($pollRepliesData);
    }

    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where(function ($query) {
                    $query->where('shop_id', $this->shop->id);
                })
            ],
            'poll_replies'            => ['required', 'array'],
        ];
    }


    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0, bool $strict = true): void
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($shop, $modelData);

        $this->handle($this->validatedData);
    }

}
