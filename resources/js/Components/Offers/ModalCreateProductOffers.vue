<script setup lang="ts">

import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { ref } from 'vue'
import PureMultiselectInfiniteScroll from '../Pure/PureMultiselectInfiniteScroll.vue'
import { InputNumber, RadioButton } from 'primevue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'
import InformationIcon from '../Utils/InformationIcon.vue'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'
import PureInput from '../Pure/PureInput.vue'

const props = defineProps<{
    shop_data: {
        slug: string
        currency_code: string
    }
}>()

const isOpenModal = ref(false)

const offerLabel = ref('')
const typeOffer = ref('quantity')
const offerQtyItems = ref<number | null>(null)
const offerAmount = ref<number | null>(0)
const discountPercentage = ref<number | null>(null)
const offerCategoryId = ref(null)

const isLoadingSubmit = ref(false)
const submitCategoryOffer = () => {
    // Section: Submit
    router.post(
        route('grp.org.shops.show.discounts.offers.campaigns.store', {
            organisation: 'sk',
            shop: 'se',
            offerCampaign: 'co-se',
        }),
        {
            name: offerLabel.value,
            type: typeOffer.value,
            offer_qty_items: offerQtyItems.value,
            offer_amount: offerAmount.value,
            discount_percentage: discountPercentage.value
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

function onOfferQtyInput(e: { value: string | number | undefined }) {
    offerQtyItems.value = e.value == null ? null : Number(e.value)
}

const resetForm = () => {
    offerLabel.value = ''
    typeOffer.value = 'quantity'
    offerQtyItems.value = null
    offerAmount.value = null
    discountPercentage.value = null
    offerCategoryId.value = null
}
</script>

<template>
    <div>
        <Button :label="trans('Create Category Offer')" @click="isOpenModal = true" icon="fas fa-badge-percent" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="isOpenModal = false">
            <div class="px-6">
                <h2 class="text-2xl font-bold mxb-4 text-center">{{ trans('Create Category Offer') }}</h2>
                <div class="mt-8 space-y-8">

                    <div>
                        <label for="amount" class="font-medium mb-2 flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk"
                                class="font-light text-xs text-red-400 align-middle" />

                            {{ trans('Offer name') }}:
                        </label>

                        <div class="pl-4">
                            <PureInput v-model="offerLabel" :placeholder="trans('Enter offer name')" />
                        </div>
                    </div>

                    <div>
                        <label for="amount" class="font-medium mb-2 flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk"
                                class="font-light text-xs text-red-400 align-middle" />

                            {{ trans('Select product') }}:
                        </label>

                        <div class="pl-4">
                            <PureMultiselectInfiniteScroll v-model="offerCategoryId" :fetchRoute="{
                                name: 'grp.json.shop.products_for_website_workshop',
                                parameters: {
                                    shop: (route().params as any).shop
                                }
                            }" placeholder="Select product" valueProp="slug" :required="true" />
                        </div>
                    </div>

                    <div>
                        <div class="font-medium mb-2 flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk"
                                class="font-light text-xs text-red-400 align-middle" />
                            {{ trans('Select offer type') }}:
                        </div>

                        <div class="flex items-center pl-4 xjustify-around gap-x-8">
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
                                        size="small" class="w-full" />
                                    <label for="type-amount" class="cursor-pointer">{{ trans('By minimum amount')
                                        }}</label>
                                </div>
                                <InputNumber v-model="offerAmount" inputId="offer_amount" mode="currency"
                                    :currency="props.shop_data.currency_code" locale="en-US"
                                    :disabled="typeOffer !== 'amount'" class="w-full" />
                            </div>
                        </div>
                    </div>

                    <!-- Section: Discount -->
                    <div>
                        <div class="font-medium mb-2 flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk"
                                class="font-light text-xs text-red-400 align-middle" />
                            {{ trans('Discount') }}:
                        </div>

                        <div class="pl-4">
                            <InputNumber v-model="discountPercentage" inputId="offer_discount"
                                :placeholder="trans('Enter percentage')" suffix="%" :min="0" :max="100"
                                class="w-full" />
                        </div>
                    </div>
                </div>

                <div class="pl-4 mt-8 flex justify-end gap-x-4">
                    <Button @click="isOpenModal = false" type="cancel" />
                    <Button full icon="fad fa-save" :label="trans('Save')" @click="submitCategoryOffer"
                        :isLoading="isLoadingSubmit" :disabled="!offerCategoryId ||
                            !offerLabel ||
                            discountPercentage === null ||
                            (typeOffer === 'quantity' && offerQtyItems === null) ||
                            (typeOffer === 'amount' && offerAmount === null)
                            ">
                    </Button>
                </div>
            </div>
        </Modal>
    </div>
</template>
