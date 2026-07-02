<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { ref, computed, watch, nextTick } from 'vue'
import { InputNumber, RadioButton, DatePicker } from 'primevue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'
import { ctrans } from '@/Composables/useTrans'
import InformationIcon from '../Utils/InformationIcon.vue'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'
import PureInput from '../Pure/PureInput.vue'
import axios from 'axios'

const props = defineProps<{
    shop_data: {
        id: number
        slug: string
        currency_code: string
        default_dates?: {
            start: string
        }
    }
    product_category_id: number
}>()

const isOpenModal = ref(false)
const offerLabel = ref('')
const buyQuantity = ref<number | null>(2)
const freeQuantity = ref<number | null>(1)
const dateType = ref<'permanent' | 'interval'>('permanent')
const startDate = ref<Date | null>(null)
const endDate = ref<Date | null>(null)
const quickIntervalDays = ref<number | null>(null)
const isLoadingSubmit = ref(false)

const quickIntervalPresets = [1, 2, 3, 7]
const today = new Date(new Date().setHours(0, 0, 0, 0))

const freeQuantityMax = computed(() => Math.max(1, (buyQuantity.value ?? 2) - 1))

const openModal = () => {
    resetForm()
    startDate.value = props.shop_data.default_dates?.start ? new Date(props.shop_data.default_dates.start) : today
    isOpenModal.value = true
}

const closeModal = () => {
    isOpenModal.value = false
    resetForm()
}

const resetForm = () => {
    offerLabel.value = ''
    buyQuantity.value = 2
    freeQuantity.value = 1
    dateType.value = 'permanent'
    startDate.value = null
    endDate.value = null
    quickIntervalDays.value = null
}

function formatDate(date: Date | null) {
    if (!date) return null

    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

let isApplyingPreset = false

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

const isFormInvalid = computed(() => {
    if (!offerLabel.value) return true
    if (!buyQuantity.value || buyQuantity.value < 2) return true
    if (!freeQuantity.value || freeQuantity.value >= buyQuantity.value) return true
    if (!startDate.value) return true
    if (dateType.value === 'interval' && !endDate.value) return true

    return false
})

const submitOffer = () => {
    isLoadingSubmit.value = true

    const payload = {
        name: offerLabel.value,
        product_category_id: props.product_category_id,
        trigger_data_item_quantity: buyQuantity.value != null ? Math.floor(buyQuantity.value) : null,
        free_quantity: freeQuantity.value != null ? Math.floor(freeQuantity.value) : null,
        duration: dateType.value,
        start_at: formatDate(startDate.value),
        end_at: dateType.value === 'interval' ? formatDate(endDate.value) : null,
    }

    axios.post(
        route('grp.models.bogo_offer.store', { shop: props.shop_data.id }),
        payload
    )
        .then(() => {
            notify({
                title: trans('Success'),
                text: trans('Successfully submit the data'),
                type: 'success'
            })
            closeModal()
            router.reload()
        })
        .catch((error) => {
            const errors = error.response?.data?.errors || {}
            const errMsg = Object.values(errors).join('. ') || trans('Failed to submit the data, please try again')
            notify({
                title: trans('Something went wrong'),
                text: errMsg,
                type: 'error'
            })
        })
        .finally(() => {
            isLoadingSubmit.value = false
        })
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

watch(freeQuantityMax, (max) => {
    if (freeQuantity.value != null && freeQuantity.value > max) {
        freeQuantity.value = max
    }
})
</script>

<template>
    <div>
        <Button :label="trans('Mix & Match')" @click="openModal" icon="fas fa-layer-group" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="closeModal">
            <div class="p-1 space-y-3">
                <h2 class="text-2xl font-bold mb-4 text-center">{{ trans('Create Mix & Match Offer') }}</h2>

                <div class="space-y-2">
                    <label class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Offer name') }}:
                    </label>
                    <PureInput v-model="offerLabel" :placeholder="trans('Enter offer name')" />
                </div>

                <div class="space-y-2">
                    <div class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Rule') }}:
                        <InformationIcon :information="trans('Customer buys any mix of products in this family and gets the cheapest ones free')" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <span>{{ trans('Buy') }}</span>
                        <InputNumber v-model="buyQuantity" inputId="buy_quantity" :min="2" class="w-40" inputClass="w-full"
                            :suffix="' ' + ((buyQuantity ?? 0) > 1 ? trans('items') : trans('item'))" />
                        <span>{{ trans('get') }}</span>
                        <InputNumber v-model="freeQuantity" inputId="free_quantity" :min="1" :max="freeQuantityMax"
                            class="w-40" inputClass="w-full"
                            :suffix="' ' + ((freeQuantity ?? 0) > 1 ? trans('items') : trans('item'))" />
                        <span>{{ trans('cheapest for free') }}</span>
                    </div>

                    <p class="text-sm text-gray-500">
                        {{ trans('e.g. buy :buy, get :free cheapest free', { buy: String(buyQuantity ?? 0), free: String(freeQuantity ?? 0) }) }}
                    </p>
                </div>

                <div class="space-y-3">
                    <div class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Offer Duration') }}:
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <label for="mm-permanent"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                            :class="dateType === 'permanent'
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            <RadioButton v-model="dateType" inputId="mm-permanent" value="permanent" />
                            <span>{{ trans('Permanent') }}</span>
                        </label>

                        <label for="mm-interval"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                            :class="dateType === 'interval'
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            <RadioButton v-model="dateType" inputId="mm-interval" value="interval" />
                            <span>{{ trans('Interval') }}</span>
                        </label>

                        <button v-if="dateType === 'interval'" v-for="days in quickIntervalPresets" :key="days" type="button"
                            @click="applyQuickInterval(days)"
                            class="px-3.5 py-2.5 rounded-lg border text-sm cursor-pointer transition-colors"
                            :class="quickIntervalDays === days
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            {{ ctrans(':count day', { count: String(days) }) }}
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="font-medium mb-2 block">
                                <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                                {{ trans('Start Date') }}
                                <InformationIcon :information="trans('If start date is empty, will start immediately')" />:
                            </label>
                            <DatePicker v-model="startDate" :minDate="today" showIcon dateFormat="yy-mm-dd" class="w-full"
                                :placeholder="trans('Select start date')" />
                        </div>

                        <div v-if="dateType === 'interval'" class="space-y-2">
                            <label class="font-medium mb-2 block">
                                {{ trans('End Date') }}
                                <InformationIcon :information="trans('If start date is empty, will start immediately')" />:
                            </label>
                            <DatePicker v-model="endDate" showIcon dateFormat="yy-mm-dd" class="w-full"
                                :minDate="startDate || undefined" :placeholder="trans('Select end date')" />
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-x-4">
                    <Button @click="closeModal" type="cancel" />
                    <Button
                        full
                        icon="fad fa-save"
                        :label="isLoadingSubmit ? trans('Loading') : trans('Save')"
                        @click="submitOffer"
                        :disabled="isFormInvalid || isLoadingSubmit"
                        :loading="isLoadingSubmit"
                    />
                </div>
            </div>
        </Modal>
    </div>
</template>
