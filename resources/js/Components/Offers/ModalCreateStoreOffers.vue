<script setup lang="ts">

import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { ref } from 'vue'
import { InputNumber } from 'primevue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'
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
const offerAmount = ref<number | null>(0)
const discountPercentage = ref<number | null>(null)

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

const resetForm = () => {
    offerLabel.value = ''
    offerAmount.value = null
    discountPercentage.value = null
}
</script>

<template>
    <div>
        <Button :label="trans('Create Category Offer')" @click="isOpenModal = true" icon="fas fa-badge-percent" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="isOpenModal = false">
            <div class="px-6">
                <h2 class="text-2xl font-bold mxb-4 text-center">{{ trans('Create Category Offer') }}</h2>
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
                    <div>
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
                    </div>

                </div>

                <div class="pl-4 mt-8 flex justify-end gap-x-4">
                    <Button @click="isOpenModal = false" type="cancel" />
                    <Button full icon="fad fa-save" :label="trans('Save')" @click="submitCategoryOffer"
                        :isLoading="isLoadingSubmit" :disabled="!offerLabel || !discountPercentage || (!offerAmount)">
                    </Button>
                </div>

            </div>
        </Modal>
    </div>
</template>
