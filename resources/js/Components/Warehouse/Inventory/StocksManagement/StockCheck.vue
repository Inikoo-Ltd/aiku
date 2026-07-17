<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faDotCircle, faSave } from "@fal"
import { faArrowRight, faDotCircle as fasDotCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { InputNumber } from 'primevue'
import { inject, ref, watch, nextTick, onBeforeUnmount } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { router, useForm } from '@inertiajs/vue3'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { routeType } from '@/types/route'
import { notify } from '@kyvg/vue3-notification'
import { formatDistanceStrict } from 'date-fns'
import { cloneDeep } from 'lodash'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { StockLocation } from '@/types/Inventory/StocksManagement'
import Multiselect from '@vueform/multiselect'
import { faFloppyDisk } from '@fortawesome/free-solid-svg-icons'
library.add(faDotCircle, fasDotCircle, faSave)

const props = defineProps<{
    locations: StockLocation[]
    selectedLocationId: Number
    auditRoute?: routeType
    bulkAuditRoute?: routeType
    reasons?: {
        increase: [],
        decrease: [],
        transfer: [],
    }
    org_stock_id: number
}>()

const emits = defineEmits(['close'])

const layout = inject('layout', layoutStructure)
const cloneLocations = ref(
    cloneDeep(props.locations).sort((a, b) => a.code.localeCompare(b.code))
)

const inputRefs = ref<Record<number, any>>({})
const focusInterval = ref<number | null>(null)
const listLoadingLocations = ref<number[]>([])
const markAsChecked = (locationOrgStock: StockLocation) => {
    router[props.auditRoute?.method || 'patch'](
        route(props.auditRoute?.name, {
            locationOrgStock: locationOrgStock?.id
        }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                listLoadingLocations.value.push(locationOrgStock?.id)
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully audited stock location (:xlocation)", { xlocation: locationOrgStock?.code }),
                    type: "success"
                })
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to audit the stock location"),
                    type: "error"
                })
            },
            onFinish: () => {
                listLoadingLocations.value = listLoadingLocations.value.filter(id => id !== locationOrgStock?.id)
            },
        }
    )
}

const bulkSubmitAudit = () => {
    router[props.bulkAuditRoute?.method || 'patch'](
        route(props.bulkAuditRoute?.name, {
            orgStock: props.org_stock_id
        }),
        {
            audited_locations: Object.values(modifiedLocationsQuantity.value)
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                listLoadingLocations.value.push(Object.keys(modifiedLocationsQuantity.value));
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully audited the stock locations"),
                    type: "success"
                })
                emits('close')
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to audit the stock locations"),
                    type: "error"
                })
            },
            onFinish: () => {
                listLoadingLocations.value = [];
            },
        }
    )
}


const setInputRef = (el: any, id: number) => {
    if (el) {
        inputRefs.value[id] = el
    }
}

const clearFocusInterval = () => {
    if (focusInterval.value !== null) {
        clearInterval(focusInterval.value)
        focusInterval.value = null
    }
}

const focusToLocation = async (id: number) => {
    if (!id) return

    clearFocusInterval()
    await nextTick()

    let attempts = 0
    focusInterval.value = window.setInterval(() => {
        attempts++
        const comp = inputRefs.value[id]
        const input = comp?.$el?.querySelector('input') as HTMLInputElement | null

        if (input && document.activeElement !== input) {
            input.focus()
            // input.select?.()
        }

        if (!input || attempts >= 20) {
            clearFocusInterval()
        }
    }, 80)
}

onBeforeUnmount(() => {
    clearFocusInterval()
})


watch(
    () => props.selectedLocationId,
    (id) => {
        if (!id) return
        focusToLocation(id)
    },
    { immediate: true }
)

interface ModifiedLocationOrgStock {
    id: number,
    code: string
    quantity: number,
    delta: number,
    reason?: string
    note?: string
}

const modifiedLocationsQuantity = ref<Record<string, ModifiedLocationOrgStock>>({});

const hydrateModifiedLocationsQuantity = (location: StockLocation) => {
    let currentQty = Number(props.locations.find(l => l.id === location.id)?.quantity);
    let newQty = Number(location.quantity)

    if (currentQty === newQty) {
        delete modifiedLocationsQuantity.value[location.id];
        return;
    }

    modifiedLocationsQuantity.value[location.id] = {
        id: location.id,
        code: location.code,
        quantity: Number(location.quantity),
        delta: Number(newQty - currentQty)
    }
}

const currentPage = ref(1);
</script>

