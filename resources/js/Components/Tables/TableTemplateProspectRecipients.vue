<!--
  - Author: eka yudinata (https://github.com/ekayudinata)
  - Created: Tuesday, 7 Apr 2026 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2026, eka yudinata
-->

<script setup lang="ts">
import {
    faCheck,
    faEnvelopeOpen,
    faExclamationCircle,
    faExclamationTriangle,
    faInboxIn,
    faMousePointer,
    faPaperPlane,
    faSpellCheck,
    faSquare,
    faTimesCircle,
    faVirus,
    faEnvelope,
    faBan,
    faUsers,
    faSpinner
} from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { reactive, computed, watch, ref, onMounted, nextTick } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown, faFilter, faTimes, faPlus } from "@fas"
import Button from '@/Components/Elements/Buttons/Button.vue'
import { InputNumber } from 'primevue'
import Menu from 'primevue/menu'
import Badge from 'primevue/badge'
import Dropdown from 'primevue/dropdown'
import Calendar from 'primevue/calendar'
import ToggleButton from 'primevue/togglebutton'
import { routeType } from '@/types/route'
import '@vuepic/vue-datepicker/dist/main.css'
import { useProspectFilterRecipients } from "@/Composables/useProspectFilterRecipients";
import { trans } from "laravel-vue-i18n"

library.add(
    faSpellCheck,
    faPaperPlane,
    faExclamationCircle,
    faVirus,
    faInboxIn,
    faMousePointer,
    faExclamationTriangle,
    faSquare,
    faEnvelopeOpen,
    faCheck,
    faTimesCircle,
    faEnvelope,
    faBan,
    faChevronDown,
    faFilter,
    faTimes,
    faPlus,
    faSpinner
);

const props = defineProps<{
    recipientFilterRoute: routeType
    filters: Record<string, any>,
    filtersStructure: Record<string, any>
    recipientsRecipe: any,
    shopId: number,
    estimatedRecipients: number,
    shopSlug: string
}>();

const {
    activeFilters,
    activeFilterCount,
    isAllCustomers,
    readyFilters,
    addFilter,
    removeFilter,
    clearAllFilters,
    fetchCustomers,
    saveFilters,
    hydrateSavedFilters,
    updateLastContactedMode,
    calculateDateFromPreset,
} = useProspectFilterRecipients(props)

const filterMenu = ref()

const handleLastContactedModeChange = (filterKey: string, newMode: string) => {
    updateLastContactedMode(filterKey, newMode)
}

const availableFilters = computed(() => {
    const list: any[] = []

    Object.values(props.filtersStructure).forEach((group: any) => {
        Object.entries(group.filters).forEach(([key, filter]: any) => {
            if (!activeFilters.value[key]) {
                list.push({
                    label: filter.label,
                    icon: filter.icon ?? 'pi pi-filter',
                    command: () => addFilter(key, filter)
                })
            }
        })
    })

    return list
})

const formatNumber = (num: number | null | undefined) => {
    return new Intl.NumberFormat('en-GB').format(num ?? 0)
}

onMounted(async () => {
    if (props.recipientsRecipe) {
        activeFilters.value = hydrateSavedFilters(
            props.recipientsRecipe,
            props.filtersStructure
        )
        await nextTick()
        fetchCustomers()
    } else {
        // Pre-load never_contacted filter if no filters exist
        const neverContactedConfig = props.filtersStructure.prospects?.filters?.never_contacted
        if (neverContactedConfig) {
            addFilter('never_contacted', neverContactedConfig)
        }
    }
})

watch(
    () => activeFilters.value,
    (filters) => {
        Object.values(filters).forEach((filter: any) => {
            if (!filter?.config || !filter?.value) return

            const val = filter.value

            if (filter.config.type === 'entity_behaviour') {
                if (val.combine_logic === false && Array.isArray(val.behaviors) && val.behaviors.length > 1) {
                    val.behaviors = [val.behaviors[0]]
                }
            }
        })
    },
    { deep: true }
)
</script>

