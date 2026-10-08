<?php

namespace App\Actions\Inventory\OrgStock;

use App\Actions\OrgAction;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

/**
 * The discontinue preview for organisation stocks picked across several organisations of the
 * group, as one list. Each organisation's share is built by GetOrgStockDiscontinuePreview.
 */
class GetGroupOrgStockDiscontinuePreview extends OrgAction
{
    /**
     * @param  array<int, int>  $orgStockIds
     * @return array<int, array<string, mixed>>
     */
    public function handle(Group $group, array $orgStockIds, ?User $user = null): array
    {
        $orgStockIdsByOrganisation = OrgStock::where('group_id', $group->id)
            ->whereIn('id', $orgStockIds)
            ->get(['id', 'organisation_id'])
            ->groupBy('organisation_id')
            ->map(fn ($orgStocks) => $orgStocks->pluck('id')->all());

        $organisations = Organisation::whereIn('id', $orgStockIdsByOrganisation->keys())->get()->keyBy('id');

        return $orgStockIdsByOrganisation
            ->flatMap(fn (array $ids, int $organisationId) => GetOrgStockDiscontinuePreview::make()->action($organisations[$organisationId], $ids, $user))
            ->sortBy('code')
            ->values()
            ->all();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return DiscontinueOrgStocks::canChangeGroupStatus($request->user());
    }

    public function rules(): array
    {
        return [
            'org_stock_ids'   => ['required', 'array', 'max:200'],
            'org_stock_ids.*' => ['integer'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group, $this->validatedData['org_stock_ids'], $request->user());
    }

    public function action(Group $group, array $orgStockIds, ?User $user = null): array
    {
        $this->asAction = true;
        $this->initialisationFromGroup($group, ['org_stock_ids' => $orgStockIds]);

        return $this->handle($group, $this->validatedData['org_stock_ids'], $user);
    }
}
