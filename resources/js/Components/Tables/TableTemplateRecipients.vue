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
    faBan, faUsers
} from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { inject, reactive, computed, watch, ref, onMounted } from "vue";
import { useFormatTime } from "@/Composables/useFormatTime";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown, faFilter, faTimes, faPlus } from "@fas"
import { debounce } from 'lodash'
import { router } from '@inertiajs/vue3'
import MultiselectTagsInfiniteScroll from '@/Components/Forms/Fields/MultiselectTagsInfiniteScroll.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { Button as ButtonPrime, InputNumber, InputText, RadioButton } from 'primevue'
import Menu from 'primevue/menu'
import Badge from 'primevue/badge'
import Dropdown from 'primevue/dropdown'
import Calendar from 'primevue/calendar'
import Checkbox from 'primevue/checkbox'
import ToggleButton from 'primevue/togglebutton'
import { routeType } from '@/types/route'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import { trans } from "laravel-vue-i18n"
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import '@vuepic/vue-datepicker/dist/main.css'
import { LMap, LTileLayer, LMarker, LTooltip, LCircle } from "@vue-leaflet/vue-leaflet"


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
    customers: any,
    recipientFilterRoute: routeType
    filters: Record<string, any>,
    filtersStructure: Record<string, any>
    recipientsRecipe: any,
    shopId: number,
    estimatedRecipients: number,
    shopSlug: string
}>();

const filterMenu = ref()
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

// validasi filter
const FILTER_CONFLICTS: Record<string, string[]> = {
    registered_never_ordered: [
        'orders_in_basket',
        'by_order_value',
        'orders_collection',
        'by_family',
        'by_subdepartment',
        'by_family_never_ordered',
        'by_showroom_orders',
        'by_department'
    ],
    orders_in_basket: ['registered_never_ordered'],
    by_order_value: ['registered_never_ordered'],
    orders_collection: ['registered_never_ordered'],
    by_family: ['registered_never_ordered'],
    by_subdepartment: ['registered_never_ordered'],
    by_family_never_ordered: ['registered_never_ordered'],
    by_showroom_orders: ['registered_never_ordered'],
    by_department: ['registered_never_ordered']
}
function hasConflict(newKey: string) {
    const activeKeys = Object.keys(activeFilters.value)

    const conflicts = FILTER_CONFLICTS[newKey] || []

    const found = activeKeys.find(k => conflicts.includes(k))

    return found || null
}

const addFilter = (key: string, config: any) => {
    const conflictWith = hasConflict(key)
    if (conflictWith) {
        notify({
            title: "Filter conflict",
            text: `"${config.label}" cannot be combined with "${activeFilters.value[conflictWith].config.label}"`,
            type: "error"
        })
        return
    }

    let value: any = true
    if (!key) {
        console.warn('[addFilter] invalid key', key, config)
        return
    }

    if (config.type === 'boolean') {
        value = {
            date_range: null,
            amount_range: { min: null, max: null },
            date_range_preset: null
        }
    }

    if (config.type === 'select') {
        value = config.options?.[0]?.value ?? null
    }

    if (config.type === 'multiselect') {
        value = config.behavior_options
            ? { ids: [], behaviors: ['purchased'], combine_logic: 'or' }
            : { ids: [] }
    }

    if (config.type === 'daterange') {
        value = { date_range: null }
    }

    if (config.type === 'entity_behaviour') {
        value = {
            ids: [],
            behaviors: [],
            combine_logic: true
        }
    }

    if (config.type === 'location') {
        value = {
            mode: 'direct',
            country_ids: [],
            postal_codes: [],
            location: '',
            radius: null,
            radius_custom: null,
            lat: null,
            lng: null,
            resolved: false
        }
    }

    activeFilters.value[key] = { value, config }

    console.log('addFilter', key, activeFilters.value)
}

