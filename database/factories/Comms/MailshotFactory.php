<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 25 Apr 2023 12:47:10 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace Database\Factories\Comms;

use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

class MailshotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'subject' => fake()->text(10),
            'type' => MailshotTypeEnum::NEWSLETTER,
            'state' => MailshotStateEnum::IN_PROCESS,
            'recipients_recipe' => [],
        ];
    }
}
