<?php

/*
 * Author Louis Perez
 * Created on 17-10-2026-11h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Design;

use App\Actions\Goods\ProductDevelopment\NotifyProductDevelopmentUsers;
use App\Actions\Goods\ProductDevelopment\ProductDevelopmentAccess;
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
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class SubmitProductDevelopmentDesignVersion extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopmentDesignRound $designRound;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $request->route('productDevelopmentDesignRound')->productDevelopment;

        return $this->canViewProductDevelopment($request->user(), $productDevelopment)
            && $this->productDevelopmentAccess()->canDesign($request->user(), $productDevelopment);
    }

    public function handle(ProductDevelopmentDesignRound $designRound, User $user): ProductDevelopmentDesignRound
    {
        $designRound->update([
            'status'          => ProductDevelopmentDesignRoundStatusEnum::IN_REVIEW,
            'submitted_by_id' => $user->id,
            'submitted_at'    => now(),
        ]);

        $productDevelopment = $designRound->productDevelopment;
        $roundLabel         = ProductDevelopmentDesignRoundEnum::labels()[$designRound->round->value];

        RecordProductDevelopmentActivity::record($productDevelopment, RecordProductDevelopmentActivity::DESIGN_SUBMITTED, [], [
            'round'   => $designRound->round->value,
            'version' => $designRound->version,
        ]);

        $notifier  = NotifyProductDevelopmentUsers::make();
        $reviewers = collect([...ProductDevelopmentAccess::DESIGN_APPROVER_ROLES, ProductDevelopmentMemberRoleEnum::AGENT, ProductDevelopmentMemberRoleEnum::COMPLIANCE])
            ->flatMap(fn (ProductDevelopmentMemberRoleEnum $role) => $notifier->usersWithRole($productDevelopment, $role))
            ->push($productDevelopment->owner);

        $access                = $this->productDevelopmentAccess();
        $hasPrintLimitsCheck   = array_key_exists(ProductDevelopmentDesignRounds::CHECK_PRINT_LIMITS, ProductDevelopmentDesignRounds::checks($productDevelopment));
        [$approvers, $others]  = $reviewers->filter()->partition(fn (User $reviewer) => $access->canApproveDesign($reviewer, $productDevelopment));
        [$agents, $commenters] = $others->partition(fn (User $reviewer) => $hasPrintLimitsCheck && $access->canConfirmPrintLimits($reviewer, $productDevelopment));

        $recipientsByBody = [
            __('Open the design to pin comments, tick the checks and approve or request changes.')                  => $approvers,
            __('Open the design to pin comments and confirm the factory print limits. The owner or an approver approves it.') => $agents,
            __('Open the design to look it over and pin comments. The owner or an approver approves it.')           => $commenters,
        ];

        foreach ($recipientsByBody as $body => $recipients) {
            NotifyProductDevelopmentUsers::run(
                $productDevelopment,
                $recipients,
                UserNotificationEnum::PRODUCT_DEVELOPMENT_TASK,
                __(':round v:version of :code is ready for review', ['round' => $roundLabel, 'version' => $designRound->version, 'code' => $productDevelopment->code]),
                $body,
                url: route('grp.product_developments.design', ['productDevelopment' => $productDevelopment->slug, 'round' => $designRound->round->value, 'version' => $designRound->version]),
                except: $user,
            );
        }

        return $designRound;
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->designRound->status !== ProductDevelopmentDesignRoundStatusEnum::DRAFT) {
            $validator->errors()->add('status', __('Only a draft version can be sent for review'));

            return;
        }

        if (empty($this->designRound->data['upload']['file_id'] ?? null)) {
            $validator->errors()->add('file', __('Upload the PDF or picture first'));
        }
    }

    public function asController(ProductDevelopmentDesignRound $productDevelopmentDesignRound, ActionRequest $request): ProductDevelopmentDesignRound
    {
        $this->designRound = $productDevelopmentDesignRound;
        $this->initialisationFromGroup($productDevelopmentDesignRound->productDevelopment->group, $request);

        return $this->handle($productDevelopmentDesignRound, $request->user());
    }

    public function action(ProductDevelopmentDesignRound $designRound, User $user): ProductDevelopmentDesignRound
    {
        $this->asAction    = true;
        $this->designRound = $designRound;
        $this->initialisationFromGroup($designRound->productDevelopment->group, []);

        return $this->handle($designRound, $user);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
