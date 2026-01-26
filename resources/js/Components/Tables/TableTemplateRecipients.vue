<!--
  - Author: eka yudinata (https://github.com/ekayudinata)
  - Created: Thursday, 8 Jan 2026 11:33:00 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2026, eka yudinata
  -->

<script setup lang="ts">
import {
    faCheck,
    faDumpster,
    faEnvelopeOpen,
    faExclamationCircle,
    faExclamationTriangle,
    faHandPaper,
    faInboxIn,
    faMousePointer,
    faPaperPlane,
    faSpellCheck,
    faSquare,
    faTimesCircle,
    faVirus,
    faEnvelope,
    faBan
} from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import Icon from "../Icon.vue";
import { inject, reactive, computed, watch, ref } from "vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { useFormatTime } from "@/Composables/useFormatTime";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown, faFilter, faTimes, faPlus } from "@fas"
import { debounce } from 'lodash'
import { router } from '@inertiajs/vue3'
import MultiselectTagsInfiniteScroll from '@/Components/Forms/Fields/MultiselectTagsInfiniteScroll.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { Button as ButtonPrime, Column, DataTable, Dialog, FileUpload, FloatLabel, IconField, InputIcon, InputNumber, InputText, MultiSelect, Popover, RadioButton, Rating, Select, Skeleton, Tag, Textarea, Toolbar } from 'primevue'
import Menu from 'primevue/menu'
import Badge from 'primevue/badge'
import Dropdown from 'primevue/dropdown'
import Calendar from 'primevue/calendar'
import Checkbox from 'primevue/checkbox'
import ToggleButton from 'primevue/togglebutton'

import '@vuepic/vue-datepicker/dist/main.css'

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
    faMousePointer,
    faDumpster,
    faHandPaper,
    faCheck,
    faTimesCircle,
    faEnvelope,
    faBan,
    faChevronDown,
    faFilter,
    faTimes,
    faPlus
);

const props = defineProps<{
    // data: object,
    // tab?: string,
    customers: any,
    filters: Record<string, any>,
    filtersStructure: Record<string, any>
}>();

const locale = inject("locale", aikuLocaleStructure);

const activeFilters = ref<Record<string, any>>(
    props.filters ? { ...props.filters } : {}
)

const activeFilterCount = computed(() =>
    Object.keys(activeFilters.value ?? {}).length
)

const tableState = reactive({
    page: props.customers.current_page ?? 1,
    rows: props.customers.per_page ?? 10,
    sortField: null,
    sortOrder: null
})

