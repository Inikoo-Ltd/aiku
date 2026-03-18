<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import { Link, router } from "@inertiajs/vue3"
import Tag from "@/Components/Tag.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Icon from "@/Components/Icon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faStickyNote, faUndo, faCheck, faBackspace } from "@fal"
import { ref, reactive } from "vue"
import Popover from "@/Components/Popover.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import { trans } from "laravel-vue-i18n"
import type { routeType } from "@/types/route"
import "@/Composables/Icon/PalletStateEnum"

library.add(faStickyNote, faUndo, faCheck, faBackspace)

const props = defineProps<{
    data: TableTS
    tab?: string
    pickingSession: any
    dispatchableReturns?: any[]
}>()

const isPickingLoading = ref<number | boolean>(false)
const isUnlinkLoading = ref<number | boolean>(false)
const isSubmitNotPickedLoading = ref<number | boolean>(false)

const listStatusNotPicked = [
    { label: trans("Damaged"), value: "damaged" },
    { label: trans("Lost"), value: "lost" },
    { label: trans("Other incident"), value: "other_incident" },
]

const selectedStatusNotPicked = reactive({
    status: "other_incident",
    notes: "",
})

const errorNotPicked = reactive<{
    status: string | null
    notes: string | null
}>({
    status: null,
    notes: null,
})

const isPickingFinished = () => props.pickingSession?.state === "picking_finished"
const isHandling = () => props.pickingSession?.state === "handling"

const getDispatchableReturn = (item: any) => {
    if (!props.dispatchableReturns?.length) {
        return null
    }

    const returnId = item?.pallet_return_id
    if (returnId) {
        return props.dispatchableReturns.find((r) => r.id === returnId) ?? null
    }

    return props.dispatchableReturns.find((r) => r.reference === item?.pallet_return_reference) ?? null
}

const isFirstReturnRow = (item: any) => {
    const rawIndex = item?.rowIndex ?? item?.data?.rowIndex
    const index = typeof rawIndex === "string" ? Number.parseInt(rawIndex, 10) : rawIndex
    const data = (props.data as any)?.data

    if (typeof index !== "number" || !Number.isFinite(index) || index < 0 || !Array.isArray(data)) {
        return true
    }

    if (index === 0) {
        return true
    }

    return data[index - 1]?.pallet_return_id !== item?.pallet_return_id
}

const isReturnReady = (item: any) => {
    if (props.tab === "grouped" && Array.isArray((item as any)?.pallets)) {
        return (item as any).pallets.every((row: any) => row?.pivot_state !== "picking")
    }

    const returnId = item?.pallet_return_id
    const data = (props.data as any)?.data

    if (!returnId || !Array.isArray(data)) {
        return false
    }

    return data.filter((row: any) => row?.pallet_return_id === returnId).every((row: any) => row?.pivot_state !== "picking")
}

const canPickAllReturn = (item: any) => {
    const dispatchableReturn = getDispatchableReturn(item)
    return dispatchableReturn?.state === "picking" && !!dispatchableReturn?.pickAllRoute?.name
}

const onPickAll = (item: any) => {
    const dispatchableReturn = getDispatchableReturn(item)
    const pickAllRoute = dispatchableReturn?.pickAllRoute
    if (!pickAllRoute?.name) {
        return
    }

    router[pickAllRoute.method || "post"](
        route(pickAllRoute.name, pickAllRoute.parameters),
        {},
        {
            preserveScroll: true,
        }
    )
}

const onSubmitNotPicked = async (id: number, closePopup: () => void, routeNotPicked: routeType) => {
    isSubmitNotPickedLoading.value = id
    errorNotPicked.status = null
    errorNotPicked.notes = null

    router[routeNotPicked.method || "get"](
        route(routeNotPicked.name, routeNotPicked.parameters),
        {
            state: selectedStatusNotPicked.status,
            notes: selectedStatusNotPicked.notes,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedStatusNotPicked.status = "other_incident"
                selectedStatusNotPicked.notes = ""
                closePopup()
            },
            onError: (errors: any) => {
                errorNotPicked.status = errors?.status ?? null
                errorNotPicked.notes = errors?.notes ?? null
            },
            onFinish: () => {
                isSubmitNotPickedLoading.value = false
            },
        }
    )
}

const returnRoute = (item: any) => {
    if (!item?.pallet_return_slug) {
        return null
    }

    if (item?.pallet_return_type === 'stored_item') {
        return route('grp.org.warehouses.show.dispatching.pallet-return-with-stored-items.show', [
            (route().params as any).organisation,
            (route().params as any).warehouse,
            item.pallet_return_slug
        ])
    }

    return route('grp.org.warehouses.show.dispatching.pallet-returns.show', [
        (route().params as any).organisation,
        (route().params as any).warehouse,
        item.pallet_return_slug
    ])
}

