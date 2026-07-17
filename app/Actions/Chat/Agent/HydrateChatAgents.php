<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Agent;

use App\Actions\Chat\Agent\Hydrators\ChatAgentHydrateChats;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Chat\ChatAgent;

class HydrateChatAgents
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:chat_agents {--ids= : Comma separated chat agent ids}';

    public function __construct()
    {
        $this->model = ChatAgent::class;
    }

    public function handle(ChatAgent $chatAgent): void
    {
        ChatAgentHydrateChats::run($chatAgent);
    }
}