const filterMenu = ref()
const availableFilters = computed(() => {
    const list: any[] = []

    Object.values(filtersStructureMerged.value).forEach((group: any) => {
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
function normalizeCurrency(currency: string) {
    const map: Record<string, string> = {
        '£': 'GBP',
        '$': 'USD',
        '€': 'EUR'
    }

    return map[currency] ?? currency
}

const addFilter = (key: string, config: any) => {
    let value: any = true
    if (!key) {
        console.warn('[addFilter] invalid key', key, config)
        return
    }
    if (config.type === 'boolean') {
        value = { value: true }

        if (key === 'orders_in_basket') {
            value._ui_preset = null      // UI only
            value.date_range = null
        }

        if (config.options?.amount_range) {
            value.amount_range = { min: null, max: null }
        }
    }

    if (config.type === 'select') {
        value = config.options?.[0]?.value ?? null
    }

    else if (config.type === 'families') {
        value = {
            family_ids: [],
            behaviors: {
                purchased: false,
                favourited: false,
                basket_only: false
            },
            combine_logic: 'or'
        }
    }


    if (config.type === 'multiselect') {
        value = config.behavior_options
            ? { ids: [], behaviors: ['purchased'], combine_logic: 'or' }
            : []
    }

    if (config.type === 'daterange') {
        value = { date_range: null }
    }

    if (config.type === 'location') {
        value = {
            location: '',
            radius: '5km'
        }
    }

    activeFilters.value[key] = { value, config }

    console.log('[addFilter]', key, activeFilters.value)
}

const onPresetChange = (filter: any, event: any) => {
    const preset = event.value

    filter.value._ui_preset = preset

    if (preset === 'custom') {
        filter.value.date_range = null
        return
    }

    const days = Number(preset)
    if (!isNaN(days)) {
        const end = new Date()
        const start = new Date()
        start.setDate(end.getDate() - days)
        filter.value.date_range = [start, end]
    }
}


const removeFilter = (key: string) => {
    delete activeFilters.value[key]
}

const clearAllFilters = () => {
    activeFilters.value = {}
}

const fetchCustomers = debounce(() => {
    const filtersPayload: any = {}

    Object.entries(activeFilters.value).forEach(([key, filter]: any) => {
        filtersPayload[key] = {
            value: filter.value
        }
    })

    console.log('[filtersPayload]', filtersPayload)

    router.get(
        route(route().current(), route().params),
        {
            filters: filtersPayload,
            // page: tableState.page,
            // per_page: tableState.rows,
            // sort: tableState.sortField,
            // direction: tableState.sortOrder === 1 ? 'asc' : 'desc'
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['customers', 'filters']
        }
    )
}, 400)

const filtersPayload = computed(() => {
    const payload: any = {}

    Object.entries(activeFilters.value).forEach(([key, filter]: any) => {
        payload[key] = filter.value
    })

    return payload
})

const saveFilters = () => {
    console.log('[SAVE FILTER PAYLOAD]', filtersPayload.value)
    return;
    router.post(
        route('mailshots.filters.save'), // ganti sesuai route
        {
            filters: filtersPayload.value
        },
        {
            preserveState: true,
            preserveScroll: true
        }
    )
}

const filtersStructureMerged = computed(() => {
    return {
        ...props.filtersStructure,
        marketing: {
            ...props.filtersStructure.marketing,
            filters: {
                ...props.filtersStructure.marketing.filters,

                by_families: {
                    label: 'By Product Families',
                    type: 'families',
                    options: {
                        families: [
                            { label: 'Electronics', value: 1 },
                            { label: 'Furniture', value: 2 },
                            { label: 'Clothing', value: 3 },
                            { label: 'Accessories', value: 4 }
                        ],
                        behaviors: [
                            { label: 'Purchased from selected families', value: 'purchased' },
                            { label: 'Added to favourites', value: 'favourited' },
                            { label: 'Added to basket but not purchased', value: 'basket_only' }
                        ]
                    }
                },
                by_departments: {
                    label: 'By Departments',
                    type: 'multiselect',
                    behavior_options: [
                        {
                            label: 'Purchased products from these departments',
                            value: 'purchased'
                        },
                        {
                            label: 'Added to basket but not purchased',
                            value: 'basket_only'
                        }
                    ],
                    combine_logic: {
                        enabled: true,
                        options: [
                            { label: 'Match any selected behaviour (OR)', value: 'or' },
                            { label: 'Match all selected behaviours (AND)', value: 'and' }
                        ]
                    },
                    options: [
                        // dummy sub-departments
                        { label: 'Mobile Phones', value: 101 },
                        { label: 'Laptops', value: 102 },
                        { label: 'Home Appliances', value: 201 },
                        { label: 'Kitchen Appliances', value: 202 },
                        { label: 'Men Clothing', value: 301 },
                        { label: 'Women Clothing', value: 302 }
                    ]
                }

            }
        }
    }
})
watch(activeFilters, fetchCustomers, { deep: true })
watch(tableState, fetchCustomers, { deep: true })
console.log("props table", props)
</script>

<template>
    <div class="px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center gap-3 mb-6">
            <Menu :model="availableFilters" popup ref="filterMenu">
            </Menu>

            <Button @click="filterMenu.toggle($event)" class="h-10 px-4">
                <FontAwesomeIcon :icon="faPlus" />
                <span>Filter</span>

                <Badge v-if="activeFilterCount" :value="activeFilterCount" class="ml-2" />
            </Button>

            <Button v-if="Object.keys(activeFilters).length" label="Clear filters" type="tertiary" class="h-10 px-4"
                @click="clearAllFilters" />
        </div>
        <div v-if="Object.keys(activeFilters).length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
            <div v-for="(filter, key) in activeFilters" :key="key"
                class="border rounded p-4 bg-gray-50 relative min-w-0 overflow-hidden">

                <div class=" flex justify-between mb-2">
                    <span class="font-medium">{{ filter.config.label }}</span>
                    <Button :icon="faTimes" type="negative" @click="removeFilter(key)" />
                </div>

                <!-- BOOLEAN -->
                <template v-if="filter.config.type === 'boolean'" class="mt-2">
                    <ToggleButton v-model="filter.value.value" onLabel="Active" offLabel="Inactive" class="mb-3" />
                    <template v-if="key === 'orders_in_basket'">
                        <!-- TIME FRAME -->
                        <Dropdown v-model="filter.value._ui_preset" :options="[
                            { label: '1–3 Days ago', value: 3 },
                            { label: 'Last 7 Days', value: 7 },
                            { label: 'Last 14 Days', value: 14 },
                            { label: 'Custom range', value: 'custom' }
                        ]" optionLabel="label" optionValue="value" placeholder="Select time frame" class="w-full mb-2"
                            appendTo="body" />


                        <!-- DATE RANGE (only custom) -->
                        <Calendar v-if="filter.value._ui_preset === 'custom'" v-model="filter.value.date_range"
                            placeholder="Select a date range" selectionMode="range" dateFormat="yy-mm-dd" showIcon
                            class="w-full" appendTo="body" />

                    </template>
                    <template v-else-if="filter.config.options?.date_range">
                        <label class="block text-xs font-medium text-gray-500 mb-1">
                            {{ filter.config.options.date_range.label }}
                        </label>

                        <!-- PRESET SELECT (if exists) -->
                        <Dropdown v-if="filter.config.options.date_range.presets"
                            :options="filter.config.options.date_range.presets" v-model="filter.value._ui_preset"
                            class="w-full mb-2" placeholder="Select time frame"
                            @change="onPresetChange(filter, $event)" />

                        <!-- CALENDAR -->
                        <Calendar
                            v-if="filter.value._ui_preset === 'custom' || !filter.config.options.date_range.presets"
                            v-model="filter.value.date_range" selectionMode="range" dateFormat="yy-mm-dd" showIcon
                            placeholder="Select a date range" class="w-full" appendTo="body" />
                    </template>

                    <!-- AMOUNT RANGE -->
                    <div v-if="filter.config.options?.amount_range" class="grid grid-cols-2 gap-2 mt-2">
                        <InputNumber v-model="filter.value.amount_range.min" placeholder="Minimum amount" class="w-full"
                            inputClass="w-full" />
                        <InputNumber v-model="filter.value.amount_range.max" placeholder="Maximum amount" class="w-full"
                            inputClass="w-full" />
                    </div>
                </template>

                <!-- SELECT -->
                <Dropdown v-else-if="filter.config.type === 'select'" v-model="filter.value"
                    :options="filter.config.options" optionLabel="label" optionValue="value" placeholder="Select"
                    class="w-full" appendTo="body" />

                <!-- MULTISELECT -->
                <template v-else-if="filter.config.type === 'multiselect'">
                    <div class="min-w-0 w-full">
                        <MultiselectTagsInfiniteScroll :form="filter.value" fieldName="ids" :fieldData="{
                            options: filter.config.options,
                            labelProp: 'label',
                            valueProp: 'value',
                            placeholder: 'Select items...'
                        }" />
                    </div>


                    <div v-if="filter.config.behavior_options" class="mt-3">
                        <div v-for="behavior in filter.config.behavior_options" :key="behavior.value"
                            class="flex items-center gap-2">
                            <Checkbox v-model="filter.value.behaviors" :value="behavior.value" />
                            <label>{{ behavior.label }}</label>
                        </div>
                    </div>

                    <div v-if="filter.config.combine_logic?.enabled && filter.value.behaviors.length > 1">
                        <label class="block text-xs font-medium text-gray-500 mb-2">
                            Combination logic
                        </label>

                        <div class="flex gap-4">
                            <div class="flex items-center gap-2">
                                <RadioButton v-model="filter.value.combine_logic" inputId="or" value="or" />
                                <label for="or" class="text-sm">Match any (OR)</label>
                            </div>

                            <div class="flex items-center gap-2">
                                <RadioButton v-model="filter.value.combine_logic" inputId="and" value="and" />
                                <label for="and" class="text-sm">Match all (AND)</label>
                            </div>
                        </div>
                    </div>
                </template>
                <!-- LOCATION -->
                <template v-else-if="filter.config.type === 'location'">
                    <div class="space-y-3">
                        <!-- Location input -->
                        <InputText v-model="filter.value.location"
                            :placeholder="filter.config.fields.location.placeholder" class="w-full" />

                        <!-- Radius -->
                        <Dropdown v-model="filter.value.radius" :options="Object.entries(filter.config.fields.radius.options).map(
                            ([value, label]) => ({ value, label })
                        )" optionLabel="label" optionValue="value" placeholder="Select radius" class="w-full"
                            appendTo="body" />
                    </div>
                </template>

                <!-- FAMILIES FILTER -->
                <template v-else-if="filter.config.type === 'families'">

                    <!-- FAMILY MULTISELECT -->
                    <div class="mb-3">
                        <label class="block text-xs font-medium text-gray-500 mb-1">
                            Product families
                        </label>

                        <MultiselectTagsInfiniteScroll :form="filter.value.family_ids" fieldName="ids" :fieldData="{
                            options: filter.config.options.families,
                            labelProp: 'label',
                            valueProp: 'value',
                            placeholder: 'Select items...'
                        }" />
                    </div>

                    <!-- BEHAVIOR CHECKBOXES -->
                    <div v-for="behavior in filter.config.options.behaviors" :key="behavior.value"
                        class="flex items-center gap-2">
                        <Checkbox v-model="filter.value.behaviors[behavior.value]" :binary="true" />
                        <span>{{ behavior.label }}</span>
                    </div>

                    <!-- COMBINATION LOGIC -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-2">
                            Combination logic
                        </label>

                        <div class="flex gap-4">
                            <div class="flex items-center gap-2">
                                <RadioButton v-model="filter.value.combine_logic" inputId="or" value="or" />
                                <label for="or" class="text-sm">Match any (OR)</label>
                            </div>

                            <div class="flex items-center gap-2">
                                <RadioButton v-model="filter.value.combine_logic" inputId="and" value="and" />
                                <label for="and" class="text-sm">Match all (AND)</label>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <Button label="Save" type="save" @click="saveFilters" v-if="Object.keys(activeFilters).length" class="mb-4" />

        <DataTable :value="customers.data" lazy paginator :rows="customers.per_page" :totalRecords="customers.total"
            :first="(customers.current_page - 1) * customers.per_page" @page="e => {
                tableState.page = e.page + 1
                tableState.rows = e.rows
            }" @sort="e => {
                tableState.sortField = e.sortField
                tableState.sortOrder = e.sortOrder
            }">
            <Column field="contact_name" header="Name" sortable />
            <Column field="email" header="Email" sortable />
            <Column field="created_at" header="Joined">
                <template #body="{ data }">
                    {{ new Date(data.created_at).toLocaleDateString() }}
                </template>
            </Column>
        </DataTable>
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
