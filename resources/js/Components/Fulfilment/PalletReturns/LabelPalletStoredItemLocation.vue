<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInventory } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"

library.add(faInventory)

const props = defineProps<{
    palletStoredItems: any[]
    selectedPalletStoredItemId?: number | null
    locationHref?: string
}>()

const emit = defineEmits<{
    openLocationModal: []
}>()

const currentPalletStoredItem = computed(() => {
    if (!props.palletStoredItems?.length) {
        return null
    }

    if (props.selectedPalletStoredItemId) {
        const selected = props.palletStoredItems.find((item) => item.id === props.selectedPalletStoredItemId)
        if (selected) {
            return selected
        }
    }

    return props.palletStoredItems.find((item) => Number(item.selected_quantity || 0) > 0) ?? props.palletStoredItems[0]
})

const otherLocationsCount = computed(() => {
    if (!props.palletStoredItems?.length) {
        return 0
    }

    const currentId = Number(currentPalletStoredItem.value?.id || props.selectedPalletStoredItemId || 0)

    return props.palletStoredItems.filter((item) => {
        return Number(item?.id || 0) !== currentId && Number(item?.selected_quantity || 0) <= 0
    }).length
})
</script>

<template>
    <div class="flex items-center gap-x-2">
        <span
            v-if="otherLocationsCount > 0"
            class="cursor-pointer whitespace-nowrap py-0.5 px-1 border border-orange-300 rounded text-gray-400 hover:bg-orange-50"
            v-tooltip="trans('Other :number locations', { number: String(otherLocationsCount) })"
            @click="emit('openLocationModal')"
        >
            <FontAwesomeIcon icon="fal fa-inventory" class="mr-1" fixed-width aria-hidden="true" />
            {{ otherLocationsCount }}
        </span>

        <span v-if="currentPalletStoredItem" class="text-sm">
            <Link v-if="locationHref" :href="locationHref" class="secondaryLink">
                {{ currentPalletStoredItem.location?.code || currentPalletStoredItem.reference || '-' }}
            </Link>
            <span v-else>
                {{ currentPalletStoredItem.location?.code || currentPalletStoredItem.reference || '-' }}
            </span>
            <span class="text-gray-700">
                (<span class="font-bold">{{ currentPalletStoredItem.quantity_in_pallet || 0 }}</span> {{ trans('Stocks') }})
            </span>
        </span>
    </div>
</template>
