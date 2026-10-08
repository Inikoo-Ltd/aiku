<?php

namespace App\Actions\Inventory\OrgStock;

use App\Actions\OrgAction;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Changes the state of organisation stocks picked across several organisations of the group in
 * one request. Each organisation's share goes through DiscontinueOrgStocks, all inside a single
 * transaction, so a refusal in one organisation leaves every organisation untouched.
 */
class DiscontinueGroupOrgStocks extends OrgAction
{
    /**
     * @return array{changed: int, unchanged: int, scheduled: int}
     */
    public function handle(Group $group, array $modelData, ?User $user = null): array
    {
        $orgStockIdsByOrganisation = OrgStock::where('group_id', $group->id)
            ->whereIn('id', $modelData['org_stock_ids'])
            ->get(['id', 'organisation_id'])
            ->groupBy('organisation_id')
            ->map(fn ($orgStocks) => $orgStocks->pluck('id')->all());

        if ($orgStockIdsByOrganisation->isEmpty()) {
            throw ValidationException::withMessages(['org_stock_ids' => __('No SKO found in this group')]);
        }

        $organisations = Organisation::whereIn('id', $orgStockIdsByOrganisation->keys())->get()->keyBy('id');
        $stats         = ['changed' => 0, 'unchanged' => 0, 'scheduled' => 0];

        DB::transaction(function () use ($orgStockIdsByOrganisation, $organisations, $modelData, $user, &$stats): void {
            foreach ($orgStockIdsByOrganisation as $organisationId => $orgStockIds) {
                $organisationData = array_merge($modelData, ['org_stock_ids' => $orgStockIds]);
                if (Arr::has($modelData, 'expected_updated_at')) {
                    $organisationData['expected_updated_at'] = Arr::only($modelData['expected_updated_at'], $orgStockIds);
                }

                $organisationStats = DiscontinueOrgStocks::make()->action($organisations[$organisationId], $organisationData, $user);

                foreach ($stats as $key => $count) {
                    $stats[$key] = $count + $organisationStats[$key];
                }
            }
        });

        return $stats;
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
        return DiscontinueOrgStocks::make()->rules();
    }

    public function asController(ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromGroup(app('group'), $request);
        $this->handle($this->group, $this->validatedData, $request->user());

        return back();
    }

    public function action(Group $group, array $modelData, ?User $user = null): array
    {
        $this->asAction = true;
        $this->initialisationFromGroup($group, $modelData);

        return $this->handle($group, $this->validatedData, $user);
    }
}
