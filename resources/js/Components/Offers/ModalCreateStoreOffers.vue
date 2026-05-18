<script setup lang="ts">

import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { ref, computed, inject, watch } from 'vue'
import { DatePicker, InputNumber, RadioButton } from 'primevue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { reset, trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'
import PureInput from '../Pure/PureInput.vue'
import InformationIcon from '../Utils/InformationIcon.vue'

const props = defineProps<{
    shop_data: {
        id: number
        slug: string
        currency_code: string
        organisation: string
        offercampaign: string
    }
}>()

const isOpenModal = ref(false)
const isLoadingSubmit = ref(false)
const layout = inject('layout', {})

const typeOffer = ref('quantity')
const offerQtyItems = ref<number | null>(1)
const offerAmount = ref<number | null>(0)
const discountPercentage = ref<number | null>(null)
const dateType = ref<'permanent' | 'interval'>('permanent')

const offerLabel = ref('')
const startDate = ref(null)
const endDate = ref(null)

const submitShopOffer = () => {
    // Section: Submit1
    router.post(
        route('grp.models.shop_offer.store', {
            shop: props.shop_data.id,
        }),
        {
            name: offerLabel.value,
            type: typeOffer.value,
            trigger_data_item_quantity: offerQtyItems.value != null ? Math.floor(offerQtyItems.value) : null,
            trigger_data_item_amount: offerAmount.value,
            percentage_off: discountPercentage.value != null ? discountPercentage.value / 100 : null,
            duration: dateType.value,
            start_at: formatDate(startDate.value),
            end_at: formatDate(endDate.value)
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                isLoadingSubmit.value = true
            },
            onSuccess: () => {
                resetForm()
                notify({
                    title: trans("Success"),
                    text: trans("Successfully submit the data"),
                    type: "success"
                })
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to submit the data, please try again"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingSubmit.value = false
            },
        }
    )
}

const today = new Date(new Date().setHours(0, 0, 0, 0))

function formatDate(date: Date | null) {
    if (!date) return null

    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

const resetForm = () => {
    offerLabel.value = ''
    typeOffer.value = 'quantity'
    dateType.value = 'permanent'
    startDate.value = null
    endDate.value = null
    discountPercentage.value = null
    offerAmount.value = 0
    offerQtyItems.value = 1
}

const isFormInvalid = computed(() => {
    if (!offerLabel.value) return true
    if (!discountPercentage.value) return true
    if (typeOffer.value === 'quantity' && !offerQtyItems.value) {
        return true
    }

    if (typeOffer.value === 'amount' && !offerAmount.value) {
        return true
    }

    if (!startDate.value) return true

    if (dateType.value === 'interval' && !endDate.value) return true

    return false
})

watch(typeOffer, (val) => {
    if (val === 'quantity') {
        offerAmount.value = 0
    } else if (val === 'amount') {
        offerQtyItems.value = 1
    }
})

watch(dateType, (val) => {
    if (val === 'permanent') {
        endDate.value = null
    }
})

resetForm();
</script>

<template>
    <div>
        <Button :label="trans('Create Offer')" @click="isOpenModal = true; resetForm();" icon="fas fa-badge-percent" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="isOpenModal = false; resetForm();">
            <div class="p-1 space-y-3">
                <h2 class="text-2xl font-bold mb-4 text-center">
                    {{ trans('Create Shop Offer') }}
                </h2>

                <!-- offer name -->
                <div class="space-y-2">
                    <label for="amount" class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk"
                            class="font-light text-xs text-red-400 align-middle" />

                        {{ trans('Offer name') }}:
                    </label>

                    <PureInput v-model="offerLabel" :placeholder="trans('Enter offer name')" />
                </div>

                <div class="space-y-2">
                    <div class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Select offer type') }}:
                    </div>

                    <div class="flex items-stretch gap-x-8">
                        <div class="space-y-2 flex-1">
                            <div class="flex items-center gap-2">
                                <RadioButton v-model="typeOffer" inputId="type-quantity" name="quantity"
                                    value="quantity" size="small" />
                                <label for="type-quantity" class="cursor-pointer">
                                    {{ trans('By quantity') }}
                                    <InformationIcon :information="trans('Total quantities of the items')" />
                                </label>
                            </div>
                            <div class="min-h-[40px]">
                            <InputNumber v-model="offerQtyItems" v-show="typeOffer === 'quantity'"  fluid
                                inputId="offer_quantity_item" :placeholder="trans('Enter minimum quantity')"
                                :disabled="typeOffer !== 'quantity'" :min="0" class="w-full" inputClass="w-full"
                                :suffix="' ' + ((offerQtyItems ?? 0) > 1 ? trans('items') : trans('item'))" />
                            </div>
                        </div>

                        <div class="space-y-2 flex-1">
                            <div class="flex items-center gap-2">
                                <RadioButton v-model="typeOffer" inputId="type-amount" name="amount" value="amount"
                                    size="small" />
                                <label for="type-amount" class="cursor-pointer">{{ trans('By minimum amount')
                                    }}</label>
                            </div>
                            <div class="min-h-[40px]">
                            <InputNumber v-show="typeOffer === 'amount'" v-model="offerAmount"   fluid inputId="offer_amount" mode="currency" inputClass="w-full" :placeholder="trans('Enter minimum amount')" 
                                :currency="props.shop_data.currency_code" locale="en-US" class="w-full"
                                :disabled="typeOffer !== 'amount'" />
                                </div>
                        </div>
                    </div>
                </div>
                
                <!-- Section: Discount -->
                <div class="space-y-2">
                    <div class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Discount') }}:
                    </div>

                    <InputNumber v-model="discountPercentage" inputId="offer_discount"
                        :placeholder="trans('Enter percentage')" suffix="%" :min="0" :max="100" class="w-full" />
                </div>

                 <!-- Section: Offer Duration -->
                <div class="space-y-3">

                    <div class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Offer Duration') }}:
                    </div>

                    <div class="flex gap-x-4">
                        <div class="flex items-center gap-x-2">
                            <RadioButton v-model="dateType" inputId="permanent" value="permanent" />
                            <label for="permanent">{{ trans('Permanent') }}</label>
                        </div>

                        <div class="flex items-center gap-x-2">
                            <RadioButton v-model="dateType" inputId="interval" value="interval" />
                            <label for="interval">{{ trans('Interval') }}</label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Start Date -->
                        <div class="space-y-2">
                            <label class="font-medium block">
                                <FontAwesomeIcon icon="fas fa-asterisk"
                                    class="font-light text-xs text-red-400 align-middle" />
                                {{ trans('Start Date') }}
                                <InformationIcon
                                    :information="trans('If start date is empty, will start immediately')" />:
                            </label>

                            <DatePicker v-model="startDate" :minDate="today" showIcon dateFormat="yy-mm-dd" class="w-full"
                                :placeholder="trans('Select start date')" />
                        </div>

                        <!-- End Date (Only for Interval) -->
                        <div v-if="dateType === 'interval'" class="space-y-2">
                            <label class="font-medium block">
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
                    <Button @click="isOpenModal = false" type="cancel" />
                    <Button full icon="fad fa-save" @click="submitShopOffer" :label="isLoadingSubmit ? trans('Loading') : trans('Save')" :isLoading="isLoadingSubmit" :disabled="isFormInvalid || isLoadingSubmit" />
                </div>

            </div>
        </Modal>
    </div>
</template>