const onPresetChange = (filter: any, event: any) => {
    const preset = event.value

    filter.value.date_range_preset = preset

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

const onMapClick = (e, filter) => {
    filter.value.lat = Number(e.latlng.lat)
    filter.value.lng = Number(e.latlng.lng)
}

const onMarkerDrag = (e, filter) => {
    const pos = e.target.getLatLng()
    filter.value.lat = Number(pos.lat)
    filter.value.lng = Number(pos.lng)
}

const getLocationToLatLng = async (filter: any) => {
    const v = filter.value
    let query = ''

    if (v.mode === 'radius') {
        if (!v.location) return
        query = v.location
    } else {
        if (!v.postal_codes?.length) return

        const postcode = v.postal_codes[0]

        const countryLabel = v.country_ids?.[0] || ''

        query = `${postcode} ${countryLabel}`
    }

    if (!query) return

    v.loadingMap = true

    try {
        const res = await axios.get(route('grp.json.get_geocode'), {
            params: { location: query }
        })

        const data = res.data

        const lat = Number(data.latitude)
        const lng = Number(data.longitude)

        if (!lat || !lng) throw new Error('Invalid coordinate')

        v.lat = lat
        v.lng = lng
        v.zoom = 12
        v.resolved = true
    } catch (err) {
        console.error('GEOCODE FAILED', err)
        v.resolved = false
    } finally {
        v.loadingMap = false
    }
}
function findConfigByKey(structure: any, key: string) {
    for (const group of Object.values(structure)) {
        if (group.filters?.[key]) return group.filters[key]
    }
    return null
}
function normalizeDate(d: string | null) {
    if (!d) return null
    return d.split('T')[0]
}

const presetMeters = ['5000', '10000', '25000', '50000', '100000']
function unwrapBoolean(val: any) {
    let v = val
    while (v && typeof v === 'object' && 'value' in v && typeof v.value === 'object') {
        v = v.value
    }
    return v
}
function hydrateSavedFilters(saved: any, structure: any) {
    const hydrated: any = {}

    Object.entries(saved || {}).forEach(([key, wrapper]: any) => {
        const config = findConfigByKey(structure, key)
        if (!config) return
        let val

        if (key === 'orders_in_basket') {
            val = wrapper
        } else {
            val = typeof wrapper === 'object' && 'value' in wrapper
                ? wrapper
                : { value: wrapper }
        }

        const raw = val?.value ?? val
        let uiValue: any = {}

        if (config.type === 'boolean') {
            const clean = unwrapBoolean(val)

            if (key === 'orders_in_basket') {
                const isCustom = Array.isArray(clean.date_range)

                uiValue = {
                    value: true,
                    mode: isCustom ? 'custom' : clean.date_range,
                    date_range: isCustom
                        ? [normalizeDate(clean.date_range[0]), normalizeDate(clean.date_range[1])]
                        : null,
                    amount_range: clean.amount_range ?? { min: null, max: null }
                }

            } else {
                uiValue = {
                    value: clean.value ?? true,
                    date_range: Array.isArray(clean.date_range)
                        ? [normalizeDate(clean.date_range[0]), normalizeDate(clean.date_range[1])]
                        : null,
                    amount_range: clean.amount_range ?? { min: null, max: null },
                    date_range_preset: null
                }
            }
        }

        else if (config.type === 'select') {
            uiValue = typeof val === 'object' && 'value' in val
                ? val.value
                : val
        }

        else if (config.type === 'multiselect') {
            if (config.behavior_options) {
                uiValue = {
                    ids: val?.ids ?? [],
                    behaviors: val?.behaviors ?? ['purchased'],
                    combine_logic: val?.combine_logic ?? 'or'
                }
            }

            else {
                const src = wrapper?.value ?? wrapper
                uiValue = {
                    ids: Array.isArray(src)
                        ? src
                        : Array.isArray(src?.ids)
                            ? src.ids
                            : []
                }
            }
        }

        else if (config.type === 'entity_behaviour') {
            uiValue = {
                ids: raw.ids ?? [],
                behaviors: Array.isArray(raw.behaviors)
                    ? raw.behaviors
                    : raw.behaviors
                        ? [raw.behaviors]
                        : [],
                combine_logic: typeof raw.combine_logic === 'boolean'
                    ? raw.combine_logic
                    : true
            }
        }

        else if (config.type === 'location') {
            const v = val?.value ?? {}

            let radiusPreset = '5000'
            let radiusCustom = null

            if (v.radius) {
                const asString = String(v.radius)

                if (presetMeters.includes(asString)) {
                    radiusPreset = asString
                } else {
                    radiusPreset = 'custom'
                    radiusCustom = Number(v.radius)
                }
            }

            uiValue = {
                mode: v.mode ?? 'direct',
                country_ids: v.country_ids ?? [],
                postal_codes: v.postal_codes ?? [],
                location: v.location ?? '',
                radius: radiusPreset,
                radius_custom: radiusCustom,
                lat: v.lat ?? 0,
                lng: v.lng ?? 0,
                zoom: 10
            }
        }
        hydrated[key] = { config, value: uiValue }
    })

    return hydrated
}

const fetchCustomers = debounce(() => {
    const filtersPayload: any = {}
    console.log("activeFilters di fetchCustomers", activeFilters.value)
    Object.entries(activeFilters.value).forEach(([key, filter]: any) => {
        filtersPayload[key] = {
            value: filter.value
        }
    })

    console.log('filtersPayload di fetchCustomers', filtersPayload)

    router.get(
        route(route().current(), route().params),
        {
            filters: filtersPayload,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['customers', 'filters', 'estimatedRecipients']
        }
    )
}, 400)

const preloadedEntities = reactive<Record<string, any[]>>({})
const preloadEntityOptions = async (key: string, ids: number[]) => {
    if (!ids?.length) return
    const routeMap = {
        by_family: {
            name: 'grp.json.shop.families',
            param: props.shopId
        },

        by_family_never_ordered: {
            name: 'grp.json.shop.families',
            param: props.shopId
        },
        by_subdepartment: {
            name: 'grp.json.shop.sub_departments',
            param: props.shopId
        },
        by_departments: {
            name: 'grp.json.shop.departments',
            param: props.shopSlug
        }
    }

    const cfg = routeMap[key]
    if (!cfg) return

    const res = await axios.get(route(cfg.name, {
        shop: cfg.param,
        ids
    }))

    preloadedEntities[key] = res.data.data
}
function getEntityFetchRoute(key: string) {
    if (key === 'by_family' || key === 'by_family_never_ordered') {
        return {
            name: 'grp.json.shop.families',
            parameters: { shop: props.shopId }
        }
    }

    if (key === 'by_subdepartment') {
        return {
            name: 'grp.json.shop.sub_departments',
            parameters: { shop: props.shopId }
        }
    }

    if (key === 'by_departments') {
        return {
            name: 'grp.json.shop.departments',
            parameters: { shop: props.shopSlug }
        }
    }

    return null
}
function onBasketModeChange(filter: { value: { mode: any; date_range: null; }; }, event: { value: any; }) {
    const val = event.value

    filter.value.mode = val

    if (val === 'custom') {
        filter.value.date_range = null
    } else {
        filter.value.date_range = val
    }
}

const isAllCustomers = computed(() => {
    return Object.keys(activeFilters.value).length === 0
})

const filtersPayload = computed(() => {
    const payload: any = {}

    Object.entries(activeFilters.value).forEach(([key, filter]: any) => {
        const val = filter.value
        const config = filter.config

        // BOOLEAN
        if (config.type === 'boolean') {
            if (key === 'orders_in_basket') {
                payload[key] = {
                    value: {
                        date_range: val.mode === 'custom'
                            ? val.date_range
                            : val.mode,
                        amount_range: val.amount_range
                    }
                }
                return
            }

            payload[key] = {
                value: {
                    value: val.value ?? true,
                    date_range: val.date_range ?? null,
                    amount_range: val.amount_range ?? null
                }
            }
            return
        }

        // ENTITY BEHAVIOUR
        if (config.type === 'entity_behaviour') {
            payload[key] = {
                value: {
                    ids: val.ids ?? [],
                    behaviors: val.combine_logic
                        ? (val.behaviors ?? [])
                        : (val.behaviors?.length ? [val.behaviors[0]] : []),
                    combine_logic: val.combine_logic ?? true
                }
            }
            return
        }

        // MULTISELECT (simple)
        if (config.type === 'multiselect') {
            const ids = Array.isArray(val.ids)
                ? val.ids
                : val.ids != null
                    ? [val.ids]
                    : []

            payload[key] = {
                value: ids
            }
            return
        }

        // SELECT
        if (config.type === 'select') {
            payload[key] = {
                value: val
            }
            return
        }

        // LOCATION
        if (config.type === 'location') {
            payload[key] = {
                value: {
                    mode: val.mode,
                    country_ids: val.country_ids,
                    postal_codes: val.postal_codes,
                    location: val.location,
                    radius: val.radius === 'custom'
                        ? Number(val.radius_custom)
                        : Number(val.radius),
                    lat: val.lat ?? 0,
                    lng: val.lng ?? 0,
                }
            }
            return
        }
        payload[key] = { value: val }
    })

    return payload
})

const shouldShowMap = (val: any) => {
    if (val.mode === 'radius') return true
    if (val.mode === 'direct' && (val.country_ids?.length || val.postal_codes?.length)) return true
    return false
}

const radiusInMeters = (val: any) => {
    if (val.radius === 'custom') return Number(val.radius_custom) || 0
    console.log("radius", val.radius)
    return Number(val.radius)
}

const getPostalCodeModel = (filter: any) => {
    return computed({
        get: () => filter.value.postal_codes?.join(', ') || '',
        set: (val: string) => {
            filter.value.postal_codes = val
                .split(',')
                .map(v => v.trim())
                .filter(Boolean)
        }
    })
}

const saveFilters = async () => {
    console.log('[SAVE FILTER PAYLOAD]', filtersPayload.value)
    let payload = filtersPayload.value

    if (!payload || Object.keys(payload).length === 0) {
        payload = {
            all_customers: {
                value: true
            }
        }
    }

    axios
        .patch(
            route(props.recipientFilterRoute.name, props.recipientFilterRoute.parameters),
            {
                recipients_recipe: payload
            },
        )
        .then((response) => {

            notify({
                title: trans('Success!'),
                text: trans('Success to save filter'),
                type: 'success',
            })
        })
        .catch((error) => {
            notify({
                title: "Failed to save filter",
                type: "error",
            })
        })
        .finally(() => {
            console.log('finally')
        });
}

onMounted(async () => {
    if (props.recipientsRecipe) {
        console.log('EDIT MODE', props.recipientsRecipe)

        activeFilters.value = hydrateSavedFilters(
            props.recipientsRecipe,
            props.filtersStructure
        )

        for (const [key, filter] of Object.entries(activeFilters.value)) {
            if (filter.config.type === 'entity_behaviour') {
                await preloadEntityOptions(key, filter.value.ids)
            }
        }

        console.log("activeFilters.value onmounted", activeFilters.value)
    }
})

watch(
    () => activeFilters.value,
    (filters) => {
        Object.values(filters).forEach((filter: any) => {
            if (filter.config.type === 'entity_behaviour') {
                watch(
                    () => filter.value.combine_logic,
                    (isMulti) => {
                        if (!isMulti) {
                            filter.value.behaviors = filter.value.behaviors?.length
                                ? [filter.value.behaviors[0]]
                                : []
                        }
                    },
                    { immediate: true }
                )
            }
        })
    },
    { deep: true, immediate: true }
)
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

            <span v-if="isAllCustomers" class="text-blue-600 font-medium ml-auto">
                Audience: All Customers
            </span>
            <Button label="Save" type="save" @click="saveFilters" class="h-10 px-4 ml-auto" />

        </div>
        <div v-if="Object.keys(activeFilters).length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
            <div v-for="(filter, key) in activeFilters" :key="key"
                class="border rounded p-4 bg-gray-50 relative min-w-0">

                <div class=" flex justify-between mb-2">
                    <span class="font-medium">{{ filter.config.label }}</span>
                    <Button :icon="faTimes" type="negative" @click="removeFilter(key)" />
                </div>
                <span
                    class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 mb-2">
                    Active
                </span>
                <!-- BOOLEAN -->
                <template v-if="filter.config.type === 'boolean'" class="mt-2">
                    <template v-if="key === 'orders_collection' || key === 'by_showroom_orders'">
                        <ToggleButton v-model="filter.value" onLabel="Active" offLabel="Inactive" class="mb-3 w-full" />
                    </template>
                    <template v-if="key === 'orders_in_basket'">
                        <!-- TIME FRAME -->
                        <Dropdown v-model="filter.value.mode" :options="[
                            { label: '1–3 Days ago', value: 3 },
                            { label: 'Last 7 Days', value: 7 },
                            { label: 'Last 14 Days', value: 14 },
                            { label: 'Custom range', value: 'custom' }
                        ]" optionLabel="label" optionValue="value" placeholder="Select time frame" class="w-full mb-2"
                            appendTo="body" @change="onBasketModeChange(filter, $event)" />


                        <!-- DATE RANGE (only custom) -->
                        <Calendar v-if="filter.value.mode === 'custom'" v-model="filter.value.date_range"
                            placeholder="Select a date range" selectionMode="range" dateFormat="yy-mm-dd" showIcon
                            class="w-full" appendTo="body" />

                    </template>
                    <template v-else-if="filter.config.options?.date_range">
                        <label class="block text-xs font-medium text-gray-500 mb-1">
                            {{ filter.config.options.date_range.label }}
                        </label>

                        <!-- PRESET SELECT (if exists) -->
                        <Dropdown v-if="filter.config.options.date_range.presets"
                            :options="filter.config.options.date_range.presets" v-model="filter.value.date_range_preset"
                            class="w-full mb-2" placeholder="Select time frame"
                            @change="onPresetChange(filter, $event)" />

                        <!-- CALENDAR -->
                        <Calendar
                            v-if="filter.value.date_range_preset === 'custom' || !filter.config.options.date_range_presets"
                            v-model="filter.value.date_range" selectionMode="range" dateFormat="yy-mm-dd" showIcon
                            placeholder="Select a date range" class="w-full" appendTo="body" />
                    </template>

                    <!-- AMOUNT RANGE -->
                    <div v-if="filter.config.options?.amount_range" class="grid grid-cols-2 gap-2 mt-2">
                        <InputNumber :min="0" v-model="filter.value.amount_range.min" placeholder="Minimum amount"
                            class="w-full" inputClass="w-full" :max="filter.value.amount_range.max ?? undefined" />
                        <InputNumber v-model="filter.value.amount_range.max" placeholder="Maximum amount" class="w-full"
                            inputClass="w-full" :min="filter.value.amount_range.min ?? undefined" />
                    </div>
                </template>

                <!-- SELECT -->
                <Dropdown v-else-if="filter.config.type === 'select'" v-model="filter.value"
                    :options="filter.config.options" optionLabel="label" optionValue="value" placeholder="Select"
                    class="w-full" appendTo="body" />

                <!-- MULTISELECT -->
                <template v-else-if="filter.config.type === 'multiselect'">
                    <template v-if="filter.config.label === 'By Family Never Ordered'">
                        <div class="min-w-0 w-full mb-3">
                            <PureMultiselectInfiniteScroll :object="false" :key="key" mode="single"
                                v-model="filter.value.ids" :initOptions="preloadedEntities[key] || []"
                                :fetchRoute="getEntityFetchRoute(key)" valueProp="id" labelProp="name"
                                placeholder="Select items..." />
                        </div>
                    </template>

                    <div class="min-w-0 w-full" v-else>
                        <MultiselectTagsInfiniteScroll :form="filter.value" fieldName="ids" :fieldData="{
                            options: filter.config.options || filter.config.fields.content.options,
                            labelProp: 'label',
                            valueProp: 'value',
                            placeholder: 'Select items...'
                        }" />
                    </div>
                </template>

                <template v-else-if="filter.config.type === 'entity_behaviour'">
                    <div class="min-w-0 w-full mb-3">
                        <PureMultiselectInfiniteScroll :key="key" mode="multiple" v-model="filter.value.ids"
                            :initOptions="preloadedEntities[key] || []" :fetchRoute="getEntityFetchRoute(key)"
                            valueProp="id" labelProp="name" placeholder="Select items..." />
                    </div>
                    <div class="mb-3">
                        <div class="flex items-center gap-6">
                            <div class="flex items-center gap-2">
                                <RadioButton v-model="filter.value.combine_logic" :value="true" inputId="multi" />
                                <label for="multi" class="text-sm">Allow multiple behaviours</label>
                            </div>

                            <div class="flex items-center gap-2">
                                <RadioButton v-model="filter.value.combine_logic" :value="false" inputId="single" />
                                <label for="single" class="text-sm">Single behaviour only</label>
                            </div>
                        </div>
                    </div>
                    <div v-if="filter.config.fields.behaviours" class="mt-3">
                        <!-- MULTI MODE -->
                        <div v-if="filter.value.combine_logic === true">
                            <div v-for="behavior in filter.config.fields.behaviours.options" :key="behavior.value"
                                class="flex items-center gap-2">
                                <Checkbox v-model="filter.value.behaviors" :value="behavior.value" />
                                <label>{{ behavior.label }}</label>
                            </div>
                        </div>
                        <!-- SINGLE MODE -->
                        <div v-else>
                            <div v-for="behavior in filter.config.fields.behaviours.options" :key="behavior.value"
                                class="flex items-center gap-2">
                                <RadioButton v-model="filter.value.behaviors[0]" :value="behavior.value" />
                                <label>{{ behavior.label }}</label>
                            </div>
                        </div>
                    </div>
                </template>
                <!-- LOCATION -->
                <template v-else-if="filter.config.type === 'location'">
                    <div class="space-y-3">

                        <!-- MODE -->
                        <Dropdown v-model="filter.value.mode"
                            :options="Object.entries(filter.config.fields.mode.options).map(([value, label]) => ({ value, label }))"
                            optionLabel="label" optionValue="value" class="w-full" />

                        <!-- DIRECT MODE -->
                        <template v-if="filter.value.mode === 'direct'">
                            <MultiselectTagsInfiniteScroll :form="filter.value" v-model="filter.value.country_ids"
                                fieldName="country_ids" :fieldData="{
                                    options: filter.config.fields.country_ids.options,
                                    labelProp: 'label',
                                    valueProp: 'value',
                                    placeholder: filter.config.fields.country_ids.placeholder
                                }" class="w-full !p-0" />
                            <InputText v-model="getPostalCodeModel(filter).value"
                                :placeholder="filter.config.fields.postal_codes.placeholder" class="w-full" />
                            <small class="text-gray-500">
                                You can enter multiple postal codes separated by commas.
                            </small>
                        </template>

                        <!-- RADIUS MODE -->
                        <template v-else>

                            <InputText v-model="filter.value.location"
                                :placeholder="filter.config.fields.location.placeholder" class="w-full" />

                            <Dropdown v-model="filter.value.radius"
                                :options="Object.entries(filter.config.fields.radius.options).map(([value, label]) => ({ value, label }))"
                                optionLabel="label" optionValue="value" class="w-full" placeholder="Radius in km" />

                            <InputNumber v-if="filter.value.radius === 'custom'" v-model="filter.value.radius_custom"
                                placeholder="Radius in km" class="w-full" />

                            <Button label="Find On Map" :type="'save'" @click="() => getLocationToLatLng(filter)" />
                            <!-- MAP PLACEHOLDER -->
                            <div v-if="shouldShowMap(filter.value)" class="h-72 w-full rounded">
                                <div v-if="filter.value.loadingMap"
                                    class="absolute inset-0 bg-white/70 backdrop-blur-sm z-10 flex items-center justify-center rounded">
                                    <div class="flex flex-col items-center gap-2 text-gray-600">
                                        <i class="pi pi-spin pi-spinner text-2xl"></i>
                                        <span class="text-sm">Finding location...</span>
                                    </div>
                                </div>

                                <template v-else>
                                    <l-map
                                        v-if="filter.value.lat !== null && filter.value.lng !== null && filter.value.zoom"
                                        v-model:zoom="filter.value.zoom" :center="[filter.value.lat, filter.value.lng]"
                                        class="h-full w-full" @click="(e: any) => onMapClick(e, filter)">
                                        <l-tile-layer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
                                        <l-marker :lat-lng="[Number(filter.value.lat), Number(filter.value.lng)]"
                                            :draggable="true" @dragend="(e: any) => onMarkerDrag(e, filter)">
                                            <l-tooltip :permanent="true" direction="top" :offset="[0, -10]">
                                                📍 This is your point<br>
                                                Lat: {{ Number(filter.value.lat).toFixed(5) }}<br>
                                                Lng: {{ Number(filter.value.lng).toFixed(5) }}
                                            </l-tooltip>
                                        </l-marker>
                                        <l-circle v-if="filter.value.mode === 'radius'"
                                            :lat-lng="[filter.value.lat, filter.value.lng]"
                                            :radius="radiusInMeters(filter.value || filter.value.range_custom)" />

                                    </l-map>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <div class="mt-8">
            <div class="bg-white shadow-sm ring-1 ring-gray-200 rounded-2xl p-8 flex items-center justify-between">

                <div>
                    <p class="text-sm text-gray-500 mb-1">Estimated Recipients</p>
                    <h2 class="text-4xl font-semibold tracking-tight text-gray-900">
                        {{ estimatedRecipients }}
                    </h2>
                    <p class="text-xs text-gray-400 mt-2">
                        Based on current filters
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
.leaflet-container {
    height: 100%;
    width: 100%;
}

.leaflet-tooltip {
    background: white;
    border-radius: 6px;
    padding: 6px 8px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    font-size: 12px;
    color: #333;
}

.leaflet-tooltip-top:before {
    border-top-color: white !important;
}


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
