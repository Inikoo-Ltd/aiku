<!--
* Author: Vika Aqordi
* Created on: 2026-07-10
* Github: https://github.com/aqordeon
* Copyright: 2026
-->

<script setup lang="ts">
import { ref, computed } from "vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { Dialog } from "primevue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faForklift } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import MoveStock from "./MoveStock.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

library.add(faForklift)

const props = defineProps<{
    fetchRoute: routeType
    buttonSize?: string
    buttonType?: string
    buttonLabel?: string
}>()

const isOpen = ref(false)
const isLoading = ref(false)
const showcaseData = ref<any>(null)

const locations = computed<any[]>(() => showcaseData.value?.stocks_management?.locations ?? [])
const replenishmentData = computed<Record<number, { replenishment_stock?: number }>>(() => {
    const map: Record<number, { replenishment_stock?: number }> = {}
    for (const location of locations.value) {
        map[location.id] = { replenishment_stock: location.settings?.replenishment_stock ?? undefined }
    }
    return map
})

const open = async () => {
    isOpen.value = true
    isLoading.value = true
    showcaseData.value = null

    try {
        const response = await axios.get(route(props.fetchRoute.name, props.fetchRoute.parameters))
        showcaseData.value = response.data
    } catch (error) {
        notify({
            title: ctrans("Something went wrong"),
            text: ctrans("Failed to load stock. Try again"),
            type: "error",
        })
        isOpen.value = false
    } finally {
        isLoading.value = false
    }
}
</script>

<template>
    <div class="w-fit">
        <Button
            :type="buttonType ?? 'tertiary'"
            :size="buttonSize ?? 'xs'"
            icon="fal fa-forklift"
            :label="buttonLabel ?? ctrans('Move stock')"
            v-tooltip="ctrans('Move stock between locations')"
            @click="open"
        />

        <!-- Modal: Move stock (closable only via X to avoid misclicks messing the process) -->
        <Dialog
            v-model:visible="isOpen"
            modal
            :header="ctrans('Move stock')"
            :draggable="false"
            :dismissableMask="false"
            :closeOnEscape="false"
            :focusOnShow="false"
            :style="{ width: '56rem' }"
            :breakpoints="{ '1280px': '75vw', '992px': '85vw', '768px': '92vw', '576px': '96vw' }"
            :contentStyle="{ maxHeight: '75vh', overflow: 'auto' }"
        >
            <div v-if="isLoading" class="flex flex-col items-center justify-center py-16 gap-3 text-gray-400">
                <LoadingIcon class="text-3xl" />
                <span class="italic text-sm">{{ ctrans('Loading...') }}</span>
            </div>

            <MoveStock
                v-else-if="locations.length"
                :part_locations="locations"
                :replenishment_data="replenishmentData"
                @close="isOpen = false"
            />

            <div v-else class="text-center py-10 text-gray-400 italic">
                {{ ctrans('No locations for this item') }}
            </div>
        </Dialog>
    </div>
</template>
