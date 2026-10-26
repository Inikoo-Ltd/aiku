<?php

/*
 * Author Louis Perez
 * Created on 17-10-2026-11h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Design;

use App\Actions\Goods\ProductDevelopment\CreateProductDevelopmentTask;
use App\Actions\Goods\ProductDevelopment\NotifyProductDevelopmentUsers;
use App\Actions\Goods\ProductDevelopment\RecordProductDevelopmentActivity;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentDesignRoundEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentDesignRoundStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Goods\ProductDevelopmentDesignRound;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Ticks or unticks a "Before approving" check, approves the version (locking it and opening the next
 * round's task) or sends it back to the designer.
 */
class ReviewProductDevelopmentDesignVersion extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    public const string CHECK           = 'check';
    public const string UNCHECK         = 'uncheck';
    public const string APPROVE         = 'approve';
    public const string REQUEST_CHANGES = 'request_changes';

    private ProductDevelopmentDesignRound $designRound;

    private User $reviewer;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canViewProductDevelopment($request->user(), $request->route('productDevelopmentDesignRound')->productDevelopment);
    }

    public function handle(ProductDevelopmentDesignRound $designRound, User $user, array $modelData): ProductDevelopmentDesignRound
    {
        $operation = $modelData['operation'];

        if ($operation === self::CHECK || $operation === self::UNCHECK) {
            $data                                  = $designRound->data;
            $data['checks'][$modelData['check']]   = $operation === self::CHECK
                ? ['user_id' => $user->id, 'name' => $user->contact_name ?: $user->username, 'at' => now()->toIso8601String()]
                : null;
            $designRound->update(['data' => $data]);

            return $designRound;
        }

        $productDevelopment = $designRound->productDevelopment;
        $approved           = $operation === self::APPROVE;

        DB::transaction(function () use ($designRound, $user, $approved) {
            $designRound->update($approved
                ? ['status' => ProductDevelopmentDesignRoundStatusEnum::APPROVED, 'approved_by_id' => $user->id, 'approved_at' => now(), 'locked_at' => now()]
                : ['status' => ProductDevelopmentDesignRoundStatusEnum::CHANGES_REQUESTED]);

            RecordProductDevelopmentActivity::record($designRound->productDevelopment, $approved ? RecordProductDevelopmentActivity::DESIGN_APPROVED : RecordProductDevelopmentActivity::DESIGN_CHANGES_REQUESTED, [], [
                'round'   => $designRound->round->value,
                'version' => $designRound->version,
            ]);
        });

        if ($approved) {
            $tasks = CreateProductDevelopmentTask::make();
            $tasks->completeDesignTask($productDevelopment, $designRound->round, $user);
            $tasks->ensureForDevelopment($productDevelopment->refresh(), $user);
        }

        $roundLabel = ProductDevelopmentDesignRoundEnum::labels()[$designRound->round->value];
        NotifyProductDevelopmentUsers::run(
            $productDevelopment,
            NotifyProductDevelopmentUsers::make()->usersWithRole($productDevelopment, ProductDevelopmentMemberRoleEnum::DESIGNER)->push($designRound->submittedBy),
            UserNotificationEnum::PRODUCT_DEVELOPMENT_TASK,
            $approved
                ? __(':round v:version of :code is approved', ['round' => $roundLabel, 'version' => $designRound->version, 'code' => $productDevelopment->code])
                : __('Changes requested on :round v:version of :code', ['round' => $roundLabel, 'version' => $designRound->version, 'code' => $productDevelopment->code]),
            $approved ? __('The version is locked.') : __('Read the pinned comments and start the next version.'),
            url: route('grp.product_developments.design', ['productDevelopment' => $productDevelopment->slug, 'round' => $designRound->round->value, 'version' => $designRound->version]),
            except: $user,
        );

        return $designRound;
    }

    public function rules(): array
    {
        return [
            'operation' => ['required', Rule::in([self::CHECK, self::UNCHECK, self::APPROVE, self::REQUEST_CHANGES])],
            'check'     => ['required_if:operation,'.self::CHECK.','.self::UNCHECK, 'nullable', Rule::in(array_keys(ProductDevelopmentDesignRounds::checks()))],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $designRound = $this->designRound;
        if ($designRound->status !== ProductDevelopmentDesignRoundStatusEnum::IN_REVIEW) {
            $validator->errors()->add('operation', __('This version is not in review'));

            return;
        }

        $access             = $this->productDevelopmentAccess();
        $productDevelopment = $designRound->productDevelopment;
        $operation          = $this->get('operation');

        if ($operation === self::CHECK || $operation === self::UNCHECK) {
            $by      = Arr::get(ProductDevelopmentDesignRounds::checks(), $this->get('check').'.by');
            $allowed = $by === 'agent' ? $access->canConfirmPrintLimits($this->reviewer, $productDevelopment) : $access->canApproveDesign($this->reviewer, $productDevelopment);
            if (!$allowed) {
                $validator->errors()->add('check', $by === 'agent' ? __('Only the agent can confirm this') : __('Only an approver can tick this'));
            }

            return;
        }

        if (!$access->canApproveDesign($this->reviewer, $productDevelopment)) {
            $validator->errors()->add('operation', __('Only the owner or an approver can do this'));

            return;
        }

        if ($operation === self::APPROVE && !ProductDevelopmentDesignRounds::allChecksTicked($designRound)) {
            $validator->errors()->add('operation', __('Tick every check before approving'));
        }
    }

    public function asController(ProductDevelopmentDesignRound $productDevelopmentDesignRound, ActionRequest $request): ProductDevelopmentDesignRound
    {
        $this->designRound = $productDevelopmentDesignRound;
        $this->reviewer    = $request->user();
        $this->initialisationFromGroup($productDevelopmentDesignRound->productDevelopment->group, $request);

        return $this->handle($productDevelopmentDesignRound, $request->user(), $this->validatedData);
    }

    public function action(ProductDevelopmentDesignRound $designRound, User $user, array $modelData): ProductDevelopmentDesignRound
    {
        $this->asAction    = true;
        $this->designRound = $designRound;
        $this->reviewer    = $user;
        $this->initialisationFromGroup($designRound->productDevelopment->group, $modelData);

        return $this->handle($designRound, $user, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
