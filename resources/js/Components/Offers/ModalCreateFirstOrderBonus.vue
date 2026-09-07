<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { ref, computed, watch } from 'vue'
import { DatePicker, InputNumber, RadioButton } from 'primevue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps<{
    shop_data: {
        id: number
        slug: string
        currency_code: string
        organisation?: string
        offercampaign?: string
    }
}>()

const isOpenModal = ref(false)
const isLoadingSubmit = ref(false)

const minAmount = ref<number | null>(null)
const discountPercentage = ref<number | null>(null)
const dateType = ref<'permanent' | 'interval'>('permanent')
const endDate = ref<Date | null>(null)

const resetForm = () => {
    minAmount.value = null
    discountPercentage.value = null
    dateType.value = 'permanent'
    endDate.value = null
}

watch(dateType, (val) => {
    if (val === 'permanent') {
        endDate.value = null
    }
})

const openModal = () => {
    resetForm()
    isOpenModal.value = true
}
const closeModal = () => {
    isOpenModal.value = false
    resetForm()
}

function formatDate(date: Date | null) {
    if (!date) return null

    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

const isFormInvalid = computed(() => {
    if (minAmount.value == null || minAmount.value < 0) return true
    if (!discountPercentage.value) return true
    if (dateType.value === 'interval' && !endDate.value) return true

    return false
})

const submitFirstOrderBonus = () => {
    isLoadingSubmit.value = true

    axios.post(
        route('grp.models.first_order_bonus.store', {
            shop: props.shop_data.id,
        }),
        {
            trigger_data_min_amount: minAmount.value,
            percentage_off: discountPercentage.value != null ? discountPercentage.value / 100 : null,
            end_at: dateType.value === 'interval' ? formatDate(endDate.value) : null,
        },
    )
    .then((response) => {
        notify({
            title: trans("Success"),
            text: trans("Successfully submit the data"),
            type: "success"
        })
        resetForm()
        isOpenModal.value = false

        if (response.data.slug) {
            router.visit(route('grp.org.shops.show.discounts.campaigns.offer.show', {
                organisation: props.shop_data.organisation,
                shop: props.shop_data.slug,
                offerCampaign: props.shop_data.offercampaign,
                offer: response.data.slug
            }))
        } else {
            router.reload()
        }
    })
    .catch((error) => {
        const errors = error.response?.data?.errors || {}
        const errMsg = Object.values(errors).join('. ') || trans("Failed to submit the data, please try again")
        notify({
            title: trans("Something went wrong"),
            text: errMsg,
            type: "error"
        })
    })
    .finally(() => {
        isLoadingSubmit.value = false
    })
}
</script>

<template>
    <div>
        <Button :label="trans('Create First Order Bonus')" @click="openModal" icon="fas fa-badge-percent" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-lg" @close="closeModal">
            <div class="p-1 space-y-3">
                <h2 class="text-2xl font-bold mb-4 text-center">
                    {{ trans('Create First Order Bonus') }}
                </h2>

                <div class="space-y-2">
                    <label class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk"
                            class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Minimum first order amount') }}:
                    </label>

                    <InputNumber v-model="minAmount" fluid inputId="fob_min_amount" mode="currency"
                        inputClass="w-full" :placeholder="trans('Enter minimum amount')"
                        :currency="props.shop_data.currency_code" locale="en-US" class="w-full" />
                </div>

                <div class="space-y-2">
                    <label class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Discount') }}:
                    </label>

                    <InputNumber v-model="discountPercentage" inputId="fob_discount"
                        :placeholder="trans('Enter percentage')" suffix="%" :min="0" :max="100" class="w-full" />
                </div>

                <div class="space-y-3">
                    <div class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Offer Duration') }}:
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4">
                        <label for="fob-permanent" class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                        :class="dateType === 'permanent' ? 'border-green-500 bg-green-50 text-green-700 font-semibold' : 'border-gray-200 hover:border-gray-300'">
                            <RadioButton v-model="dateType" inputId="fob-permanent" value="permanent" />
                            <span>{{ trans('Permanent') }}</span>
                        </label>

                        <label for="fob-interval" class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                        :class="dateType === 'interval' ? 'border-green-500 bg-green-50 text-green-700 font-semibold' : 'border-gray-200 hover:border-gray-300'">
                            <RadioButton v-model="dateType" inputId="fob-interval" value="interval" />
                            <span>{{ trans('Interval') }}</span>
                        </label>
                    </div>

                    <div v-if="dateType === 'interval'" class="space-y-2">
                        <label class="font-medium block">
                            <FontAwesomeIcon icon="fas fa-asterisk"
                                class="font-light text-xs text-red-400 align-middle" />
                            {{ trans('End Date') }}:
                        </label>

                        <DatePicker v-model="endDate" showIcon dateFormat="yy-mm-dd" class="w-full"
                            :minDate="new Date()" :placeholder="trans('Select end date')" />
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-x-4">
                    <Button @click="closeModal" type="cancel" />
                    <Button full icon="fad fa-save" @click="submitFirstOrderBonus" :label="isLoadingSubmit ? trans('Loading') : trans('Save')" :loading="isLoadingSubmit" :disabled="isFormInvalid || isLoadingSubmit" />
                </div>
            </div>
        </Modal>
    </div>
</template>
