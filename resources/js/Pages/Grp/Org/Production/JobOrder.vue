<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 09 Aug 2026 10:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { ref, computed, watch } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSortShapesDown, faPlus } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faSortShapesDown, faPlus)

interface ItemTask {
    id: number
    position: number
    task_name: string
    state: string
    quantity_required: number
    quantity_made: number
    quantity_rejected: number
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    job_order: {
        id: number
        reference: string
        state: string
        state_label: string
        date: string | null
        public_notes: string | null
    }
    items: {
        id: number
        artefact_code: string
        artefact_name: string
        quantity: number
        produced_quantity: number
        tasks: ItemTask[]
    }[]
    artefact_options: { id: number, code: string, name: string, has_recipe: boolean, recommended_batch_size: number | null }[]
    add_item_route: null | { name: string, parameters: object }
    confirm_route: null | { name: string, parameters: object }
    receive_route: null | { name: string, parameters: object }
    locations_fetch_route: null | { name: string, parameters: object }
}>()

function confirmJobOrder() {
    if (!props.confirm_route) return
    processing.value = true
    router.patch(
        route(props.confirm_route.name, props.confirm_route.parameters),
        {},
        { preserveScroll: true, onFinish: () => processing.value = false }
    )
}

const newArtefactId = ref<number | null>(null)
const newQuantity = ref<number | null>(null)
const processing = ref(false)

const selectedArtefactOption = computed(() =>
    props.artefact_options.find(option => option.id === newArtefactId.value) ?? null
)

watch(newArtefactId, () => {
    if (!newQuantity.value && selectedArtefactOption.value?.recommended_batch_size) {
        newQuantity.value = selectedArtefactOption.value.recommended_batch_size
    }
})

function addItem() {
    if (!props.add_item_route || !newArtefactId.value || !newQuantity.value) return
    processing.value = true
    router.post(
        route(props.add_item_route.name, props.add_item_route.parameters),
        {
            artefact_id: newArtefactId.value,
            quantity: newQuantity.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false
                newArtefactId.value = null
                newQuantity.value = null
            },
        }
    )
}

function progressPercent(task: ItemTask) {
    if (!task.quantity_required) return 0
    return Math.min(100, Math.round(task.quantity_made / task.quantity_required * 100))
}

const locationQuery = ref("")
const locationResults = ref<{ id: number, code: string }[]>([])
const selectedLocation = ref<{ id: number, code: string } | null>(null)
const searchingLocations = ref(false)

async function searchLocations() {
    if (!props.locations_fetch_route || !locationQuery.value) {
        locationResults.value = []
        return
    }
    searchingLocations.value = true
    try {
        const response = await axios.get(route(props.locations_fetch_route.name, props.locations_fetch_route.parameters), {
            params: { "filter[global]": locationQuery.value },
        })
        locationResults.value = response.data.data ?? []
    } finally {
        searchingLocations.value = false
    }
}

function pickLocation(location: { id: number, code: string }) {
    selectedLocation.value = location
    locationResults.value = []
    locationQuery.value = location.code
}

