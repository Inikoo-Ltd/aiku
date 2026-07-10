<!--
* Author: Vika Aqordi
* Created on: 2026-04-22 09:25
* Github: https://github.com/aqordeon
* Copyright: 2026
-->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInventory, faForklift } from "@fal"
import { RadioButton, Dialog } from "primevue"
import { twBreakPoint } from "@/Composables/useWindowSize"
import { RouteParams } from "@/types/route-params"
import { ref, onUnmounted } from "vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import StocksManagement from "@/Components/Warehouse/Inventory/StocksManagement/StocksManagement.vue"
import { ctrans } from "@/Composables/useTrans"

library.add(faInventory, faForklift)

const props = defineProps<{
    item: {
        id: number
        org_stock_code: string
        locations: {
            location_code: string
            quantity: number
        }[]
    }
    selectedLocationCode: string
    ignoreNoQty?: boolean
}>()

const emit = defineEmits<{
    close: []
    select: [locationCode: string]
}>()

const generateLocationRoute = (location: any): string => {
    if (!location.location_slug) return "#"
    return route("grp.org.warehouses.show.infrastructure.locations.show", [
        (route().params as RouteParams).organisation,
        (route().params as RouteParams).warehouse,
        location.location_slug,
    ])
}

// Section: Stock management (move stock while picking)
const isStockManagementOpen = ref(false)
const isLoadingStockManagement = ref(false)
const stockManagementData = ref<any>(null)
let removeInertiaSuccessListener: (() => void) | null = null

const fetchStockManagement = async () => {
    const response = await axios.get(
        route("grp.json.delivery_note_item.stocks_management", {
            deliveryNoteItem: props.item.id,
        })
    )
    stockManagementData.value = response.data
}

// Keep the location list (and the modal) in sync after any stock mutation done inside StocksManagement
const refreshAfterStockMutation = async () => {
    if (!props.item?.id) return

    try {
        const [, rowResponse] = await Promise.all([
            fetchStockManagement(),
            axios.get(
                route("grp.json.delivery_note_item_row", {
                    deliveryNoteItem: props.item.id,
                })
            ),
        ])

        if (rowResponse.data?.data) {
            Object.assign(props.item, rowResponse.data.data)
        }
    } catch (error) {
        // Silent: a stale view is preferable to interrupting the picker with an error
    }
}

const openStockManagement = async () => {
    if (!props.item?.id) return

    isStockManagementOpen.value = true
    isLoadingStockManagement.value = true
    stockManagementData.value = null

    if (!removeInertiaSuccessListener) {
        removeInertiaSuccessListener = router.on("success", () => refreshAfterStockMutation())
    }

    try {
        await fetchStockManagement()
    } catch (error) {
        notify({
            title: ctrans("Something went wrong"),
            text: ctrans("Failed to load stock management. Try again"),
            type: "error",
        })
        closeStockManagement()
    } finally {
        isLoadingStockManagement.value = false
    }
}

const closeStockManagement = () => {
    isStockManagementOpen.value = false
    if (removeInertiaSuccessListener) {
        removeInertiaSuccessListener()
        removeInertiaSuccessListener = null
    }
}

onUnmounted(() => {
    if (removeInertiaSuccessListener) {
        removeInertiaSuccessListener()
        removeInertiaSuccessListener = null
    }
})
</script>

<template>
    <div>
        <div class="text-center font-semibold mb-4 text-2xl">
            {{ ctrans('Location list for') }} {{ item?.org_stock_code }}
        </div>

        <div class="flex justify-center mb-4">
            <Button
                type="tertiary"
                size="sm"
                icon="fal fa-forklift"
                :label="ctrans('Manage stock')"
                v-tooltip="ctrans('Move stock between locations')"
                @click="openStockManagement"
            />
        </div>
        
        <div class="rounded p-1 grid grid-cols-2 lg:grid-cols-3 gap-3">
            <div
                v-for="location in item?.locations"
                :key="location.location_code"
                class="xbg-slate-100 border border-slate-300 rounded w-full flex justify-between gap-x-3 items-center px-2 py-1"
            >
                <label :for="location.location_code" class="flex flex-wrap">
                    <span
                        v-if="location.location_code"
                        v-tooltip="location.quantity <= 0 ? ctrans('Location has no stock') : ''"
                        :class="location.quantity <= 0 ? 'text-gray-400' : ''"
                    >
                        <Link :href="generateLocationRoute(location)" class="bg-gradient-to-t from-yellow-300/50 to-yellow-200/50 px-1">
                            {{ location.location_code }}
                        </Link>
                    </span>
                    <span v-else class="text-gray-400 italic">({{ ctrans('Unknown') }})</span>
                    <span v-tooltip="ctrans('Total stock in this location')" class="ml-1 whitespace-nowrap text-gray-400 tabular-nums border  rounded px-1 text-xs"
                        :class="Number(location.quantity) > 0 ? 'border-gray-300' : 'border-red-300 opacity-70'"
                    >
                        <span v-if="Number(location.quantity) > 0">{{ Number(location.quantity) }} {{ ctrans("stocks") }}</span>
                        <span v-else class="text-red-500 italic">{{ ctrans('Empty') }}</span>
                    </span>
                </label>

                <RadioButton
                    :modelValue="selectedLocationCode"
                    @update:modelValue="(e: string) => emit('select', e)"
                    :size="twBreakPoint().includes('lg') ? undefined : 'large'"
                    :inputId="location.location_code"
                    :disabled="ignoreNoQty ? false : location.quantity <= 0 "
                    name="location"
                    :value="location.location_code"
                />
            </div>
        </div>

        <!-- Modal: Stock management (closable only via X to avoid misclicks messing the process) -->
        <Dialog
            v-model:visible="isStockManagementOpen"
            modal
            :header="ctrans('Stocks management')"
            :draggable="false"
            :dismissableMask="false"
            :closeOnEscape="false"
            :focusOnShow="false"
            :style="{ width: '56rem' }"
            :breakpoints="{ '1280px': '75vw', '992px': '85vw', '768px': '92vw', '576px': '96vw' }"
            :contentStyle="{ maxHeight: '75vh', overflow: 'auto' }"
            @hide="closeStockManagement"
        >
            <div v-if="isLoadingStockManagement" class="flex flex-col items-center justify-center py-16 gap-3 text-gray-400">
                <LoadingIcon class="text-3xl" />
                <span class="italic text-sm">{{ ctrans('Loading stock management...') }}</span>
            </div>

            <StocksManagement
                v-else-if="stockManagementData?.stocks_management"
                :stocks_management="stockManagementData.stocks_management"
                :trade_units="stockManagementData.trade_units"
                :actions="['move_stock']"
                :data="{
                    is_quantity_excess: stockManagementData.is_quantity_excess,
                    currency_code: stockManagementData.currency_code,
                }"
            />
        </Dialog>
    </div>
</template>