const palletRoute = (item: any) => {
    const params: any = route().params as any

    if (!item?.slug || !item?.fulfilment_slug || !item?.fulfilment_customer_slug || !params.organisation) {
        return null
    }

    return route('grp.org.fulfilments.show.crm.customers.show.pallets.show', [
        params.organisation,
        item.fulfilment_slug,
        item.fulfilment_customer_slug,
        item.slug,
    ])
}
</script>

<template>
    <Table :resource="data" :name="tab ?? ''" class="mt-5">
        <template v-if="tab === 'grouped'" #cell(pallet_return_reference)="{ item }">
            <div class="flex items-center gap-x-2">
                <Icon v-if="item?.state_icon" :data="item['state_icon']" class="px-1 shrink-0" />
                <div>
                    <Link v-if="returnRoute(item)" :href="returnRoute(item)" class="primaryLink">
                        {{ item.pallet_return_reference }}
                    </Link>
                    <div v-else>
                        {{ "-" }}
                    </div>
                </div>
            </div>
        </template>

        <template v-if="tab === 'grouped'" #cell(pallets)="{ item }">
            <div class="flex flex-col gap-y-3">
                <div
                    v-for="pallet in item.pallets"
                    :key="pallet.id"
                    class="border-b last:border-b-0 py-2"
                >
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                        <div class="min-w-[140px]">
                            <Link v-if="palletRoute(pallet)" :href="palletRoute(pallet)" class="primaryLink">
                                {{ pallet.reference }}
                            </Link>
                            <div v-else>
                                {{ pallet.reference || "-" }}
                            </div>
                        </div>

                        <div class="min-w-[160px]">
                            <div>
                                {{ pallet.customer_reference || "-" }}
                                <div v-if="pallet.notes" class="text-gray-400">
                                    <FontAwesomeIcon icon="fal fa-sticky-note" fixed-width aria-hidden="true" />
                                    <span>{{ pallet.notes }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex-1">
                            <div v-if="pallet.stored_items?.length" class="flex flex-wrap gap-x-1 gap-y-1.5">
                                <Tag
                                    v-for="storedItem of pallet.stored_items"
                                    :key="`${storedItem.reference}-${storedItem.quantity}`"
                                    :label="`${storedItem.reference} (${storedItem.quantity})`"
                                    :closeButton="false"
                                    :stringToColor="true"
                                >
                                    <template #label>
                                        <div class="whitespace-nowrap text-xs">
                                            {{ storedItem.reference }} (<span class="font-light">{{ storedItem.quantity }}</span>)
                                        </div>
                                    </template>
                                </Tag>
                            </div>
                            <div v-else class="text-gray-400 text-xs italic">
                                {{ trans("No items") }}
                            </div>
                        </div>

                        <div class="flex items-center gap-x-3 ml-auto">
                            <div>
                                <Tag v-if="pallet.location_code" :label="pallet.location_code" />
                                <div v-else class="text-gray-400 text-xs">-</div>
                            </div>

                            <div class="flex gap-x-2">
                                <template v-if="pallet.updateRoute?.name && (pallet.state === 'picking' || pallet.pivot_state === 'picking')">
                                    <Link
                                        as="div"
                                        :href="route(pallet.updateRoute.name, pallet.updateRoute.parameters)"
                                        method="patch"
                                        preserveScroll
                                        @start="() => (isPickingLoading = pallet.id)"
                                        @finish="() => (isPickingLoading = false)"
                                        v-tooltip="trans('Set as picked')"
                                    >
                                        <Button icon="fal fa-check" type="positive" :loading="isPickingLoading === pallet.id" class="py-0" />
                                    </Link>

                                    <Popover v-if="pallet.notPickedRoute?.name">
                                        <template #button="{ open }">
                                            <Button
                                                icon="fal fa-times"
                                                :type="'negative'"
                                                :key="pallet.id + open"
                                                :loading="isSubmitNotPickedLoading === pallet.id"
                                                v-tooltip="trans('Set as not picked')"
                                            />
                                        </template>
                                        <template #content="{ close }">
                                            <div class="w-[250px]">
                                                <div class="mb-3">
                                                    <div class="text-xs px-1 mb-1">
                                                        <span class="text-red-500 text-sm mr-0.5">*</span>{{ trans("Select status:") }}
                                                    </div>
                                                    <PureMultiselect
                                                        v-model="selectedStatusNotPicked.status"
                                                        @update:modelValue="() => (errorNotPicked.status = null)"
                                                        :options="listStatusNotPicked"
                                                        required
                                                        caret
                                                        :class="errorNotPicked.status ? 'errorShake' : ''"
                                                    />
                                                    <div v-if="errorNotPicked.status" class="mt-1 text-red-500 italic text-xxs">
                                                        {{ errorNotPicked.status }}
                                                    </div>
                                                </div>

                                                <div class="mb-4">
                                                    <div class="text-xs px-1 mb-1">
                                                        <span class="text-red-500 text-sm mr-0.5">*</span>{{ trans("Description:") }}
                                                    </div>
                                                    <PureTextarea
                                                        v-model="selectedStatusNotPicked.notes"
                                                        @update:modelValue="() => (errorNotPicked.notes = null)"
                                                        :placeholder="trans('Enter reason why the pallet is not picked')"
                                                        :class="errorNotPicked.notes ? 'errorShake' : ''"
                                                    />
                                                    <div v-if="errorNotPicked.notes" class="mt-1 text-red-500 italic text-xxs">
                                                        {{ errorNotPicked.notes }}
                                                    </div>
                                                </div>

                                                <div class="flex justify-end mt-2">
                                                    <Button
                                                        @click="async () => onSubmitNotPicked(pallet.id, close, pallet.notPickedRoute)"
                                                        full
                                                        :label="trans('Submit')"
                                                        :disabled="!selectedStatusNotPicked.status || !selectedStatusNotPicked.notes"
                                                        :loading="isSubmitNotPickedLoading === pallet.id"
                                                    />
                                                </div>
                                            </div>
                                        </template>
                                    </Popover>

                                    <Link
                                        v-if="pallet.unlinkRoute?.name"
                                        as="div"
                                        :href="route(pallet.unlinkRoute.name, pallet.unlinkRoute.parameters)"
                                        method="patch"
                                        preserveScroll
                                        @start="() => (isUnlinkLoading = pallet.id)"
                                        @finish="() => (isUnlinkLoading = false)"
                                        v-tooltip="trans(`Unlink pallet from this return order (Will set it as in-warehouse)`)"
                                    >
                                        <Button icon="fal fa-backspace" type="warning" :loading="isUnlinkLoading === pallet.id" class="py-0" />
                                    </Link>
                                </template>

                                <Link
                                    v-else-if="pallet.undoPickingRoute?.name && (pallet.state === 'picked' || pallet.pivot_state === 'picked')"
                                    as="div"
                                    :href="route(pallet.undoPickingRoute.name, pallet.undoPickingRoute.parameters)"
                                    method="patch"
                                    preserveScroll
                                    @start="() => (isPickingLoading = pallet.id)"
                                    @finish="() => (isPickingLoading = false)"
                                    v-tooltip="trans('Undo picking')"
                                >
                                    <Button icon="fal fa-undo" label="Undo picking" type="tertiary" size="xs" :loading="isPickingLoading === pallet.id" class="py-0" />
                                </Link>

                                <div v-else-if="pallet.status === 'incident' && pallet.state === 'lost'" class="text-red-300 italic">
                                    {{ trans("Pallet lost") }}
                                </div>
                                <div v-else-if="pallet.status === 'incident' && pallet.state === 'damaged'" class="text-red-300 italic">
                                    {{ trans("Pallet damaged") }}
                                </div>
                                <div v-else-if="pallet.pivot_state === 'cancel'" class="text-red-300 italic">
                                    {{ trans("Pallet set back to storing") }}
                                </div>
                                <div v-else class="text-gray-400">-</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template v-if="tab === 'grouped'" #cell(actions)="{ item }">
            <div v-if="isPickingFinished() && isReturnReady(item)" class="flex justify-end gap-x-2">
                <Button
                    v-if="canPickAllReturn(item)"
                    icon="fal fa-check"
                    :label="trans('Pick all')"
                    type="secondary"
                    size="xs"
                    class="py-0"
                    v-tooltip="trans('Pick all remaining pallets in this return')"
                    @click="() => onPickAll(item)"
                />

                <Link
                    v-if="getDispatchableReturn(item)?.dispatchRoute?.name && getDispatchableReturn(item)?.canDispatch"
                    as="div"
                    :href="route(getDispatchableReturn(item).dispatchRoute.name, getDispatchableReturn(item).dispatchRoute.parameters)"
                    :method="getDispatchableReturn(item).dispatchRoute.method || 'post'"
                    preserveScroll
                    v-tooltip="trans('Dispatch')"
                >
                    <Button icon="fal fa-save" :label="trans('Dispatch')" type="secondary" size="xs" class="py-0" />
                </Link>

                <Link
                    v-if="getDispatchableReturn(item)?.cancelRoute?.name"
                    as="div"
                    :href="route(getDispatchableReturn(item).cancelRoute.name, getDispatchableReturn(item).cancelRoute.parameters)"
                    :method="getDispatchableReturn(item).cancelRoute.method || 'patch'"
                    preserveScroll
                    v-tooltip="trans('Cancel')"
                >
                    <Button icon="far fa-times" :label="trans('Cancel')" type="negative" size="xs" class="py-0" />
                </Link>
            </div>
        </template>

        <template v-else #cell(pallet_return_reference)="{ item }">
            <div class="flex items-center gap-x-2">
                <Icon v-if="item?.state_icon" :data="item['state_icon']" class="px-1 shrink-0" />
                <div>
                    <Link v-if="returnRoute(item)" :href="returnRoute(item)" class="primaryLink">
                        {{ item.pallet_return_reference }}
                    </Link>
                    <div v-else>
                        {{ item.pallet_return_reference || "-" }}
                    </div>
                </div>
            </div>
        </template>

        <template #cell(reference)="{ item }">
            <Link v-if="palletRoute(item)" :href="palletRoute(item)" class="primaryLink">
                {{ item.reference }}
            </Link>
            <div v-else>
                {{ item.reference || "-" }}
            </div>
        </template>

        <template #cell(customer_reference)="{ item }">
            <div>
                {{ item.customer_reference || "-" }}
                <div v-if="item.notes" class="text-gray-400">
                    <FontAwesomeIcon icon="fal fa-sticky-note" fixed-width aria-hidden="true" />
                    <span>{{ item.notes }}</span>
                </div>
            </div>
        </template>

        <template #cell(stored_items)="{ item }">
            <div v-if="item.stored_items?.length" class="flex flex-wrap gap-x-1 gap-y-1.5">
                <Tag
                    v-for="storedItem of item.stored_items"
                    :key="`${storedItem.reference}-${storedItem.quantity}`"
                    :label="`${storedItem.reference} (${storedItem.quantity})`"
                    :closeButton="false"
                    :stringToColor="true"
                >
                    <template #label>
                        <div class="whitespace-nowrap text-xs">
                            {{ storedItem.reference }} (<span class="font-light">{{ storedItem.quantity }}</span>)
                        </div>
                    </template>
                </Tag>
            </div>
            <div v-else class="text-gray-400 text-xs italic">
                No items
            </div>
        </template>

        <template #cell(location)="{ item }">
            <Tag v-if="item.location_code" :label="item.location_code" />
            <div v-else class="text-gray-400">-</div>
        </template>

        <template #cell(actions)="{ item }">
            <div class="flex flex-col items-end gap-y-1">
                <div class="flex gap-x-2">
                    <template v-if="item.updateRoute?.name && (item.state === 'picking' || item.pivot_state === 'picking')">
                        <Link
                            as="div"
                            :href="route(item.updateRoute.name, item.updateRoute.parameters)"
                            method="patch"
                            preserveScroll
                            @start="() => (isPickingLoading = item.id)"
                            @finish="() => (isPickingLoading = false)"
                            v-tooltip="trans('Set as picked')"
                        >
                            <Button icon="fal fa-check" type="positive" :loading="isPickingLoading === item.id" class="py-0" />
                        </Link>

                        <Popover v-if="item.notPickedRoute?.name">
                            <template #button="{ open }">
                                <Button
                                    icon="fal fa-times"
                                    :type="'negative'"
                                    :key="item.id + open"
                                    :loading="isSubmitNotPickedLoading === item.id"
                                    v-tooltip="trans('Set as not picked')"
                                />
                            </template>
                            <template #content="{ close }">
                                <div class="w-[250px]">
                                    <div class="mb-3">
                                        <div class="text-xs px-1 mb-1">
                                            <span class="text-red-500 text-sm mr-0.5">*</span>{{ trans("Select status:") }}
                                        </div>
                                        <PureMultiselect
                                            v-model="selectedStatusNotPicked.status"
                                            @update:modelValue="() => (errorNotPicked.status = null)"
                                            :options="listStatusNotPicked"
                                            required
                                            caret
                                            :class="errorNotPicked.status ? 'errorShake' : ''"
                                        />
                                        <div v-if="errorNotPicked.status" class="mt-1 text-red-500 italic text-xxs">
                                            {{ errorNotPicked.status }}
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <div class="text-xs px-1 mb-1">
                                            <span class="text-red-500 text-sm mr-0.5">*</span>{{ trans("Description:") }}
                                        </div>
                                        <PureTextarea
                                            v-model="selectedStatusNotPicked.notes"
                                            @update:modelValue="() => (errorNotPicked.notes = null)"
                                            :placeholder="trans('Enter reason why the pallet is not picked')"
                                            :class="errorNotPicked.notes ? 'errorShake' : ''"
                                        />
                                        <div v-if="errorNotPicked.notes" class="mt-1 text-red-500 italic text-xxs">
                                            {{ errorNotPicked.notes }}
                                        </div>
                                    </div>

                                    <div class="flex justify-end mt-2">
                                        <Button
                                            @click="async () => onSubmitNotPicked(item.id, close, item.notPickedRoute)"
                                            full
                                            :label="trans('Submit')"
                                            :disabled="!selectedStatusNotPicked.status || !selectedStatusNotPicked.notes"
                                            :loading="isSubmitNotPickedLoading === item.id"
                                        />
                                    </div>
                                </div>
                            </template>
                        </Popover>

                        <Link
                            v-if="item.unlinkRoute?.name"
                            as="div"
                            :href="route(item.unlinkRoute.name, item.unlinkRoute.parameters)"
                            method="patch"
                            preserveScroll
                            @start="() => (isUnlinkLoading = item.id)"
                            @finish="() => (isUnlinkLoading = false)"
                            v-tooltip="trans(`Unlink pallet from this return order (Will set it as in-warehouse)`)"
                        >
                            <Button icon="fal fa-backspace" type="warning" :loading="isUnlinkLoading === item.id" class="py-0" />
                        </Link>
                    </template>

                    <Link
                        v-else-if="item.undoPickingRoute?.name && (item.state === 'picked' || item.pivot_state === 'picked')"
                        as="div"
                        :href="route(item.undoPickingRoute.name, item.undoPickingRoute.parameters)"
                        method="patch"
                        preserveScroll
                        @start="() => (isPickingLoading = item.id)"
                        @finish="() => (isPickingLoading = false)"
                        v-tooltip="trans('Undo picking')"
                    >
                        <Button icon="fal fa-undo" label="Undo picking" type="tertiary" size="xs" :loading="isPickingLoading === item.id" class="py-0" />
                    </Link>

                    <div v-else-if="item.status === 'incident' && item.state === 'lost'" class="text-red-300 italic">
                        {{ trans("Pallet lost") }}
                    </div>
                    <div v-else-if="item.status === 'incident' && item.state === 'damaged'" class="text-red-300 italic">
                        {{ trans("Pallet damaged") }}
                    </div>
                    <div v-else-if="item.pivot_state === 'cancel'" class="text-red-300 italic">
                        {{ trans("Pallet set back to storing") }}
                    </div>
                    <div v-else class="text-gray-400">-</div>
                </div>

                <div v-if="isFirstReturnRow(item) && isPickingFinished() && isReturnReady(item) && canPickAllReturn(item)" class="flex justify-end">
                    <Button
                        icon="fal fa-check"
                        :label="trans('Pick all')"
                        type="secondary"
                        size="xs"
                        class="py-0"
                        v-tooltip="trans('Pick all remaining pallets in this return')"
                        @click="() => onPickAll(item)"
                    />
                </div>

                <div v-if="isFirstReturnRow(item) && isPickingFinished() && isReturnReady(item)" class="flex gap-x-2">
                    <Link
                        v-if="getDispatchableReturn(item)?.dispatchRoute?.name && getDispatchableReturn(item)?.canDispatch"
                        as="div"
                        :href="route(getDispatchableReturn(item).dispatchRoute.name, getDispatchableReturn(item).dispatchRoute.parameters)"
                        :method="getDispatchableReturn(item).dispatchRoute.method || 'post'"
                        preserveScroll
                        v-tooltip="trans('Dispatch')"
                    >
                        <Button icon="fal fa-save" :label="trans('Dispatch')" type="secondary" size="xs" class="py-0" />
                    </Link>

                    <Link
                        v-if="getDispatchableReturn(item)?.cancelRoute?.name"
                        as="div"
                        :href="route(getDispatchableReturn(item).cancelRoute.name, getDispatchableReturn(item).cancelRoute.parameters)"
                        :method="getDispatchableReturn(item).cancelRoute.method || 'patch'"
                        preserveScroll
                        v-tooltip="trans('Cancel')"
                    >
                        <Button icon="far fa-times" :label="trans('Cancel')" type="negative" size="xs" class="py-0" />
                    </Link>
                </div>
            </div>
        </template>
    </Table>
</template>
