<script setup lang="ts">

import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { ref } from 'vue'
import { DatePicker, InputNumber } from 'primevue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'
import PureInput from '../Pure/PureInput.vue'
import InformationIcon from '../Utils/InformationIcon.vue'
import Toggle from '../Pure/Toggle.vue'

const props = defineProps<{
    shop_data: {
        slug: string
        currency_code: string
    }
}>()

const isOpenModal = ref(false)

const offerLabel = ref('')
const offerAmount = ref<number | null>(0)
const discountPercentage = ref<number | null>(null)
const startDate = ref(null)
const endDate = ref(null)
const reuseCustomer = ref(false)

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
            type: 'amount',
            offer_amount: offerAmount.value,
            discount_percentage: discountPercentage.value,
            start_date: startDate.value,
            end_date: endDate.value,
            reuse_customer: reuseCustomer.value,
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
    offerAmount.value = null
    discountPercentage.value = null
}
</script>

<template>
    <div>
        <Button :label="trans('Create Voucher')" @click="isOpenModal = true" icon="fas fa-badge-percent" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="isOpenModal = false">
            <div class="px-6">
                <h2 class="text-2xl font-bold mxb-4 text-center">{{ trans('Create Voucher') }}</h2>
                <div class="mt-8 space-y-8">

                    <!-- offer name -->
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

                    <!-- amount -->
                    <div>
                        <label class="font-medium mb-2 flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk"
                                class="font-light text-xs text-red-400 align-middle" />
                            {{ trans('Minimum purchase amount') }}:
                        </label>

                        <div class="pl-4">
                            <InputNumber v-model="offerAmount" inputId="offer_amount" class="w-full" mode="currency"
                                currency="SEK" locale="en-US" :placeholder="trans('Enter minimum amount')" />
                        </div>
                    </div>

                    <!-- Discount -->
                    <!-- <div>
                        <label class="font-medium mb-2 flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk"
                                class="font-light text-xs text-red-400 align-middle" />
                            {{ trans('Discount percentage') }}:
                        </label>

                        <div class="pl-4">
                            <InputNumber v-model="discountPercentage" inputId="offer_discount"
                                :placeholder="trans('Enter discount percentage')" suffix="%" class="w-full" :min="0"
                                :max="100" />
                        </div>
                    </div> -->

                    <!-- Start date - end date -->
                    <div class="grid grid-cols-2 gap-x-6 ">
                        <div>
                            <label class="font-medium mb-2 flex items-center gap-x-1">
                                {{ trans('Start date') }} <InformationIcon :information="trans('If start date is empty, will start immediately')" />:
                            </label>
                            <div class="pl-4">
                                <DatePicker v-model="startDate" showButtonBar showIcon />
                            </div>
                        </div>

                        <div>
                            <label class="font-medium mb-2 flex items-center gap-x-1">
                                {{ trans('End date') }} <InformationIcon :information="trans('If end date is empty, will treat as permanent')" />:
                            </label>
                            <div class="pl-4">
                                <DatePicker v-model="endDate" showButtonBar showIcon />
                            </div>
                        </div>
                    </div>

                    
                    <!-- Discount -->
                    <div>
                        <label class="font-medium mb-2 flex items-center gap-x-1">
                            {{ trans('Can customers reuse the voucher') }}?
                        </label>

                        <div class="pl-4">
                            <Toggle v-model="reuseCustomer" />
                        </div>
                    </div>

                </div>

                <div class="pl-4 mt-8 flex justify-end gap-x-4">
                    <Button @click="isOpenModal = false" type="cancel" />
                    <Button full icon="fad fa-save" :label="trans('Save')" @click="submitCategoryOffer"
                        :isLoading="isLoadingSubmit"
                        :disabled="
                            !offerLabel
                            || !discountPercentage
                            || (!offerAmount)
                        "
                    />
                </div>

            </div>
        </Modal>
    </div>
</template>
