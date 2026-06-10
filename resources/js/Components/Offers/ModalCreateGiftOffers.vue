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
import Image from '../../Common/Components/Image.vue'
import axios from 'axios'

const props = defineProps<{
    shop_data: {
        id: number
        slug?: string
        organisation?: string
        offercampaign?: string        
        currency_code: string
        default_dates: {
            start: string
            end: string
        }
    }
    product_id?: number
}>()

const today = new Date(new Date().setHours(0, 0, 0, 0))
const isOpenModal = ref(false)
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

const offerLabel = ref('')
const offerAmount = ref<number | null>(0)
const quantity = ref<number | null>(1)
const productId = ref<number | null>(0)
const selectedProduct = ref<any | null>(null)
const dateType = ref<'permanent' | 'interval'>('permanent')
const startDate = ref<Date | null>(today)
const endDate = ref<Date | null>(null)


function formatDate(date: Date | null) {
    if (!date) return null

    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

const isLoadingSubmit = ref(false)
const submitGiftOffer = () => {
    // Section: Submit
    isLoadingSubmit.value = true

    axios.post(
        route('grp.models.gift_offer.store', {
            shop: props.shop_data.id,
        }),
        {
            name: offerLabel.value,
            product_id: productId.value || props.product_id,
            quantity: quantity.value,
            min_order_amount: offerAmount.value,
            duration: dateType.value,
            start_at: formatDate(startDate.value),
            end_at: formatDate(endDate.value)
        }
    )
        .then((response) => {
            notify({
                title: trans("Success"),
                text: trans("Successfully submit the data"),
                type: "success"
            })
            resetForm()
            isOpenModal.value = false
            if (!props.product_id) {
                router.visit(route('grp.org.shops.show.discounts.campaigns.offer.show', {
                    organisation: props.shop_data.organisation,
                    shop: props.shop_data.slug,
                    offerCampaign: props.shop_data.offercampaign,
                    offer: response.data.slug
                }))
            }
            router.reload()
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

const productFetchRoute = {
    name: 'grp.json.shop.products',
    parameters: {
        shop: (route().params as any).shop
    }
}

const selectedProductImage = computed(() =>
    selectedProduct.value?.web_images?.main?.original || null
)

const resetForm = () => {
    offerLabel.value = ''
    offerAmount.value = 0
    productId.value = props.product_id || null
    selectedProduct.value = null
    quantity.value = 1
    dateType.value = 'permanent'
    startDate.value = today
    endDate.value = null
}

const isFormInvalid = computed(() => {
    if (!offerLabel.value) return true
    if (!productId.value && !props.product_id) return true
    if (!quantity.value) return true
    if (!dateType.value) return true
    if (!startDate.value) return true
    if (dateType.value === 'interval' && !endDate.value) return true
    return false
})
resetForm()
</script>

<template>
    <div>
        <Button :label="trans('Create Gift Offer')" @click="openModal" icon="fas fa-badge-percent" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="closeModal">
            <div class="p-1 space-y-3">
                <h2 class="text-2xl font-bold mb-4 text-center">{{ trans('Create Gift Offer') }}</h2>

                <div class="space-y-2">
                    <label for="amount" class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Offer name') }}:
                    </label>

                    <PureInput v-model="offerLabel" :placeholder="trans('Enter offer name')" />
                </div>

                <div class="space-y-2" v-if="!props.product_id">
                    <label for="amount" class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />

                        {{ trans('Select product') }}:
                    </label>
                    <PureMultiselectInfiniteScroll v-model="productId" :fetchRoute="productFetchRoute"
                        labelProp="name" placeholder="Select product" valueProp="id" :required="true" mode="single"
                        @selectedObject="(product) => selectedProduct = product">
                        <template #singlelabel="{ value }">
                            <div class="w-full text-left pl-4 leading-4 truncate mr-2">
                                {{ value.name }}
                                <span v-if="value.code" class="text-sm text-gray-400">({{ value.code }})</span>
                                <span class="text-sm text-gray-400"> · {{ trans('Stock') }}: {{ value.stock ?? 0 }}</span>
                            </div>
                        </template>

                        <template #option="{ option, isSelected }">
                            <div class="flex w-full items-center justify-between gap-x-2">
                                <div>
                                    {{ option.name }}
                                    <span v-if="option.code" class="text-sm"
                                        :class="isSelected(option) ? 'text-indigo-200' : 'text-gray-400'">({{ option.code }})</span>
                                </div>
                                <span class="text-sm whitespace-nowrap"
                                    :class="isSelected(option) ? 'text-indigo-200' : 'text-gray-400'">
                                    {{ trans('Stock') }}: {{ option.stock ?? 0 }}
                                </span>
                            </div>
                        </template>
                    </PureMultiselectInfiniteScroll>
                </div>
                <div class="space-y-2" v-if="selectedProductImage">
                    <!-- Product Image -->
                    <div class="h-24 rounded-lg border border-gray-200 shadow-sm flex items-center justify-center ">
                        <Image :src="selectedProductImage" alt="Product image" object-cover />
                    </div>
                </div>
                 <div>
                    <div class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk"
                            class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Quantity') }}:
                    </div>

                    <InputNumber v-model="quantity" inputId="offer_discount"
                        :placeholder="trans('Enter quantity')" :min="1" class="w-full" />

                </div>

                <div class="space-y-2">
                    <label class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk"
                            class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Minimum purchase amount') }}:
                    </label>
                    <InputNumber v-model="offerAmount" inputId="offer_amount" class="w-full" mode="currency"
                        :currency="props.shop_data.currency_code" locale="en-US"
                        :placeholder="trans('Enter minimum amount')" />
                </div>
             
                <!-- Section: Offer Duration -->
                <div class="space-y-3">

                    <div class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Offer Duration') }}:
                    </div>

                    <div class="flex flex-wrap gap-4">
                        <label for="permanent"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                            :class="dateType === 'permanent'
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            <RadioButton v-model="dateType" inputId="permanent" value="permanent" />
                            <span>{{ trans('Permanent') }}</span>
                        </label>

                        <label for="interval"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                            :class="dateType === 'interval'
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            <RadioButton v-model="dateType" inputId="interval" value="interval" />
                            <span>{{ trans('Interval') }}</span>
                        </label>
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

                            <DatePicker v-model="startDate" :minDate="today" showIcon dateFormat="yy-mm-dd" class="w-full"
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
                                :minDate="startDate || undefined" :placeholder="trans('Select end date')" />
                        </div>
                    </div>

                </div>


                <div class="mt-8 flex justify-end gap-x-4">
                    <Button @click="closeModal" type="cancel" />
                    <Button full icon="fad fa-save" :label="isLoadingSubmit ? trans('Loading') : trans('Save')" @click="submitGiftOffer"
                        :loading="isLoadingSubmit" :disabled="isFormInvalid || isLoadingSubmit">
                    </Button>
                </div>
            </div>
        </Modal>
    </div>
</template>
