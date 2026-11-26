<?php

/*
 * Author Louis Perez
 * Created on 26-11-2026-09h-50m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group;

use App\Actions\Api\Group\Offline\MobileOfflineDeviceHeaders;
use App\Actions\Helpers\Ticket\StoreTicket;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Models\Helpers\Ticket;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class StoreMobileTicket
{
    use AsController;

    public const array CATEGORIES = ['unknown_barcode', 'wrong_stock', 'data_problem', 'app_error', 'other'];

    private const int SUBJECT_DETAILS_LENGTH = 80;

    /**
     * @param array{category: string, details: string, screen?: string|null, error_code?: string|null, support_reference?: string|null, last_scan?: string|null, warehouse_id?: int|null, images?: array<int, mixed>} $data
     */
    public function handle(User $user, array $data, ?string $appVersion = null, ?string $platform = null): Ticket
    {
        $warehouse = isset($data['warehouse_id']) ? Warehouse::find($data['warehouse_id']) : null;

        return StoreTicket::make()->action($user->group, array_filter([
            'subject'       => $this->categoryLabel($data['category']).': '.Str::limit(Str::squish($data['details']), self::SUBJECT_DETAILS_LENGTH),
            'description'   => $this->description($data, $warehouse, $appVersion, $platform),
            'kind'          => $data['category'] === 'app_error' ? TicketKindEnum::BUG->value : null,
            'reporter_type' => 'User',
            'reporter_id'   => $user->id,
            'images'        => $data['images'] ?? [],
        ], fn ($value) => $value !== null && $value !== []));
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(Ticket::canBeRaisedBy($user), 403);

        $data = Validator::make($request->all(), [
            'category'          => ['required', Rule::in(self::CATEGORIES)],
            'details'           => ['required', 'string', 'max:5000'],
            'screen'            => ['sometimes', 'nullable', 'string', 'max:120'],
            'error_code'        => ['sometimes', 'nullable', 'string', 'regex:/^AIKU-(APP|CLK|SYNC)-\d{3}$/'],
            'support_reference' => ['sometimes', 'nullable', 'string', 'max:64'],
            'last_scan'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'warehouse_id'      => ['sometimes', 'nullable', 'integer', Rule::exists('warehouses', 'id')->where('group_id', $user->group_id)],
            'images'            => ['sometimes', 'array', 'max:5'],
            'images.*'          => Ticket::ticketFileRules(),
        ], Ticket::ticketFileValidationMessages())->validate();

        $ticket = $this->handle(
            $user,
            $data,
            $request->header(MobileOfflineDeviceHeaders::APP_VERSION_HEADER),
            $request->header(MobileOfflineDeviceHeaders::PLATFORM_HEADER),
        );

        return response()->json(['data' => ['reference' => $ticket->reference]], 201);
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            'unknown_barcode' => __('Unknown barcode'),
            'wrong_stock'     => __('Wrong stock'),
            'data_problem'    => __('Data problem'),
            'app_error'       => __('App error'),
            default           => __('Other'),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function description(array $data, ?Warehouse $warehouse, ?string $appVersion, ?string $platform): string
    {
        $details = array_filter([
            __('Sent from the staff app').($appVersion || $platform ? ' ('.trim(implode(' · ', array_filter([$platform, $appVersion]))).')' : ''),
            isset($data['screen']) ? __('Screen').': '.$data['screen'] : null,
            $warehouse ? __('Warehouse').': '.$warehouse->name : null,
            isset($data['error_code']) ? __('Error code').': '.$data['error_code'] : null,
            isset($data['support_reference']) ? __('Support reference').': '.$data['support_reference'] : null,
            isset($data['last_scan']) ? __('Last scan').': '.$data['last_scan'] : null,
        ]);

        return trim($data['details'])."\n\n---\n".implode("\n", $details);
    }
}
