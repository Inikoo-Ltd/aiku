<script setup lang="ts">

import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { ref, computed, nextTick, watch } from 'vue'
import PureMultiselectInfiniteScroll from '../Pure/PureMultiselectInfiniteScroll.vue'
import { InputNumber, RadioButton, DatePicker, Checkbox } from 'primevue'
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
const discountPercentage = ref<number | null>(null)
const allowanceType = ref<'percentage' | 'free'>('percentage')
const freeQuantity = ref<number | null>(1)
const freeProductId = ref<number | null>(null)
const selectedFreeProduct = ref<any | null>(null)
const freeSameAsProduct = ref(true)
const selectedProduct = ref<any | null>(null)
const dateType = ref<'permanent' | 'interval'>('permanent')
const startDate = ref<Date | null>(today)
const endDate = ref<Date | null>(null)
const typeOffer = ref('quantity')
const offerQtyItems = ref<number | null>(1)

const quickIntervalDays = ref<number | null>(null)

const quickIntervalPresets = [1, 2, 3, 7]

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

function formatDate(date: Date | null) {
    if (!date) return null

    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

const isLoadingSubmit = ref(false)

const buildAllowancePayload = () => {
    if (allowanceType.value === 'percentage') {
        return {
            percentage_off: discountPercentage.value != null ? discountPercentage.value / 100 : null,
        }
    }

    return {
        free_quantity: freeQuantity.value != null ? Math.floor(freeQuantity.value) : null,
        free_product_id: freeSameAsProduct.value
            ? (productId.value || props.product_id)
            : freeProductId.value,
    }
}

const submitGiftOffer = () => {
    isLoadingSubmit.value = true
    const payload = {
            name: offerLabel.value,
            type: typeOffer.value,
            trigger_data_item_quantity: offerQtyItems.value != null ? Math.floor(offerQtyItems.value) : null,
            trigger_data_item_amount: offerAmount.value,
            ...buildAllowancePayload(),
            product_id: productId.value || props.product_id,
            quantity: quantity.value,            
            duration: dateType.value,
            start_at: formatDate(startDate.value),
            end_at: dateType.value === 'interval' ? formatDate(endDate.value) : null
    }
    axios.post(
        route('grp.models.gift_offer.store', {
            shop: props.shop_data.id,
        }), 
        payload
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
    name: 'grp.json.shop.products_including_not_for_sale',
    parameters: {
        shop: (route().params as any).shop
    }
}

const selectedProductImage = computed(() =>
    selectedProduct.value?.web_images?.main?.original || null
)

const freeProductImage = computed(() =>
    selectedFreeProduct.value?.web_images?.main?.original || null
)

const resetForm = () => {
    offerLabel.value = ''
    offerAmount.value = 0
    discountPercentage.value = null
    allowanceType.value = 'percentage'
    freeQuantity.value = 1
    freeProductId.value = null
    selectedFreeProduct.value = null
    freeSameAsProduct.value = true
    typeOffer.value = 'quantity'
    offerQtyItems.value = 1
    productId.value = props.product_id || null
    selectedProduct.value = null
    quantity.value = 1
    dateType.value = 'permanent'
    startDate.value = today
    endDate.value = null
    quickIntervalDays.value = null
    isLoadingSubmit.value = false
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

watch(typeOffer, (val) => {
    if (val === 'quantity') {
        offerAmount.value = 0
    } else if (val === 'amount') {
        offerQtyItems.value = 1
    }
})

watch(freeSameAsProduct, (isSame) => {
    if (isSame) {
        freeProductId.value = null
        selectedFreeProduct.value = null
    }
})

const isFormInvalid = computed(() => {
    if (!offerLabel.value) return true
    if (!productId.value && !props.product_id) return true
    if (!quantity.value) return true
    if (!dateType.value) return true
    if (allowanceType.value === 'percentage' && !discountPercentage.value) return true
    if (allowanceType.value === 'free') {
        if (!freeQuantity.value) return true
        if (!freeSameAsProduct.value && !freeProductId.value) return true
    }
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
                        <template #singlelabel>
                            <div class="w-full text-left pl-4 leading-4 truncate mr-2">
                                {{ selectedProduct?.code }}
                                <span class="text-sm text-gray-400">({{ selectedProduct?.name }})</span>
                                <span class="text-sm text-gray-400"> · {{ trans('Stock') }}: {{ selectedProduct?.stock ?? 0 }}</span>
                            </div>
                        </template>

                        <template #option="{ option, isSelected }">
                            <div class="flex w-full items-center justify-between gap-x-2">
                                <div>
                                    {{ option.code }}
                                    <span class="text-sm"
                                        :class="isSelected(option) ? 'text-indigo-200' : 'text-gray-400'">({{ option.name }})</span>
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

                <!-- Section: Allowance -->
                <div class="space-y-3">
                    <div class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Allowance') }}:
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <label for="allowance-percentage"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                            :class="allowanceType === 'percentage'
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            <RadioButton v-model="allowanceType" inputId="allowance-percentage" value="percentage" />
                            <span>{{ trans('Percentage (%)') }}</span>
                        </label>

                        <label for="allowance-free"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                            :class="allowanceType === 'free'
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            <RadioButton v-model="allowanceType" inputId="allowance-free" value="free" />
                            <span>{{ trans('Get free items') }}</span>
                        </label>
                    </div>

                    <div v-if="allowanceType === 'percentage'">
                        <InputNumber v-model="discountPercentage" inputId="offer_discount"
                            :placeholder="trans('Enter percentage')" suffix="%" :min="0" :max="100" class="w-full" />
                    </div>

                    <div v-else class="space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span>{{ trans('Get') }}</span>
                            <InputNumber v-model="freeQuantity" inputId="free_quantity" :min="1" class="w-40"
                                inputClass="w-full"
                                :suffix="' ' + ((freeQuantity ?? 0) > 1 ? trans('items') : trans('item'))" />
                            <span>{{ trans('for free') }}</span>
                        </div>

                        <label for="free_same_product" class="flex w-fit items-center gap-2 cursor-pointer">
                            <Checkbox v-model="freeSameAsProduct" :binary="true" inputId="free_same_product" />
                            <span>{{ trans('Same as the offer product') }}</span>
                        </label>

                        <div v-if="!freeSameAsProduct" class="space-y-2">
                            <label class="font-medium flex items-center gap-x-1">
                                <FontAwesomeIcon icon="fas fa-asterisk"
                                    class="font-light text-xs text-red-400 align-middle" />
                                {{ trans('Select free product') }}:
                            </label>

                            <PureMultiselectInfiniteScroll v-model="freeProductId" :fetchRoute="productFetchRoute"
                                labelProp="name" :placeholder="trans('Select free product')" valueProp="id"
                                mode="single" @selectedObject="(product) => selectedFreeProduct = product">
                                <template #singlelabel="{ value }">
                                    <div class="w-full text-left pl-4 leading-4 truncate mr-2">
                                        {{ value.code }}
                                        <span class="text-sm text-gray-400">({{ value.name }})</span>
                                        <span class="text-sm text-gray-400"> · {{ trans('Stock') }}: {{ value.stock ?? 0 }}</span>
                                    </div>
                                </template>

                                <template #option="{ option, isSelected }">
                                    <div class="flex w-full items-center justify-between gap-x-2">
                                        <div>
                                            {{ option.code }}
                                            <span class="text-sm"
                                                :class="isSelected(option) ? 'text-indigo-200' : 'text-gray-400'">({{ option.name }})</span>
                                        </div>
                                        <span class="text-sm whitespace-nowrap"
                                            :class="isSelected(option) ? 'text-indigo-200' : 'text-gray-400'">
                                            {{ trans('Stock') }}: {{ option.stock ?? 0 }}
                                        </span>
                                    </div>
                                </template>
                            </PureMultiselectInfiniteScroll>

                            <div v-if="freeProductImage"
                                class="h-24 rounded-lg border border-gray-200 shadow-sm flex items-center justify-center">
                                <Image :src="freeProductImage" alt="Free product image" object-cover />
                            </div>
                        </div>
                    </div>
                </div>
             
                <!-- Section: Offer Duration -->
                <div class="space-y-3">

                    <div class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Offer Duration') }}:
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
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
                        <button v-if="dateType === 'interval'" v-for="days in quickIntervalPresets" :key="days" type="button"
                            @click="applyQuickInterval(days)"
                            class="px-3.5 py-2.5 rounded-lg border text-sm cursor-pointer transition-colors"
                            :class="quickIntervalDays === days
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            {{ trans(':count day', { count: String(days) }) }}
                        </button>
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
