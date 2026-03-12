<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import Tag from "@/Components/Tag.vue"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import type { Table as TableTS } from "@/types/Table"
import { Collapse } from "vue-collapsed"
import { get, set } from "lodash-es"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faArrowDown } from "@fal"
import { ref, computed } from "vue"

library.add(faArrowDown)

const props = defineProps<{
    data: TableTS
    tab?: string
    pickingSession: anys
    dispatchableReturns?: any[]
}>()

const isLoadingUndoPick = ref<Record<number, boolean>>({})

const onUndoPick = (undoRoute: any, palletStoredItem: any) => {
    const id = palletStoredItem?.id
    if (!id || !undoRoute?.name) {
        return
    }

    isLoadingUndoPick.value[id] = true

    router.patch(
        route(undoRoute.name, undoRoute.parameters),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isLoadingUndoPick.value[id] = false
            },
        }
    )
}

const returnRoute = (item: any) => {
    if (!item?.pallet_return_slug) {
        return null
    }

    return route('grp.org.warehouses.show.dispatching.pallet-return-with-stored-items.show', [
        (route().params as any).organisation,
        (route().params as any).warehouse,
        item.pallet_return_slug
    ])
}

const storedItemRoute = (item: any) => {
    if (!item?.slug) {
        return null
    }

    return route('grp.org.warehouses.show.inventory.stored_items.current.show', [
        (route().params as any).organisation,
        (route().params as any).warehouse,
        item.slug
    ])
}

const palletRoute = (palletStoredItem: any) => {
    if (!palletStoredItem?.pallet_slug || !palletStoredItem?.location?.slug) {
        return null
    }

    return route('grp.org.warehouses.show.fulfilment.locations.show.pallets.show', {
        organisation: (route().params as any).organisation,
        warehouse: (route().params as any).warehouse,
        location: palletStoredItem.location.slug,
        pallet: palletStoredItem.pallet_slug,
    })
}

const isRowPicking = (item: any) => item?.pallet_return_state === 'picking'
const canRowDispatch = (item: any) => !!getDispatchableReturn(item)?.canDispatch
const isPickingFinished = () => props.pickingSession?.state === 'picking_finished'
const groupMode = computed<'by_item' | 'by_return'>(() => {
    if (props?.tab === 'grouped') return 'by_return'
    return 'by_item'
})

const getDispatchableReturn = (item: any) => {
    if (!props.dispatchableReturns?.length) {
        return null
    }

    const returnId = item?.pallet_return_id
    if (returnId) {
        return props.dispatchableReturns.find(r => r.id === returnId) ?? null
    }

    return props.dispatchableReturns.find(r => r.reference === item?.pallet_return_reference) ?? null
}

const collapsedGroups = ref<Record<number, boolean>>({})
const isGroupCollapsed = (item: any) => {
    const id = item?.pallet_return_id ?? item?.data?.pallet_return_id
    return !!collapsedGroups.value[id]
}
const toggleGroup = (item: any) => {
    const id = item?.pallet_return_id ?? item?.data?.pallet_return_id
    if (!id) return
    collapsedGroups.value[id] = !collapsedGroups.value[id]
}

const isFirstReturnRow = (item: any) => {
    const rawIndex = item?.rowIndex ?? item?.data?.rowIndex
    const index = typeof rawIndex === 'string' ? Number.parseInt(rawIndex, 10) : rawIndex
    const data = (props.data as any)?.data
    const palletReturnId = item?.pallet_return_id ?? item?.data?.pallet_return_id

    if (typeof index !== 'number' || !Number.isFinite(index) || index < 0 || !Array.isArray(data)) {
        return true
    }

    if (index === 0) {
        return true
    }

    return data[index - 1]?.pallet_return_id !== palletReturnId
}

const getRequestedPallets = (storedItem: any) => {
    const items = storedItem?.pallet_stored_items || []
    return items.filter((ps: any) => (ps?.selected_quantity ?? 0) > 0 || (ps?.available_to_pick_quantity ?? 0) > 0)
}
const getHiddenPallets = (storedItem: any) => {
    const items = storedItem?.pallet_stored_items || []
    return items.filter((ps: any) => !((ps?.selected_quantity ?? 0) > 0 || (ps?.available_to_pick_quantity ?? 0) > 0))
}
</script>

