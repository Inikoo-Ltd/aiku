<script setup lang="ts">

import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { ref, computed } from 'vue'
import PureMultiselectInfiniteScroll from '../Pure/PureMultiselectInfiniteScroll.vue'
import { InputNumber, RadioButton, DatePicker } from 'primevue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'
import InformationIcon from '../Utils/InformationIcon.vue'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'
import PureInput from '../Pure/PureInput.vue'

const props = defineProps<{
    shop_data: {
        id: number
        slug: string
        currency_code: string
    }
}>()

const isOpenModal = ref(false)

const offerLabel = ref('')
const typeOffer = ref('quantity')
const offerQtyItems = ref<number | null>(null)
const offerAmount = ref<number | null>(null)
const discountPercentage = ref<number | null>(null)
const offerCategoryId = ref(null)
const isLoadingSubmit = ref(false)
const dateType = ref<'permanent' | 'interval'>('permanent')
const startDate = ref<Date | null>(null)
const endDate = ref<Date | null>(null)

function onOfferQtyInput(e: { value: string | number | undefined }) {
    offerQtyItems.value = e.value == null ? null : Number(e.value)
}

const submitCategoryOffer = () => {
    // Section: Submit
    router.post(
        route('grp.models.category_offer.store', {
            shop: props.shop_data.id,
        }),
        {
            name: offerLabel.value,
            type: typeOffer.value,
            product_category_id: offerCategoryId.value,
            trigger_data_item_quantity: offerQtyItems.value != null ? Math.floor(offerQtyItems.value) : null,
            trigger_data_item_amount: offerAmount.value,
            percentage_off: discountPercentage.value != null ? discountPercentage.value / 100 : null,
            duration: dateType.value,
            start_at: startDate.value,
            end_at: endDate.value
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

const resetForm = () => {
    offerLabel.value = ''
    typeOffer.value = 'quantity'
    discountPercentage.value = null
    offerQtyItems.value = null
    offerAmount.value = null
    offerCategoryId.value = null
    dateType.value = 'permanent'
    startDate.value = null
    endDate.value = null
}

const isFormInvalid = computed(() => {

    if (!offerCategoryId.value) return true

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
</script>

<template>
    <div>
        <Button :label="trans('Create Category Offer')" @click="isOpenModal = true" icon="fas fa-badge-percent" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="isOpenModal = false">
            <div class="p-1 space-y-3">
                <h2 class="text-2xl font-bold mb-4 text-center">{{ trans('Create Category Offer') }}</h2>

                <div class="space-y-2">
                    <label for="amount" class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />

                        {{ trans('Offer name') }}:
                    </label>


                    <PureInput v-model="offerLabel" :placeholder="trans('Enter offer name')" />

                </div>

                <div class="space-y-2">
                    <label for="amount" class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />

                        {{ trans('Select category') }}:
                    </label>


                    <PureMultiselectInfiniteScroll v-model="offerCategoryId" :fetchRoute="{
                        name: 'grp.json.shop.product_categories',
                        parameters: {
                            shop: props.shop_data.slug
                        }
                    }" required :placeholder="trans('Select category from the list')" valueProp="id" />

                </div>

                <div class="space-y-2">
                    <div class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Select offer type') }}:
                    </div>

                    <div class="flex items-center xjustify-around gap-x-8">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <RadioButton v-model="typeOffer" inputId="type-quantity" name="quantity"
                                    value="quantity" size="small" />
                                <label for="type-quantity" class="cursor-pointer">
                                    {{ trans('By quantity') }}
                                    <InformationIcon :information="trans('Total quantities of the items')" />
                                </label>
                            </div>

                            <InputNumber :modelValue="offerQtyItems" @input="onOfferQtyInput"
                                inputId="offer_quantity_item" :placeholder="trans('Enter number')"
                                :disabled="typeOffer !== 'quantity'" :min="0"
                                :suffix="' ' + ((offerQtyItems ?? 0) > 1 ? trans('items') : trans('item'))" />
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <RadioButton v-model="typeOffer" inputId="type-amount" name="amount" value="amount"
                                    size="small" />
                                <label for="type-amount" class="cursor-pointer">{{ trans('By minimum amount')
                                    }}</label>
                            </div>
                            <InputNumber v-model="offerAmount" inputId="offer_amount" mode="currency"
                                :currency="props.shop_data.currency_code" locale="en-US" class="w-full"
                                :disabled="typeOffer !== 'amount'" />
                        </div>
                    </div>
                </div>

                <!-- Section: Discount -->
                <div class="space-y-2">
                    <div class="font-medium mb-2 flex items-center gap-x-1">
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
                            <label class="font-medium mb-2 block">
                                <FontAwesomeIcon icon="fas fa-asterisk"
                                    class="font-light text-xs text-red-400 align-middle" />
                                {{ trans('Start Date') }}
                                <InformationIcon
                                    :information="trans('If start date is empty, will start immediately')" />:
                            </label>

                            <DatePicker v-model="startDate" showIcon dateFormat="yy-mm-dd" class="w-full"
                                :placeholder="trans('Select start date')" />
                        </div>

                        <!-- End Date (Only for Interval) -->
                        <div v-if="dateType === 'interval'" class="space-y-2">
                            <label class="font-medium mb-2 block">
                                {{ trans('End Date') }}
                                <InformationIcon
                                    :information="trans('If start date is empty, will start immediately')" />:
                            </label>

                            <DatePicker v-model="endDate" showIcon dateFormat="yy-mm-dd" class="w-full"
                                :minDate="startDate" :placeholder="trans('Select end date')" />
                        </div>
                    </div>

                </div>


                <div class="mt-8 flex justify-end gap-x-4">
                    <Button @click="isOpenModal = false" type="cancel" />
                    <Button full icon="fad fa-save" :label="trans('Save')" @click="submitCategoryOffer"
                        :isLoading="isLoadingSubmit" :disabled="isFormInvalid">
                    </Button>
                </div>


            </div>
        </Modal>
    </div>
</template>
