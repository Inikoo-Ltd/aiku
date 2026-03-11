<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import { Link } from "@inertiajs/vue3"
import Tag from "@/Components/Tag.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faStickyNote, faUndo, faCheck } from "@fal"
import { ref } from "vue"

library.add(faStickyNote, faUndo, faCheck)

defineProps<{
    data: TableTS
    tab?: string
    pickingSession: object
}>()

const isPickingLoading = ref<number | boolean>(false)

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
    if (!item?.location_slug || !item?.slug) {
        return null
    }

    return route('grp.org.warehouses.show.fulfilment.locations.show.pallets.show', {
        organisation: (route().params as any).organisation,
        warehouse: (route().params as any).warehouse,
        location: item.location_slug,
        pallet: item.slug,
    })
}
</script>

<template>
    <Table :resource="data" :name="tab ?? ''" class="mt-5">
        <template #cell(pallet_return_reference)="{ item }">
            <Link v-if="returnRoute(item)" :href="returnRoute(item)" class="primaryLink">
                {{ item.pallet_return_reference }}
            </Link>
            <div v-else>
                {{ item.pallet_return_reference || "-" }}
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
            <div class="flex gap-x-2">
                <Link
                    v-if="item.updateRoute?.name && (item.state === 'picking' || item.pivot_state === 'picking')"
                    as="div"
                    :href="route(item.updateRoute.name, item.updateRoute.parameters)"
                    method="patch"
                    preserveScroll
                    @start="() => isPickingLoading = item.id"
                    @finish="() => isPickingLoading = false"
                >
                    <Button icon="fal fa-check" type="positive" :loading="isPickingLoading === item.id" class="py-0" />
                </Link>

                <Link
                    v-if="item.undoPickingRoute?.name && (item.state === 'picked' || item.pivot_state === 'picked')"
                    as="div"
                    :href="route(item.undoPickingRoute.name, item.undoPickingRoute.parameters)"
                    method="patch"
                    preserveScroll
                    @start="() => isPickingLoading = item.id"
                    @finish="() => isPickingLoading = false"
                >
                    <Button icon="fal fa-undo" type="tertiary" size="xs" :loading="isPickingLoading === item.id" class="py-0" />
                </Link>
            </div>
        </template>
    </Table>
</template>