<template>
    <Table :resource="data" :name="tab ?? ''" class="mt-5">
        <template #cell(pallet_return_reference)="{ item }">
            <template v-if="isFirstReturnRow(item)">
                <Link v-if="returnRoute(item)" :href="returnRoute(item)" class="primaryLink">
                    {{ item.pallet_return_reference }}
                </Link>
                <div v-else>
                    {{ item.pallet_return_reference || "-" }}
                </div>
            </template>
            <template v-else>
                <div class="-mt-px pt-px">
                    <span class="invisible">-</span>
                </div>
            </template>
        </template>

        <template #cell(reference)="{ item }">
            <Link v-if="storedItemRoute(item)" :href="storedItemRoute(item)" class="primaryLink">
                {{ item.reference }}
            </Link>
            <div v-else>
                {{ item.reference || "-" }}
            </div>
        </template>

        <template #cell(total_quantity)="{ item }">
            <div class="tabular-nums text-right">
                {{ item.total_quantity ?? 0 }}
            </div>
        </template>

        <template #cell(pallet_stored_items)="{ item: storedItem, proxyItem }">
            <div v-if="storedItem.pallet_stored_items?.length" class="space-y-2">
                <!-- Requested/selected pallets first -->
                <div v-for="palletStoredItem in getRequestedPallets(storedItem)" :key="`req-${palletStoredItem.id}`" class="flex justify-between gap-x-4">
                    <div>
                        <Link v-if="palletRoute(palletStoredItem)" :href="palletRoute(palletStoredItem)" class="primaryLink">
                            {{ palletStoredItem.reference }}
                        </Link>
                        <span v-else>
                            {{ palletStoredItem.reference || "-" }}
                        </span>
                        <span v-if="palletStoredItem.location?.code" class="text-gray-400"> [{{ palletStoredItem.location.code }}]</span>

                        <div class="text-gray-400 tabular-nums">
                            {{ trans('Stocks in pallet') }}: {{ palletStoredItem.quantity_in_pallet ?? 0 }}
                        </div>
                    </div>

                    <div v-if="isRowPicking(storedItem)" class="shrink-0">
                        <div v-if="palletStoredItem.state === 'picked'" class="flex items-center gap-x-2 tabular-nums">
                            <Button
                                @click="() => onUndoPick(palletStoredItem.undoRoute, palletStoredItem)"
                                icon="fal fa-undo-alt"
                                :label="trans('Undo pick')"
                                size="xs"
                                type="tertiary"
                                :loading="get(isLoadingUndoPick, palletStoredItem.id, false)"
                                class="py-0"
                            />
                            <span class="text-gray-500">
                                {{ palletStoredItem.picked_quantity ?? 0 }}/{{ palletStoredItem.selected_quantity ?? 0 }}
                            </span>
                        </div>

                        <NumberWithButtonSave
                            v-else
                            noUndoButton
                            :modelValue="palletStoredItem.selected_quantity ?? 0"
                            saveOnForm
                            :routeSubmit="palletStoredItem.pallet_return_item_id ? palletStoredItem.updateRoute : palletStoredItem.newPickRoute"
                            :keySubmit="palletStoredItem.pallet_return_item_id ? 'quantity_picked' : 'quantity_ordered'"
                            :bindToTarget="{
                                step: 1,
                                min: 0,
                                max: palletStoredItem.max_quantity ?? 0
                            }"
                        >
                            <template #save="{ isProcessing, onSaveViaForm }">
                                <Button
                                    v-if="(palletStoredItem.selected_quantity ?? 0) > 0"
                                    @click="() => onSaveViaForm()"
                                    icon="fal fa-save"
                                    :label="trans('pick')"
                                    size="xs"
                                    type="secondary"
                                    :loading="isProcessing"
                                    class="py-0"
                                />
                            </template>
                        </NumberWithButtonSave>
                    </div>
                </div>

                <Collapse as="section" :when="get(proxyItem, ['is_open_collapsed'], false)">
                    <div class="space-y-2">
                        <div v-for="palletStoredItem in getHiddenPallets(storedItem)" :key="`hid-${palletStoredItem.id}`" class="flex justify-between gap-x-4">
                            <div>
                                <Link v-if="palletRoute(palletStoredItem)" :href="palletRoute(palletStoredItem)" class="secondaryLink">
                                    {{ palletStoredItem.reference }}
                                </Link>
                                <span v-else>
                                    {{ palletStoredItem.reference || "-" }}
                                </span>
                                <span v-if="palletStoredItem.location?.code" class="text-gray-400"> [{{ palletStoredItem.location.code }}]</span>
                                <div class="text-gray-400 tabular-nums">
                                    {{ trans('Stocks in pallet') }}: {{ palletStoredItem.quantity_in_pallet ?? 0 }}
                                </div>
                            </div>
                        </div>
                    </div>
                </Collapse>

                <div v-if="getHiddenPallets(storedItem).length" class="w-full mt-2">
                    <Button
                        type="dashed"
                        full
                        size="sm"
                        @click="() => set(proxyItem, ['is_open_collapsed'], !get(proxyItem, ['is_open_collapsed'], false))"
                    >
                        <div class="py-1 text-gray-500">
                            <FontAwesomeIcon
                                icon="fal fa-arrow-down"
                                class="transition-all"
                                :class="get(proxyItem, ['is_open_collapsed'], false) ? 'rotate-180' : ''"
                                fixed-width
                                aria-hidden="true"
                            />
                            {{ get(proxyItem, ['is_open_collapsed'], false) ? 'Close' : 'Open hidden pallets' }}
                        </div>
                    </Button>
                </div>
            </div>

            <div v-else class="text-gray-400 italic">
                {{ trans('No pallet') }}
            </div>
        </template>

        <template #cell(total_quantity_ordered)="{ item }">
            <div class="tabular-nums text-right">
                {{ item.total_quantity_ordered ?? 0 }}
            </div>
        </template>

        <template #cell(actions)="{ item }">
            <div v-if="isFirstReturnRow(item) && isPickingFinished()" class="flex justify-end">
                <Link
                    v-if="canRowDispatch(item)"
                    as="div"
                    :href="route(getDispatchableReturn(item).dispatchRoute.name, getDispatchableReturn(item).dispatchRoute.parameters)"
                    method="post"
                    preserveScroll
                >
                    <Button icon="fal fa-save" label="Set as dispatched" type="secondary" size="xs" class="py-0" />
                </Link>
            </div>
            <div v-else class="-mt-px pt-px"></div>
        </template>
    </Table>
</template>
