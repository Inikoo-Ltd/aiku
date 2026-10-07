<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\SysAdmin\McpChange\RevertMcpChange;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\SysAdmin\McpChange;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lists the changes AI assistants made in Aiku (related products, SKO states…), newest first, with who asked, their request text and whether each was reverted; with revert_id it reverts one change back to what it was before. A revert is refused when the data was changed again afterwards. Show the user the change and only revert after they confirmed in their own words, passing their request text. Lists and reverts only the kinds of change this user is enrolled for.')]
class AiChangesTool extends Tool
{
    use WithMcpPermissions;

    public function handle(Request $request): Response
    {
        $request->validate([
            'revert_id'    => ['sometimes', 'integer'],
            'request_text' => ['required_with:revert_id', 'string', 'max:4000'],
            'username'     => ['sometimes', 'string'],
            'limit'        => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var User $user */
        $user  = $request->user();
        $types = $this->visibleTypes($request);
        if (!$types) {
            return Response::error('This user is not enrolled for any change made through an AI assistant. Do not retry.');
        }

        if ($request->has('revert_id')) {
            $mcpChange = McpChange::where('group_id', $user->group_id)->whereIn('type', $types)->find($request->integer('revert_id'));
            if (!$mcpChange || !$mcpChange->canBeRevertedBy($user)) {
                return Response::error('Change '.$request->integer('revert_id').' does not exist, was already reverted, or this user cannot revert it.');
            }

            try {
                RevertMcpChange::run($mcpChange, $user);
            } catch (ValidationException $exception) {
                return Response::error(implode(' ', $exception->validator->errors()->all()));
            }

            return Response::json(['reverted' => $this->row($mcpChange->refresh(), $user)]);
        }

        $changes = McpChange::where('group_id', $user->group_id)
            ->whereIn('type', $types)
            ->when($request->has('username'), fn ($query) => $query->whereHas('user', fn ($query) => $query->where('username', $request->string('username'))))
            ->with(['user:id,username', 'revertedBy:id,username'])
            ->latest('id')
            ->limit($request->integer('limit', 20))
            ->get();

        return Response::json($changes->map(fn (McpChange $mcpChange) => $this->row($mcpChange, $user))->all());
    }

    /**
     * @return array<int, string>
     */
    private function visibleTypes(Request $request): array
    {
        $isSysadmin = $this->userCan($request, 'sysadmin.edit');

        return collect(McpChangeTypeEnum::cases())
            ->filter(fn (McpChangeTypeEnum $type) => $isSysadmin || $request->user()->{$type->userSwitch()})
            ->map(fn (McpChangeTypeEnum $type) => $type->value)
            ->values()
            ->all();
    }

    private function row(McpChange $mcpChange, User $user): array
    {
        return [
            'id'           => $mcpChange->id,
            'at'           => $mcpChange->created_at->toIso8601String(),
            'by'           => $mcpChange->user?->username,
            'change'       => $mcpChange->label,
            'shops'        => $mcpChange->data['shops'] ?? null,
            'request_text' => $mcpChange->request_text,
            'before'       => $mcpChange->before,
            'after'        => $mcpChange->after,
            'reverted_at'  => $mcpChange->reverted_at?->toIso8601String(),
            'reverted_by'  => $mcpChange->revertedBy?->username,
            'can_revert'   => $mcpChange->canBeRevertedBy($user),
        ];
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'revert_id'    => $schema->integer()->description('Id of the change to revert; omit to list changes'),
            'request_text' => $schema->string()->description('The user\'s request, verbatim; required when reverting'),
            'username'     => $schema->string()->description('Only changes made by this aiku username'),
            'limit'        => $schema->integer()->description('How many changes to list, default 20'),
        ];
    }
}