<template>
    <div class="px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex justify-between items-center mb-6">
            <!-- left side -->
            <div class="flex items-center gap-3">
                <Menu :model="availableFilters" popup ref="filterMenu">
                </Menu>

                <Button @click="filterMenu.toggle($event)" class="h-10 px-4" :type="'secondary'">
                    <FontAwesomeIcon :icon="faPlus" />
                    <span>{{ trans("Filter") }}</span>

                    <Badge v-if="activeFilterCount" :value="activeFilterCount" class="ml-2" />
                </Button>

                <Button :label="trans('Apply Filters')" :type="'primary'" class="h-10 px-4" @click="fetchCustomers" />

                <Button v-if="Object.keys(activeFilters).length" label="Clear filters" type="warning" class="h-10 px-4"
                    @click="clearAllFilters" />
            </div>
            <!-- center side -->
            <div class="flex items-center">
                <span v-if="isAllCustomers" class="text-blue-600 font-medium">
                    {{ trans("Audience: All Prospects") }}
                </span>
            </div>
            <!-- right side -->
            <div class="flex items-center gap-3">

                <Button :label="trans('Save')" type="positive" icon="save" @click="saveFilters" class="h-10 px-4" />
            </div>
        </div>
        <div v-if="Object.keys(activeFilters).length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
            <div v-for="(filter, key) in readyFilters" :key="key"
                class="border rounded p-4 bg-gray-50 relative min-w-0">

                <div v-if="filter.config" class="flex justify-between mb-2">
                    <span class="font-medium flex items-center">{{ filter.config.label ?? '-' }}</span>
                    <Button :icon="faTimes" type="negative" @click="removeFilter(key)" />
                </div>
                <p v-if="filter.config.description" class="text-xs text-gray-600 mb-2">
                    {{ filter.config.description }}
                </p>
                <span
                    class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 mb-2">
                    {{ trans("Active") }}
                </span>
                <!-- BOOLEAN -->
                <template v-if="filter.config.type === 'boolean'" class="mt-2">
                    <template v-if="filter.config.options?.date_range">
                        <label class="block text-xs font-medium text-gray-500 mb-1">
                            {{ filter.config.options.date_range.label }}
                        </label>

                        <!-- PRESET SELECT (if exists) -->
                        <Dropdown v-if="filter.config.options.date_range.presets"
                            :options="filter.config.options.date_range.presets" v-model="filter.value.date_range_preset"
                            class="w-full mb-2" placeholder="Select time frame" />

                        <!-- CALENDAR -->
                        <Calendar
                            v-if="filter.value.date_range_preset === 'custom' || !filter.config.options.date_range_presets"
                            v-model="filter.value.date_range" selectionMode="range" dateFormat="yy-mm-dd" showIcon
                            placeholder="Select a date range" class="w-full" appendTo="body" />
                    </template>

                    <!-- COUNT -->
                    <div v-if="filter.config.options?.count" class="mt-2">
                        <label class="block text-xs font-medium text-gray-500 mb-1">
                            {{ filter.config.options.count.label }}
                        </label>
                        <InputNumber :min="filter.config.options.count.min ?? 1" v-model="filter.value.count"
                            class="w-full" inputClass="w-full" />
                    </div>

                    <!-- WEEKS -->
                    <div v-if="filter.config.options?.weeks" class="mt-2">
                        <label class="block text-xs font-medium text-gray-500 mb-1">
                            {{ filter.config.options.weeks.label }}
                        </label>
                        <Dropdown v-model="filter.value.mode" :options="filter.config.options.weeks.presets"
                            optionLabel="label" optionValue="value" placeholder="Select time period" class="w-full mb-2"
                            appendTo="body"
                            @update:modelValue="(newMode) => handleLastContactedModeChange(key, newMode)" />

                        <!-- CUSTOM DATE (only when mode is custom) -->
                        <Calendar v-if="filter.value.mode === 'custom'" v-model="filter.value.custom_date"
                            placeholder="Select date" dateFormat="yy-mm-dd" showIcon class="w-full" appendTo="body" />

                        <!-- DISPLAY CALCULATED DATE (for presets) -->
                        <div v-else-if="filter.value.custom_date" class="text-xs text-gray-600 mt-1">
                            Date: {{ filter.value.custom_date }}
                        </div>
                    </div>
                </template>

                <!-- SELECT -->
                <Dropdown v-else-if="filter.config.type === 'select'" v-model="filter.value"
                    :options="filter.config.options" optionLabel="label" optionValue="value" placeholder="Select"
                    class="w-full" appendTo="body" />
            </div>
        </div>

        <div class="mt-8">
            <div class="bg-white shadow-sm ring-1 ring-gray-200 rounded-2xl p-8 flex items-center justify-between">

                <div>
                    <p class="text-sm text-gray-500 mb-1">{{ trans("Estimated Recipients") }}</p>
                    <h2 class="text-4xl font-semibold tracking-tight text-gray-900">
                        {{ trans(formatNumber(estimatedRecipients)) }}
                    </h2>
                    <p class="text-xs text-gray-400 mt-2">
                        {{ trans("Based on current filters") }}
                    </p>
                </div>

                <div class="h-16 w-16 rounded-full bg-indigo-50 flex items-center justify-center">
                    <FontAwesomeIcon :icon="faUsers" class="text-indigo-600 text-2xl" />
                </div>

            </div>
        </div>
    </div>
</template>
<style scoped>
:deep(.p-multiselect),
:deep(.p-calendar) {
    width: 100% !important;
    max-width: 100% !important;
}

:deep(.p-calendar-input),
:deep(.p-inputtext) {
    width: 100% !important;
}
</style>
