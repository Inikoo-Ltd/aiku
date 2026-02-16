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

const props = defineProps<{
    shop_data: {
        slug: string
        currency_code: string
    }
}>()

const isOpenModal = ref(false)

const typeOffer = ref('quantity')
const offerQtyItems = ref(0)
const offerAmount = ref(0)
const discountPercentage = ref()

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
                notify({
                    title: trans("Success"),
                    text: trans("Successfully submit the data"),
                    type: "success"
                })
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to set location"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingSubmit.value = false
            },
        }
    )
}
</script>

<template>
    <div>
        <Button
            :label="trans('Create Category Offer')"
            @click="isOpenModal = true"
            icon="fas fa-badge-percent"
        />

        <Modal
            :isOpen="isOpenModal"
            width="w-full max-w-2xl"
            @close="isOpenModal = false"
        >
            <div class="px-6">
                <h2 class="text-2xl font-bold mxb-4 text-center">{{ trans('Create Category Offer') }}</h2>
                <!-- <p class="italic mb-6 text-center opacity-70 text-sm">Enter the details to create a category offer</p> -->
                <div class="mt-8 space-y-8">

                    <div>
                        <label for="amount" class="font-medium mb-2 flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle"/>

                            {{ trans('Select category') }}:
                        </label>

                        <div class="pl-4">
                            <PureMultiselectInfiniteScroll
                                :fetchRoute="{
                                    name: 'grp.json.shop.product_categories',
                                    parameters: {
                                        shop: props.shop_data.slug
                                    }
                                }"
                                :placeholder="trans('Select category from the list')"
                            />
                        </div>
                    </div>

                    <div>
                        <div class="font-medium mb-2 flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle"/>
                            {{ trans('Select offer type') }}:
                        </div>

                        <div class="flex items-center pl-4 xjustify-around gap-x-8">
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <RadioButton v-model="typeOffer" inputId="type-quantity" name="quantity" value="quantity" size="small" />
                                    <label for="type-quantity" class="cursor-pointer">
                                        {{ trans('By quantity') }}
                                        <InformationIcon :information="trans('Total quantities of the items')" />
                                    </label>
                                </div>

                                <InputNumber
                                    :modelValue="offerQtyItems"
                                    @input="(e) => offerQtyItems = e.value"
                                    inputId="integeronly"
                                    :placeholder="trans('Enter number')"
                                    :disabled="typeOffer !== 'quantity'"
                                    :min="0"
                                    :suffix="' ' + (offerQtyItems > 1 ? trans('items') : trans('item'))"
                                />
                            </div>

                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <RadioButton v-model="typeOffer" inputId="type-amount" name="amount" value="amount" size="small" />
                                    <label for="type-amount" class="cursor-pointer">{{ trans('By minimum amount') }}</label>
                                </div>
                                <InputNumber
                                    v-model="offerAmount"
                                    inputId="currency-us"
                                    mode="currency"
                                    :currency="props.shop_data.currency_code"
                                    locale="en-US"
                                    :disabled="typeOffer !== 'amount'"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Section: Discount -->
                    <div>
                        <div class="font-medium mb-2 flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle"/>
                            {{ trans('Discount') }}:
                        </div>

                        <div class="pl-4">
                            <InputNumber
                                v-model="discountPercentage"
                                inputId="integeronly"
                                :placeholder="trans('Enter percentage')"
                                suffix="%"
                                :min="0"
                                :max="100"
                            />
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end space-x-4">
                    <Button
                        @click="isOpenModal = false"
                        type="cancel"
                    />
                    <Button
                        full
                        icon="fad fa-save"
                        :label="trans('Save')"
                        @click="submitCategoryOffer"
                        :isLoading="isLoadingSubmit"
                    >
                    </Button>
                </div>


            </div>
        </Modal>
    </div>
</template>
