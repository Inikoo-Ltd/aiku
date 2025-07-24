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
use App\Models\CRM\Poll;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class StoreMultiPollReply extends OrgAction
{
    use WithCRMEditAuthorisation;
    use WithNoStrictRules;


    public function handle(array $modelData): void
    {
        $pollReplies = Arr::pull($modelData, 'poll_replies');


        foreach ($pollReplies as $pollReply) {

            $pollId = Arr::pull($pollReply, 'id');
            if (Arr::pull($pollReply, 'type') == 'Open Question') {
                $type = PollTypeEnum::OPEN_QUESTION;
            } else {
                $type = PollTypeEnum::OPTION;
            }



            $answer = Arr::pull($pollReply, 'answer');

            $replyData = [
                'customer_id' => $modelData['customer_id'],
                'poll_id'     => $pollId,
            ];

            $ok = false;
            if ($type == PollTypeEnum::OPEN_QUESTION) {
                $answer = (string)$answer ?? '';
                if ($answer != '') {
                    $ok = true;
                }
                $replyData['value']          = $answer;
                $replyData['poll_option_id'] = null;
            } else {
                $answer = (int)$answer;
                $replyData['value']          = null;
                $replyData['poll_option_id'] = $answer;
                if ($answer) {
                    $ok = true;
                }
            }
            if ($ok) {
                $poll = Poll::find($pollId);
                if ($poll) {
                    StorePollReply::make()->action($poll, $replyData);
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            'customer_id'  => [
                'required',
                Rule::exists('customers', 'id')->where(function ($query) {
                    $query->where('shop_id', $this->shop->id);
                })
            ],
            'poll_replies' => ['required', 'array'],
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
