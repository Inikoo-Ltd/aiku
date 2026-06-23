<script setup lang="ts">

import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { ref, computed } from "vue"
import { DatePicker, InputNumber } from "primevue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { router } from "@inertiajs/vue3"
import PureInput from "../Pure/PureInput.vue"
import InformationIcon from "../Utils/InformationIcon.vue"
import axios from "axios"

const props = defineProps<{
    shop_data: {
        id: number
        slug: string
        currency_code: string
        organisation?: string
        offercampaign?: string
        default_dates: {
            start: string
            end: string
        }
    }
}>()

const isOpenModal = ref(false)

const offerLabel = ref("")
const offerAmount = ref<number | null>(0)

const isLoadingSubmit = ref(false)
const submitShippingOffer = () => {
    isLoadingSubmit.value = true
    // Section: Submit
    const payload = {
        name: offerLabel.value,
        min_order_amount: offerAmount.value,
        start_at: formatDate(startDate.value),
        end_at: formatDate(endDate.value)
    }

    axios.post(
        route("grp.models.shipping_offer.store", {
            shop: props.shop_data.id
        }),
        payload
    )
    .then((response) => {
        notify({
            title: trans("Success"),
            text: trans("Successfully submit the data"),
            type: "success"
        })
        resetForm();
        isOpenModal.value = false

        router.visit(route('grp.org.shops.show.discounts.campaigns.offer.show', {
            organisation: props.shop_data.organisation,
            shop: props.shop_data.slug,
            offerCampaign: props.shop_data.offercampaign,
            offer: response.data.slug
        }))
        router.reload()
    })
    .catch((error) => {
        const errors = error.response?.data?.errors || {}
        const errMsg = Object.values(errors).join('. ') || trans("Failed to submit the data, please try again");
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

const startDate = ref<Date | null>(
    props.shop_data.default_dates?.start
        ? new Date(props.shop_data.default_dates.start)
        : null
)

const endDate = ref<Date | null>(
    props.shop_data.default_dates?.end
        ? new Date(props.shop_data.default_dates.end)
        : null
)

const resetForm = () => {
    offerLabel.value = ""
    offerAmount.value = 0
    startDate.value = null
    endDate.value = null
    isLoadingSubmit.value = false
}

const openModal = () => {
    startDate.value = new Date(props.shop_data.default_dates.start)
    endDate.value = new Date(props.shop_data.default_dates.end)
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
    if (!offerLabel.value) return true
    if (offerAmount.value === null || offerAmount.value === undefined || offerAmount.value < 0) return true
    if (!endDate.value) return true
    if (startDate.value && endDate.value < startDate.value) return true
    return false
})
</script>

<template>
    <div>
        <Button :label="trans('Create Discount Shipping')" @click="openModal" icon="fas fa-badge-percent" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="closeModal">
            <div class="px-6">
                <h2 class="text-2xl font-bold mxb-4 text-center">{{ trans("Create Discount Shipping") }}</h2>
                <div class="mt-8 space-y-8">

                    <!-- offer name -->
                    <div>
                        <label for="amount" class="font-medium mb-2 flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk"
                                             class="font-light text-xs text-red-400 align-middle" />

                            {{ trans("Offer name") }}:
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
                            {{ trans("Minimum purchase amount") }}:
                        </label>

                        <div class="pl-4">
                            <InputNumber v-model="offerAmount" inputId="offer_amount" class="w-full" mode="currency"
                                         :currency="props.shop_data.currency_code" locale="en-US"
                                         :placeholder="trans('Enter minimum amount')" />
                        </div>
                    </div>

                    <!-- Start date - end date -->
                    <div class="grid grid-cols-2 gap-x-6 ">
                        <div>
                            <label class="font-medium mb-2 flex items-center gap-x-1">
                                <FontAwesomeIcon icon="fas fa-asterisk"
                                                 class="font-light text-xs text-red-400 align-middle" />
                                {{ trans("Start date") }}
                                <InformationIcon
                                    :information="trans('If start date is empty, will start immediately')" />
                                :
                            </label>
                            <div class="pl-4">
                                <DatePicker v-model="startDate" showButtonBar showIcon />
                            </div>
                        </div>

                        <div>
                            <label class="font-medium mb-2 flex items-center gap-x-1">
                                <FontAwesomeIcon icon="fas fa-asterisk"
                                                 class="font-light text-xs text-red-400 align-middle" />
                                {{ trans("End date") }}
                                <InformationIcon
                                    :information="trans('If end date is empty, will treat as permanent')" />
                                :
                            </label>
                            <div class="pl-4">
                                <DatePicker v-model="endDate" showButtonBar showIcon :minDate="startDate ?? undefined"/>
                            </div>
                            <p v-if="startDate && endDate && endDate < startDate" class="text-red-500 text-sm">
                                End date must be after start date
                            </p>
                        </div>
                    </div>

                </div>

                <div class="pl-4 mt-8 flex justify-end gap-x-4">
                    <Button @click="closeModal" type="cancel" />
                    <Button full icon="fad fa-save" :label="isLoadingSubmit ? trans('Loading') : trans('Save')" @click="submitShippingOffer"
                            :loading="isLoadingSubmit" :disabled="isFormInvalid || isLoadingSubmit" />
                </div>

            </div>
        </Modal>
    </div>
</template>
