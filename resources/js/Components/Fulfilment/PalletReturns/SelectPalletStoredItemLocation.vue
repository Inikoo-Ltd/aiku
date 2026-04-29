<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { RouteParams } from "@/types/route-params"

const props = defineProps<{
    item: {
        reference: string
        pallet_stored_items: any[]
    }
    selectedPalletStoredItemId?: number | null
}>()

const emit = defineEmits<{
    select: [palletStoredItemId: number]
}>()

const generatePalletRoute = (palletStoredItem: any): string | null => {
    const palletIdentifier = palletStoredItem.pallet_slug ?? palletStoredItem.reference
    const params = route().params as RouteParams

    if (!palletIdentifier || !params.organisation || !params.warehouse) {
        return null
    }

    return String(route("grp.org.warehouses.show.inventory.pallets.current.show", {
        organisation: params.organisation,
        warehouse: params.warehouse,
        pallet: palletIdentifier,
    }))
}

const generateLocationRoute = (palletStoredItem: any): string | null => {
    const locationSlug = palletStoredItem.location?.slug
    const params = route().params as RouteParams

    if (!locationSlug || !params.organisation || !params.warehouse) {
        return null
    }

    return String(route("grp.org.warehouses.show.infrastructure.locations.show", {
        organisation: params.organisation,
        warehouse: params.warehouse,
        location: locationSlug,
    }))
}
</script>

<template>
    <div>
        <div class="text-center font-semibold mb-4 text-2xl">
            {{ trans("Location list for item") }} {{ item?.reference }}
        </div>

        <div class="rounded p-1 grid grid-cols-1 lg:grid-cols-2 gap-3">
            <label
                v-for="palletStoredItem in item?.pallet_stored_items"
                :key="palletStoredItem.id"
                class="border border-slate-300 rounded w-full flex justify-between gap-x-3 items-center px-2 py-2 cursor-pointer"
                :for="`pallet-stored-item-${palletStoredItem.id}`"
            >
                <div class="flex flex-wrap items-center gap-1">
                    <Link
                        v-if="generateLocationRoute(palletStoredItem)"
                        :href="generateLocationRoute(palletStoredItem)!"
                        class="bg-gradient-to-t from-yellow-300/50 to-yellow-200/50 px-1 secondaryLink"
                    >
                        {{ palletStoredItem.location?.code || "-" }}
                    </Link>
                    <span v-else class="bg-gradient-to-t from-yellow-300/50 to-yellow-200/50 px-1">
                        {{ palletStoredItem.location?.code || "-" }}
                    </span>

                    <Link
                        v-if="generatePalletRoute(palletStoredItem)"
                        :href="generatePalletRoute(palletStoredItem)!"
                        class="px-1 secondaryLink"
                    >
                        {{ palletStoredItem.reference || "-" }}
                    </Link>
                    <span v-else class="px-1">
                        {{ palletStoredItem.reference || "-" }}
                    </span>

                    <span class="ml-1 whitespace-nowrap text-gray-500 tabular-nums border rounded px-1 text-xs border-gray-300">
                        {{ Number(palletStoredItem.quantity_in_pallet || 0) }} {{ trans("stocks") }}
                    </span>
                </div>

                <input
                    :id="`pallet-stored-item-${palletStoredItem.id}`"
                    type="radio"
                    name="pallet-stored-item"
                    :value="palletStoredItem.id"
                    :checked="Number(selectedPalletStoredItemId) === Number(palletStoredItem.id)"
                    @change="emit('select', palletStoredItem.id)"
                    class="size-4"
                />
            </label>
        </div>
    </div>
</template>
