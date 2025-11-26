<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faDotCircle, faSave } from "@fal"
import { faDotCircle as fasDotCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { InputNumber } from 'primevue'
import { inject, ref } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { router, useForm } from '@inertiajs/vue3'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { routeType } from '@/types/route'
import { notify } from '@kyvg/vue3-notification'
import { formatDistanceStrict } from 'date-fns'
import { cloneDeep } from 'lodash'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { StockLocation } from '@/types/Inventory/StocksManagement'
library.add(faDotCircle, fasDotCircle, faSave)

const props = defineProps<{
    stock_locations: StockLocation[]
    auditRoute?: routeType
}>()

const emits = defineEmits<{
    (e: "onClickBackground"): void
}>()

const layout = inject('layout', layoutStructure)

const cloneLocations = ref(cloneDeep(props.stock_locations))

const listLoadingLocations = ref<number[]>([])
const submitCheckStock = (locationOrgStock: StockLocation, value?: number) => {

    // Section: Submit
    router[props.auditRoute?.method || 'patch'](
        route(props.auditRoute?.name, {
            locationOrgStock: locationOrgStock?.id
        }),
        {
            quantity: typeof value !== 'undefined' ? value : locationOrgStock.quantity
        },
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
</script>

<template>
    <div>
        <div @click="() => emits('onClickBackground')" class="cursor-pointer fixed inset-0 bg-black/40 z-30" />
        <div class="relative bg-white z-40 xpy-2 xpx-3 space-y-1">
            <div class="text-center">Stock check</div>
            <div v-for="(location, idx) in cloneLocations" class="grid grid-cols-7 gap-x-3 items-center gap-2">
                <div class="col-span-3 flex items-center gap-x-2">
                    {{ location.code }}
                </div>

                <div v-tooltip="trans('Last audit :date', { date: useFormatTime(new Date(location.audited_at)) })" class="col-span-2 text-right">
                    {{ formatDistanceStrict(new Date(location.audited_at), new Date()) }}
                    <FontAwesomeIcon icon="fal fa-clock" class="text-gray-400" fixed-width aria-hidden="true" />
                </div>

                <div class="col-span-2 text-right flex items-center justify-end gap-x-1">
                    <div v-if="location.quantity != stock_locations[idx].quantity">
                        <span v-if="location.quantity > stock_locations[idx].quantity" class="text-green-600">
                            +{{ location.quantity - stock_locations[idx].quantity }}
                        </span>
                        <span v-else class="text-red-500">
                            -{{ stock_locations[idx].quantity - location.quantity }}
                        </span>
                    </div>
                    
                    <div v-else
                        v-tooltip="trans('Set as audited with same stock (:xstock stocks)', { xstock: Number(location.quantity)})"
                        @click="() => submitCheckStock(location, location.quantity)"
                        class="cursor-pointer text-gray-400 hover:text-green-500"
                    >
                        <FontAwesomeIcon
                            :icon="location.quantity != !stock_locations[idx].quantity ? 'fas fa-dot-circle' : 'fal fa-dot-circle'"
                            fixed-width
                            aria-hidden="true"
                        />
                    </div>

                    <div class="w-14">
                        <InputNumber
                            :modelValue="location.quantity"
                            @input="(event: { value: any }) => location.quantity = event.value"
                            :min="0"
                            :step="1"
                            size="small"
                            fluid
                            inputClass="!py-0"
                        />
                    </div>

                    <!-- Section: icon save -->
                    <div class="">
                        <LoadingIcon v-if="listLoadingLocations.includes(location.id)" class="text-2xl" />
                        <template v-else>
                            <FontAwesomeIcon v-if="location.quantity != stock_locations[idx].quantity" @click="() => submitCheckStock(location)" icon="fad fa-save" class="text-2xl cursor-pointer" :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" fixed-width aria-hidden="true" />
                            <FontAwesomeIcon v-else icon="fal fa-save" class="text-2xl text-gray-300" fixed-width aria-hidden="true" />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: buttons -->
        <div class="relative flex gap-x-2 z-40 mt-4">
            <Button
                label="Cancel"
                type="cancel"
                key="2"
                class="bg-red-100"
                @click="() => emits('onClickBackground')"
            />


        </div>
        <div v-if="layout.app.environment === 'local'">
            <pre>{{ stock_locations }}</pre>
        </div>
    </div>
</template>