function receiveIntoStock() {
    if (!props.receive_route || !selectedLocation.value) return
    processing.value = true
    router.patch(
        route(props.receive_route.name, props.receive_route.parameters),
        { location_id: selectedLocation.value.id },
        { preserveScroll: true, onFinish: () => processing.value = false }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 py-4 max-w-4xl">
        <div class="flex items-center gap-4 text-sm text-gray-600 mb-6">
            <span class="rounded-full bg-gray-100 border border-gray-200 px-3 py-1 font-medium">{{ job_order.state_label }}</span>
            <span v-if="job_order.date">{{ useFormatTime(job_order.date) }}</span>
            <span v-if="job_order.public_notes" class="truncate">{{ job_order.public_notes }}</span>
            <button
                v-if="confirm_route"
                type="button"
                class="ml-auto rounded bg-green-600 text-white text-sm font-semibold px-4 py-2 disabled:opacity-40"
                :disabled="processing || !items.length"
                @click="confirmJobOrder"
            >
                {{ trans('Release to floor') }}
            </button>
        </div>
        <div v-if="confirm_route" class="mb-6 -mt-3 text-sm text-amber-600">
            {{ trans('Draft — workers cannot see these tasks until the order is released to the floor') }}
        </div>

        <div v-if="!items.length" class="text-gray-400 text-center py-10 border border-dashed border-gray-200 rounded-lg mb-6">
            {{ trans('No items yet. Add an artefact to manufacture.') }}
        </div>

        <div v-for="item in items" :key="item.id" class="mb-4 rounded-xl border border-gray-200 bg-white p-4">
            <div class="flex items-baseline justify-between">
                <div class="font-semibold">
                    {{ item.artefact_code }}
                    <span class="text-gray-500 font-normal ml-2">{{ item.artefact_name }}</span>
                </div>
                <div class="tabular-nums text-gray-700">× {{ item.quantity }}</div>
            </div>

            <div v-if="!item.tasks.length" class="mt-2 text-sm text-amber-600">
                {{ trans('This artefact has no recipe, no tasks were generated') }}
            </div>
            <div v-for="task in item.tasks" :key="task.id" class="mt-3">
                <div class="flex items-baseline justify-between text-sm">
                    <div>
                        <span class="text-gray-400 mr-1">{{ task.position }}.</span>
                        {{ task.task_name }}
                        <span v-if="task.state == 'done'" class="ml-2 text-green-600 font-medium">{{ trans('Done') }}</span>
                        <span v-else-if="task.state == 'in_progress'" class="ml-2 text-amber-600 font-medium">{{ trans('In progress') }}</span>
                    </div>
                    <div class="tabular-nums text-gray-600">
                        {{ task.quantity_made }} / {{ task.quantity_required }}
                        <span v-if="task.quantity_rejected" class="text-red-500 ml-1">({{ task.quantity_rejected }} {{ trans('rejected') }})</span>
                    </div>
                </div>
                <div class="mt-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                    <div
                        class="h-full rounded-full"
                        :class="task.state == 'done' ? 'bg-green-500' : 'bg-indigo-500'"
                        :style="{ width: progressPercent(task) + '%' }"
                    />
                </div>
            </div>
        </div>

        <div v-if="receive_route" class="mt-6 rounded-xl border border-gray-200 bg-white p-4">
            <div class="font-semibold mb-3">{{ trans('Receive into stock') }}</div>
            <ul class="text-sm text-gray-600 mb-3">
                <li v-for="item in items" :key="item.id">
                    {{ item.artefact_code }} — {{ trans('will receive') }} {{ item.produced_quantity }} {{ trans('units') }}
                </li>
            </ul>
            <div class="relative max-w-xs">
                <label class="block text-xs text-gray-500 mb-1">{{ trans('Location') }}</label>
                <input
                    type="text"
                    v-model="locationQuery"
                    class="w-full rounded border-gray-300 text-sm"
                    :placeholder="trans('Search location')"
                    @input="selectedLocation = null; searchLocations()"
                />
                <ul v-if="locationResults.length" class="absolute z-10 mt-1 w-full rounded border border-gray-200 bg-white shadow-lg max-h-48 overflow-auto">
                    <li
                        v-for="location in locationResults"
                        :key="location.id"
                        class="px-3 py-1.5 text-sm cursor-pointer hover:bg-gray-100"
                        @click="pickLocation(location)"
                    >
                        {{ location.code }}
                    </li>
                </ul>
            </div>
            <button
                type="button"
                class="mt-3 rounded bg-green-600 text-white text-sm font-semibold px-4 py-2 disabled:opacity-40"
                :disabled="!selectedLocation || processing"
                @click="receiveIntoStock"
            >
                {{ trans('Receive into stock') }}
            </button>
        </div>

        <div v-if="add_item_route" class="mt-6 flex items-end gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ trans('Artefact') }}</label>
                <select v-model="newArtefactId" class="rounded border-gray-300 text-sm min-w-64">
                    <option :value="null" disabled>{{ trans('Select artefact') }}</option>
                    <option v-for="option in artefact_options" :key="option.id" :value="option.id">
                        {{ option.code }} — {{ option.name }}{{ option.has_recipe ? '' : ' (' + trans('no recipe') + ')' }}
                    </option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ trans('Quantity') }}</label>
                <input type="number" min="1" v-model.number="newQuantity" class="w-24 rounded border-gray-300 text-sm" />
                <div
                    v-if="selectedArtefactOption?.recommended_batch_size && newQuantity !== selectedArtefactOption.recommended_batch_size"
                    class="text-xs text-gray-400 mt-1"
                >
                    {{ trans('recommended batch') }}: {{ selectedArtefactOption.recommended_batch_size }}
                </div>
            </div>
            <button
                type="button"
                class="rounded bg-indigo-600 text-white text-sm px-3 py-2 disabled:opacity-50"
                :disabled="!newArtefactId || !newQuantity || processing"
                @click="addItem"
            >
                <FontAwesomeIcon :icon="['fal', 'plus']" fixed-width class="mr-1" />
                {{ trans('Add item') }}
            </button>
        </div>
    </div>
</template>
