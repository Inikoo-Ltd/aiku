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
import { reactive, computed, watch, ref, onMounted, nextTick } from "vue";
import { useFormatTime } from "@/Composables/useFormatTime";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown, faFilter, faTimes, faPlus } from "@fas"
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
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import '@vuepic/vue-datepicker/dist/main.css'
import { LMap, LTileLayer, LMarker, LTooltip, LCircle } from "@vue-leaflet/vue-leaflet"
import { useFilterRecipients } from "@/Composables/useFilterRecipients";
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
    recipientFilterRoute: routeType
    filters: Record<string, any>,
    filtersStructure: Record<string, any>
    recipientsRecipe: any,
    shopId: number,
    estimatedRecipients: number,
    shopSlug: string
}>();

const {
    isByOrderValueInvalid,
    activeFilters,
    activeFilterCount,
    isAllCustomers,
    readyFilters,
    addFilter,
    removeFilter,
    clearAllFilters,
    isAmountRangeInvalid,
    fetchCustomers,
    getLatLngToLocation,
    onMapClick,
    onMarkerDrag,
    radiusInMeters,
    shouldShowMap,
    saveFilters,
    getPostalCodeModel,
    hydrateSavedFilters,
} = useFilterRecipients(props)

const filterMenu = ref()

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

const formatNumber = (num: number | null | undefined) => {
    return new Intl.NumberFormat('en-GB').format(num ?? 0)
}

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

function onBasketModeChange(filter: { value: { mode: any; date_range: any[] | null; }; }, event: { value: any; }) {
    const val = event.value

    filter.value.mode = val

    if (val === 'custom') {
        filter.value.date_range = null
        return
    }

    const days = Number(val)
    if (!isNaN(days)) {
        const end = new Date()
        const start = new Date()
        start.setDate(end.getDate() - days)

        filter.value.date_range = [
            formatDate(start),
            formatDate(end)
        ]
    }
}

function formatDate(d: Date) {
    return d.toISOString().split('T')[0]
}

onMounted(async () => {
    if (props.recipientsRecipe) {
        activeFilters.value = hydrateSavedFilters(
            props.recipientsRecipe,
            props.filtersStructure
        )

        for (const [key, filter] of Object.entries(activeFilters.value)) {
            if (filter.config.type === 'entity_behaviour') {
                await preloadEntityOptions(key, filter.value.ids)
            }

            if (filter.config.label === 'By Family Never Ordered' && filter.value.ids) {
                await preloadEntityOptions(key, [filter.value.ids])
            }
        }
        await nextTick()
        fetchCustomers()
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

            if (filter.config.type === 'location') {

                if (val.mode === 'direct') {
                    if (val.lat || val.lng || val.radius || val.location) {
                        val.location = ''
                        val.radius = null
                        val.radius_custom = null
                        val.lat = null
                        val.lng = null
                        val.zoom = null
                        val.resolved = false
                    }
                }

                if (val.mode === 'radius') {
                    if ((val.country_ids && val.country_ids.length) || (val.postal_codes && val.postal_codes.length)) {
                        val.country_ids = []
                        val.postal_codes = []
                    }
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
                    {{ trans("Audience: All Customers") }}
                </span>
            </div>
            <!-- right side -->
            <div class="flex items-center gap-3">

                <Button :label="trans('Save')" type="positive" icon="save" @click="saveFilters" class="h-10 px-4"
                    :disabled="isByOrderValueInvalid" />
            </div>
        </div>
        <div v-if="Object.keys(activeFilters).length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
            <div v-for="(filter, key) in readyFilters" :key="key"
                class="border rounded p-4 bg-gray-50 relative min-w-0">

                <div v-if="filter.config" class="flex justify-between mb-2">
                    <span class="font-medium">{{ filter.config.label ?? '-' }}</span>
                    <Button :icon="faTimes" type="negative" @click="removeFilter(key)" />
                </div>
                <span
                    class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 mb-2">
                    {{ trans("Active") }}
                </span>
                <!-- BOOLEAN -->
                <template v-if="filter.config.type === 'boolean'" class="mt-2">
                    <template v-if="key === 'orders_collection' || key === 'by_showroom_orders'">
                        <ToggleButton v-model="filter.value" onLabel="Active" offLabel="Inactive" class="mb-3 w-full"
                            disabled />
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
                            {{ trans('filter.config.options.date_range.label') }}
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
                            mode="currency" currency="GBP" locale="en-GB" class="w-full" inputClass="w-full"
                            :class="{ 'p-invalid': isAmountRangeInvalid(filter) }"
                            :max="filter.value.amount_range.max ?? undefined" />
                        <InputNumber v-model="filter.value.amount_range.max" placeholder="Maximum amount"
                            mode="currency" currency="GBP" locale="en-GB" class="w-full" inputClass="w-full"
                            :min="filter.value.amount_range.min ?? undefined" />

                        <p v-if="isAmountRangeInvalid(filter)" class="text-xs text-red-500 mt-1 col-span-2">
                            {{ trans("Minimum amount is required.") }}
                        </p>
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
                            <PureMultiselectInfiniteScroll v-if="getEntityFetchRoute(key)" :key="key" mode="single"
                                v-model="filter.value.ids" :initOptions="preloadedEntities[key] || []"
                                :fetchRoute="getEntityFetchRoute(key)!" valueProp="id" labelProp="name"
                                :placeholder="trans('Select items...')" />
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
                        <PureMultiselectInfiniteScroll v-if="getEntityFetchRoute(key)" :key="key" mode="multiple"
                            v-model="filter.value.ids" :initOptions="preloadedEntities[key] || []"
                            :fetchRoute="getEntityFetchRoute(key)!" valueProp="id" labelProp="name"
                            :placeholder="trans('Select items...')" />
                    </div>
                    <div class="mb-3">
                        <div class="flex items-center gap-6">
                            <div class="flex items-center gap-2">
                                <RadioButton v-model="filter.value.combine_logic" :value="true" inputId="multi" />
                                <label for="multi" class="text-sm">{{ trans("Allow multiple behaviours") }}</label>
                            </div>

                            <div class="flex items-center gap-2">
                                <RadioButton v-model="filter.value.combine_logic" :value="false" inputId="single" />
                                <label for="single" class="text-sm">{{ trans("Single behaviour only") }}</label>
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
                                {{ trans("You can enter multiple postal codes separated by commas.") }}
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

                            <Button label="Find On Map" @click="() => {
                                filter.value.lastSource = 'input'
                                getLatLngToLocation(filter, 'forward')
                            }" />
                            <!-- MAP PLACEHOLDER -->
                            <div v-if="shouldShowMap(filter.value)" class="h-72 w-full rounded">
                                <div v-if="filter.value.loadingMap"
                                    class="absolute inset-0 bg-white/70 backdrop-blur-sm z-10 flex items-center justify-center rounded">
                                    <div class="flex flex-col items-center gap-2 text-gray-600">
                                        <i class="pi pi-spin pi-spinner text-2xl"></i>
                                        <span class="text-sm">{{ trans("Finding location...") }}</span>
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
                                                📍 {{ trans("This is your point") }}<br>
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