<template>
    <div class="space-y-2">
        <!-- list -->
        <template v-if="cloneLocations.length > 0">
            <div v-if="currentPage == 1">
                <div class="grid grid-cols-7 gap-2 border-b pb-2 font-semibold">
                    <div class="col-span-2 md:col-span-3  flex items-center gap-x-2">
                        {{ ctrans('Location') }}
                    </div>
                    <div class="col-span-2 md:col-span-2 text-right">
                        {{ ctrans('Last audited at') }}
                    </div>
                    <div class="col-span-3 md:col-span-2 text-right flex items-center justify-end gap-x-1">
                        {{ ctrans('New Quantity') }}
                    </div>
                </div>
                <div v-for="(location, idx) in cloneLocations" :key="location.id" class="grid grid-cols-7 gap-2 border-b pb-2">
                    <div class="col-span-2 md:col-span-3  flex items-center gap-x-2">
                        {{ location.code }}
                    </div>
    
                    <div v-if="location.audited_at" v-tooltip="trans('Last audit  :date', { date: useFormatTime(new Date(location.audited_at)) })" class="col-span-2 md:col-span-2 text-right">
                        {{ formatDistanceStrict(new Date(location.audited_at), new Date()) }}
                        <FontAwesomeIcon icon="fal fa-clock" class="text-gray-400" fixed-width aria-hidden="true" />
                    </div>
    
                    <div v-else class="col-span-2 md:col-span-2 text-right text-sm italic opacity-60 whitespace-nowrap">
                        {{ trans("Never audited") }}
                    </div>
    
                    <div class="col-span-3 md:col-span-2 text-right flex items-center justify-end gap-x-1">
                        <div v-if="location.quantity != props.locations.find(l => l.id === location.id)?.quantity">
                            <span v-if="location.quantity > props.locations.find(l => l.id === location.id)?.quantity" class="text-green-600">
                                +{{ location.quantity - (props.locations.find(l => l.id === location.id)?.quantity ?? 0) }}
                            </span>
                            <span v-else class="text-red-500">
                                -{{ (props.locations.find(l => l.id === location.id)?.quantity ?? 0) - location.quantity }}
                            </span>
                        </div>
                        
                        <div v-else
                            v-tooltip="trans('Set as audited with same stock (:xstock stocks)', { xstock: Number(location.quantity)})"
                            @click="() => markAsChecked(location)"
                            class="cursor-pointer text-gray-400 hover:text-green-500"
                        >
                            <FontAwesomeIcon
                                :icon="location.quantity != !props.locations[idx].quantity ? 'fas fa-dot-circle' : 'fal fa-dot-circle'"
                                fixed-width
                                aria-hidden="true"
                            />
                        </div>
                        <div class="w-14">
                            <InputNumber
                                :ref="el => setInputRef(el, location.id)"
                                :modelValue="location.quantity"
                                @keydown.enter.prevent="submitCheckStock(location, Number(location.quantity))"
                                @input="(event: { value: any }) => {
                                    location.quantity = event.value
                                    hydrateModifiedLocationsQuantity(location);
                                }"
                                :min="0"
                                :step="1"
                                size="small"
                                fluid
                                inputClass="!py-0"
                            />
                        </div>
                    </div>
                </div>
            </div>
            <div v-else> 
                <div class="grid grid-cols-6 gap-3 border-b pb-2 font-semibold">
                    <div class="col-span-1">
                        {{ ctrans('Location Code') }}
                    </div>
                    <div class="col-span-1">
                        {{ ctrans('New Quantity') }}
                    </div>
                    <div class="col-span-2">
                        {{ ctrans('Reason') }}
                        <span class="text-red-500">*</span>
                    </div>
                    <div class="col-span-2">
                        {{ ctrans('Note') }}
                        <span class="font-normal text-gray-400 italic">
                            {{ ctrans('optional') }}
                        </span>
                    </div>
                </div>
                <div v-for="location in modifiedLocationsQuantity" :key="location.id" class="grid grid-cols-6 gap-3 border-b py-2">
                    <div class="col-span-1 flex items-center h-10">
                        {{ location.code }}
                    </div>
                    <div class="col-span-1 flex items-center gap-x-2 h-10 tabular-nums">
                        {{ location.quantity }}
                        <span
                            class="border rounded px-1.5 py-0.5 text-xs font-semibold"
                            :class="location.delta > 0 ? 'text-green-700 border-green-300 bg-green-50' : 'text-red-700 border-red-300 bg-red-50'"
                        >
                            {{ location.delta > 0 ? '+' : '' }}{{ location.delta }}
                        </span>
                    </div>
                    <div class="col-span-2">
                        <Multiselect
                            v-model="location.reason"
                            :options="(location.delta > 0 ? reasons?.increase : reasons?.decrease) ?? []"
                            :placeholder="ctrans('Select your reason')"
                            :canClear="false"
                            :mode="'single'"
                            :closeOnSelect="true"
                            :canDeselect="false"
                            :hideSelected="false"
                            :searchable="true"
                            :filter-results="false"                           
                        />
                    </div>
                    <div class="col-span-2">
                        <textarea
                            v-model.trim="location.note"
                            :rows="1"
                            :placeholder="ctrans('Add more details about this audit')"
                            class="block w-full h-10 py-2 rounded border-gray-300 placeholder:text-gray-400 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm leading-5 resize-none"
                        />
                    </div>
                </div>
            </div>
        </template>
        <div
            v-else
            class="flex flex-col items-center justify-center text-center py-10 border border-dashed border-gray-300 rounded-lg"
        >
            <div class="text-gray-600 font-medium">
                {{ trans("No locations available") }}
            </div>

            <div class="text-sm text-gray-400 mt-1">
                {{ trans("You haven't added any locations yet") }}
            </div>
        </div>
        <!-- Section: buttons -->
         <div class="flex xjustify-end gap-2 pt-3">
            <Button 
                :label="currentPage == 1 ? ctrans('Close') : ctrans('Back')" 
                type="tertiary" 
                icon="far fa-arrow-left" 
                @click="() => {
                    if (currentPage == 1) {
                        emits('close')
                    } else {
                        currentPage = 1
                    }
                }" 
            />
            <Button 
                :label="currentPage == 1 ? ctrans('Next') : ctrans('Save')" 
                :type="'primary'" 
                :icon="currentPage == 1 ? faArrowRight : faFloppyDisk" 
                class="ml-auto" @click="() => {
                    if (currentPage == 1) {
                        currentPage = 2
                    } else {
                        bulkSubmitAudit()
                    }
                }" 
                :disabled="
                    currentPage == 1
                        ? Object.keys(modifiedLocationsQuantity).length === 0
                        : Object.values(modifiedLocationsQuantity).some(item => !item.reason)
                "
                :loading="listLoadingLocations.length > 1"
            />
        </div>
    </div>
</template>
