<script setup lang="ts">

import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { ref, computed, nextTick, watch } from 'vue'
import { InputNumber, RadioButton, DatePicker } from 'primevue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faGift } from '@fal'
import { trans } from 'laravel-vue-i18n'
import InformationIcon from '../Utils/InformationIcon.vue'
import { router } from '@inertiajs/vue3'
import PureInput from '../Pure/PureInput.vue'

library.add(faGift)

const props = defineProps<{
    shop_data: {
        id: number
        slug?: string
        organisation?: string
        offercampaign?: string
        currency_code?: string
        default_dates: {
            start: string
            end: string
        }
    }
    product_id?: number
}>()

const today = new Date(new Date().setHours(0, 0, 0, 0))
const isOpenModal = ref(false)

const offerLabel = ref('')
const buyQuantity = ref<number | null>(1)
const freeQuantity = ref<number | null>(1)
const dateType = ref<'permanent' | 'interval'>('permanent')
const startDate = ref<Date | null>(today)
const endDate = ref<Date | null>(null)

const quickIntervalDays = ref<number | null>(null)
const quickIntervalPresets = [1, 2, 3, 7]

const isLoadingSubmit = ref(false)

let isApplyingPreset = false

const openModal = () => {
    resetForm()
    startDate.value = new Date(props.shop_data.default_dates.start)
    endDate.value = new Date(props.shop_data.default_dates.end)
    isOpenModal.value = true
}

const closeModal = () => {
    isOpenModal.value = false
    resetForm()
}

const applyQuickInterval = (days: number) => {
    isApplyingPreset = true
    dateType.value = 'interval'

    const start = startDate.value ? new Date(startDate.value) : new Date(today)
    const end = new Date(start)
    end.setDate(end.getDate() + days)

    startDate.value = start
    endDate.value = end
    quickIntervalDays.value = days

    nextTick(() => {
        isApplyingPreset = false
    })
}

function formatDate(date: Date | null) {
    if (!date) return null

    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

const submitBogoOffer = () => {
    isLoadingSubmit.value = true

    router.post(
        route('grp.models.bogo_offer.store', {
            shop: props.shop_data.id,
        }),
        {
            name: offerLabel.value,
            product_id: props.product_id,
            buy_quantity: buyQuantity.value,
            free_quantity: freeQuantity.value,
            duration: dateType.value,
            start_at: formatDate(startDate.value),
            end_at: dateType.value === 'interval' ? formatDate(endDate.value) : null,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                isLoadingSubmit.value = false
            },
        }
    )
}

const resetForm = () => {
    offerLabel.value = ''
    buyQuantity.value = 1
    freeQuantity.value = 1
    dateType.value = 'permanent'
    startDate.value = today
    endDate.value = null
    quickIntervalDays.value = null
    isLoadingSubmit.value = false
}

watch(dateType, (val) => {
    if (val === 'permanent') {
        endDate.value = null
        quickIntervalDays.value = null
    }
})

watch([startDate, endDate], () => {
    if (!isApplyingPreset) {
        quickIntervalDays.value = null
    }
})

const isFormInvalid = computed(() => {
    if (!offerLabel.value) return true
    if (!props.product_id) return true
    if (!buyQuantity.value) return true
    if (!freeQuantity.value) return true
    if (!startDate.value) return true
    if (dateType.value === 'interval' && !endDate.value) return true
    return false
})

resetForm()
</script>

<template>
    <div>
        <Button :label="trans('Buy One Get One Free')" @click="openModal" icon="fal fa-gift" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="closeModal">
            <div class="p-1 space-y-3">
                <h2 class="text-2xl font-bold mb-4 text-center">{{ trans('Create Buy One Get One Free') }}</h2>

                <!-- <div class="space-y-2">
                    <label class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Offer name') }}:
                    </label>

                    <PureInput v-model="offerLabel" :placeholder="trans('Enter offer name')" />
                </div> -->

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="font-medium mb-2 flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                            {{ trans('Buy quantity') }}
                            <InformationIcon :information="trans('Quantity the customer must buy to trigger the offer')" />:
                        </label>

                        <InputNumber v-model="buyQuantity" inputId="bogo_buy_quantity" :min="1" class="w-full"
                            :placeholder="trans('Enter quantity')"
                            :suffix="' ' + ((buyQuantity ?? 0) > 1 ? trans('items') : trans('item'))" />
                    </div>

                    <div class="space-y-2">
                        <label class="font-medium mb-2 flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                            {{ trans('Free quantity') }}
                            <InformationIcon :information="trans('Quantity of the same product given for free')" />:
                        </label>

                        <InputNumber v-model="freeQuantity" inputId="bogo_free_quantity" :min="1" class="w-full"
                            :placeholder="trans('Enter quantity')"
                            :suffix="' ' + ((freeQuantity ?? 0) > 1 ? trans('items') : trans('item'))" />
                    </div>
                </div>

                <!-- Section: Offer Duration -->
                <div class="space-y-3">
                    <div class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Offer Duration') }}:
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <label for="bogo-permanent"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                            :class="dateType === 'permanent'
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            <RadioButton v-model="dateType" inputId="bogo-permanent" value="permanent" />
                            <span>{{ trans('Permanent') }}</span>
                        </label>

                        <label for="bogo-interval"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                            :class="dateType === 'interval'
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            <RadioButton v-model="dateType" inputId="bogo-interval" value="interval" />
                            <span>{{ trans('Interval') }}</span>
                        </label>

                        <button v-if="dateType === 'interval'" v-for="days in quickIntervalPresets" :key="days"
                            type="button" @click="applyQuickInterval(days)"
                            class="px-3.5 py-2.5 rounded-lg border text-sm cursor-pointer transition-colors"
                            :class="quickIntervalDays === days
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            {{ trans(':count day', { count: String(days) }) }}
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Start Date -->
                        <div class="space-y-2">
                            <label class="font-medium mb-2 block">
                                <FontAwesomeIcon icon="fas fa-asterisk"
                                    class="font-light text-xs text-red-400 align-middle" />
                                {{ trans('Start Date') }}
                                <InformationIcon
                                    :information="trans('If start date is empty, will start immediately')" />:
                            </label>

                            <DatePicker v-model="startDate" :minDate="today" showIcon dateFormat="yy-mm-dd"
                                class="w-full" :placeholder="trans('Select start date')" />
                        </div>

                        <!-- End Date (Only for Interval) -->
                        <div v-if="dateType === 'interval'" class="space-y-2">
                            <label class="font-medium mb-2 block">
                                {{ trans('End Date') }}
                                <InformationIcon
                                    :information="trans('If end date is empty, will treat as permanent')" />:
                            </label>

                            <DatePicker v-model="endDate" showIcon dateFormat="yy-mm-dd" class="w-full"
                                :minDate="startDate || undefined" :placeholder="trans('Select end date')" />
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-x-4">
                    <Button @click="closeModal" type="cancel" />
                    <Button full icon="fad fa-save" :label="isLoadingSubmit ? trans('Loading') : trans('Save')"
                        @click="submitBogoOffer" :loading="isLoadingSubmit" :disabled="isFormInvalid || isLoadingSubmit">
                    </Button>
                </div>
            </div>
        </Modal>
    </div>
</template>
