<script setup lang="ts">

import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { ref, computed, watch, nextTick } from 'vue'
import PureMultiselectInfiniteScroll from '../Pure/PureMultiselectInfiniteScroll.vue'
import { InputNumber, InputText, RadioButton, DatePicker, Select } from 'primevue'
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { ctrans } from '@/Composables/useTrans'
import InformationIcon from '../Utils/InformationIcon.vue'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'
import PureInput from '../Pure/PureInput.vue'
import axios from 'axios'
import {
    faSpinner, faExclamationTriangle
} from "@fas";
library.add(
    faSpinner, faExclamationTriangle
);

const props = defineProps<{
    shop_data: {
        id: number
        slug: string
        currency_code: string
        organisation?: string
        offercampaign?: string
        default_dates?: {
            start: string            
        }
    }
    product_category_id?: number
}>()

const isOpenModal = ref(false)
const openModal = () => {
    resetForm()
    startDate.value = props.shop_data.default_dates?.start ? new Date(props.shop_data.default_dates.start) : today
    isOpenModal.value = true
}
const closeModal = () => {
    isOpenModal.value = false
    resetForm()
}
const offerLabel = ref('')
const typeOffer = ref('quantity')
const offerQtyItems = ref<number | null>(1)
const offerAmount = ref<number | null>(0)
const discountPercentage = ref<number | null>(null)
const offerCategories = ref<{ id: number, name: string }[]>([])
const categoryType = ref<'department' | 'subdepartment' | 'family'>('department')
const discountTarget = ref<'same' | 'other'>('same')
const isCombinedOffer = ref(true)
const targetCategoryId = ref<number | null>(null)
const isLoadingSubmit = ref(false)
const dateType = ref<'permanent' | 'interval'>('permanent')
const startDate = ref<Date | null>(null)
const endDate = ref<Date | null>(null)
const quickIntervalDays = ref<number | null>(null)

const quickIntervalPresets = [1, 2, 3, 7]

const discountMode = ref<'percentage' | 'free_stock'>('percentage')
const freeQuantity = ref<number | null>(null)
const freeProductId = ref<number | null>(null)
const discontinuingProducts = ref<{ id: number, label: string }[]>([])
const isLoadingDiscontinuing = ref(false)

const selectedFamilyId = computed<number | null>(() => {
    if (props.product_category_id) return props.product_category_id
    if (categoryType.value === 'family' && offerCategories.value.length === 1) return offerCategories.value[0].id
    return null
})

const canOfferFreeStock = computed(() => !!selectedFamilyId.value && typeOffer.value === 'quantity')
const isFreeStock = computed(() => canOfferFreeStock.value && discountMode.value === 'free_stock')

const responsibilityPhrase = 'I accept responsibility'
const acceptResponsibility = ref('')
const isGivingAwayTooMuch = computed(() => isFreeStock.value && !!freeQuantity.value && (offerQtyItems.value ?? 0) <= freeQuantity.value)
const hasAcceptedResponsibility = computed(() => acceptResponsibility.value.trim().toLowerCase() === responsibilityPhrase.toLowerCase())

watch([isFreeStock, selectedFamilyId], () => {
    freeProductId.value = null
    discontinuingProducts.value = []
    if (!isFreeStock.value || !selectedFamilyId.value) return

    isLoadingDiscontinuing.value = true
    axios.get(route('grp.json.product_category.discontinuing_products.index', { productCategory: selectedFamilyId.value }))
        .then((response) => {
            discontinuingProducts.value = response.data
        })
        .finally(() => {
            isLoadingDiscontinuing.value = false
        })
})

const categoryRoutes = computed(() => ({
    department: {
        name: 'grp.json.shop.departments',
        parameters: { shop: props.shop_data.slug }
    },
    subdepartment: {
        name: 'grp.json.shop.sub_departments',
        parameters: { shop: props.shop_data.id }
    },
    family: {
        name: 'grp.json.shop.families',
        parameters: { shop: props.shop_data.id }
    }
}))

const activeCategoryRoute = computed(() => categoryRoutes.value[categoryType.value])

const submitCategoryOffer = () => {
    // Section: Submit
    isLoadingSubmit.value = true
    
    axios.post(
        route('grp.models.category_offer.store', {
            shop: props.shop_data.id,
        }),
        {
            name: offerLabel.value,
            type: typeOffer.value,
            product_category_ids: props.product_category_id
                ? [props.product_category_id]
                : offerCategories.value.map((category) => category.id),
            trigger_data_item_quantity: offerQtyItems.value != null ? Math.floor(offerQtyItems.value) : null,
            trigger_data_item_amount: offerAmount.value,
            percentage_off: !isFreeStock.value && discountPercentage.value != null ? discountPercentage.value / 100 : null,
            free_quantity: isFreeStock.value ? freeQuantity.value : null,
            free_product_id: isFreeStock.value ? freeProductId.value : null,
            accept_responsibility: isGivingAwayTooMuch.value ? acceptResponsibility.value : null,
            target_product_category_id: !isFreeStock.value && discountTarget.value === 'other' ? targetCategoryId.value : null,
            combine: isCombinedOffer.value,
            duration: dateType.value,
            start_at: formatDate(startDate.value),
            end_at: dateType.value === 'interval' ? formatDate(endDate.value) : null
        }
    )
    .then((response) => {
        const { url, created, skipped } = response.data

        if (!created) {
            notify({
                title: ctrans("Something went wrong"),
                text: ctrans("No offer was created, the selected categories already have an active offer"),
                type: "error"
            })
            return
        }

        notify({
            title: ctrans("Success"),
            text: skipped
                ? ctrans("Created :created offers, skipped :skipped (already have an active offer)", { created: String(created), skipped: String(skipped) })
                : ctrans("Successfully submit the data"),
            type: skipped ? "warning" : "success"
        })
        resetForm();
        isOpenModal.value = false

        router.visit(url)
    })
    .catch((error) => {
        const errors = error.response?.data?.errors || {}
        const errMsg = Object.values(errors).join('. ') || ctrans("Failed to submit the data, please try again");
        notify({
            title: ctrans("Something went wrong"),
            text: errMsg,
            type: "error"
        })
    })
    .finally(() => {
        isLoadingSubmit.value = false
    })
}
const today = new Date(new Date().setHours(0, 0, 0, 0))

function formatDate(date: Date | null) {
    if (!date) return null

    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

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

const resetForm = () => {
    offerLabel.value = ''
    typeOffer.value = 'quantity'
    discountPercentage.value = null
    discountMode.value = 'percentage'
    freeQuantity.value = null
    freeProductId.value = null
    acceptResponsibility.value = ''
    offerQtyItems.value = 1
    offerAmount.value = 0
    categoryType.value = 'department'
    offerCategories.value = []
    discountTarget.value = 'same'
    isCombinedOffer.value = true
    targetCategoryId.value = null
    dateType.value = 'permanent'
    startDate.value = null
    endDate.value = null
    quickIntervalDays.value = null
}

const isFormInvalid = computed(() => {
    if (!props.product_category_id && !offerCategories.value.length) return true

    if (!offerLabel.value) return true

    if (isFreeStock.value) {
        if (!freeQuantity.value || !discontinuingProducts.value.length) return true
        if (isGivingAwayTooMuch.value && !hasAcceptedResponsibility.value) return true
    } else {
        if (!discountPercentage.value) return true

        if (discountTarget.value === 'other' && !targetCategoryId.value) return true
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
        quickIntervalDays.value = null
    }
})

watch([startDate, endDate], () => {
    if (!isApplyingPreset) {
        quickIntervalDays.value = null
    }
})

watch(categoryType, () => {
    if (!props.product_category_id) {
        offerCategories.value = []
    }
})

resetForm();

</script>

<template>
    <div>
        <Button :label="ctrans('Create Category Offer')" @click="openModal" icon="fas fa-badge-percent" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="closeModal">
            <div class="p-1 space-y-3">
                <h2 class="text-2xl font-bold mb-4 text-center">{{ ctrans('Create Category Offer') }}</h2>

                <div class="space-y-2">
                    <label for="amount" class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" fixed-width />

                        {{ ctrans('Offer name') }}:
                    </label>


                    <PureInput v-model="offerLabel" :placeholder="ctrans('Enter offer name')" />

                </div>

                <div class="space-y-2" v-if="!product_category_id">
                    <label for="amount" class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" fixed-width />

                        {{ ctrans('Select categories') }}:
                        <InformationIcon :information="ctrans('You can select more than one, and choose whether they count together in one offer or get an offer each')" />
                    </label>

                    <div class="flex gap-4">
                        <div class="flex items-center gap-2">
                            <label for="category-type-department" class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors" :class="categoryType ==='department' ? 'border-green-500 bg-green-50 text-green-700 font-semibold': 'border-gray-200 hover:border-gray-300'">
                                <RadioButton v-model="categoryType" value="department" inputId="category-type-department" />
                                <span>{{ ctrans('Department') }}</span>
                            </label>
                        </div>

                        <div class="flex items-center gap-2">
                            <label for="category-type-subdepartment" class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors" :class="categoryType ==='subdepartment' ? 'border-green-500 bg-green-50 text-green-700 font-semibold': 'border-gray-200 hover:border-gray-300'">
                                <RadioButton v-model="categoryType" value="subdepartment" inputId="category-type-subdepartment" />
                                <span>{{ ctrans('Sub Department') }}</span>
                            </label>
                        </div>

                        <div class="flex items-center gap-2">
                            <label for="category-type-family" class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors" :class="categoryType ==='family' ? 'border-green-500 bg-green-50 text-green-700 font-semibold': 'border-gray-200 hover:border-gray-300'">
                                <RadioButton v-model="categoryType" value="family" inputId="category-type-family" />
                                <span>{{ ctrans('Family') }}</span>
                            </label>
                        </div>
                    </div>

                    <PureMultiselectInfiniteScroll
                        :key="categoryType"
                        v-model="offerCategories"
                        :fetchRoute="activeCategoryRoute"
                        mode="tags"
                        :object="true"
                        :placeholder="ctrans('Select one or more categories from the list')"
                        valueProp="id"
                        labelProp="name" />

                    <div v-if="offerCategories.length > 1" class="flex flex-col gap-2 pt-1">
                        <div class="flex items-center gap-2">
                            <RadioButton v-model="isCombinedOffer" :value="true" inputId="category-offer-combined" size="small" />
                            <label for="category-offer-combined" class="cursor-pointer">
                                {{ ctrans('One offer: the selected categories count together') }}
                            </label>
                        </div>
                        <div class="flex items-center gap-2">
                            <RadioButton v-model="isCombinedOffer" :value="false" inputId="category-offer-separate" size="small" />
                            <label for="category-offer-separate" class="cursor-pointer">
                                {{ ctrans('One offer per category') }}
                            </label>
                        </div>
                    </div>

                </div>

                <div class="space-y-2">
                    <div class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" fixed-width />
                        {{ ctrans('Select offer type') }}:
                    </div>

                    <div class="flex items-stretch gap-x-8">
                        <div class="space-y-2 flex-1">
                            <div class="flex items-center gap-2">
                                <RadioButton v-model="typeOffer" inputId="type-quantity" name="quantity"
                                    value="quantity" size="small" />
                                <label for="type-quantity" class="cursor-pointer">
                                    {{ ctrans('By quantity') }}
                                    <InformationIcon :information="ctrans('Total quantities of the items')" />
                                </label>
                            </div>
                            <div class="min-h-[40px]">
                            <InputNumber v-model="offerQtyItems" v-show="typeOffer === 'quantity'"  fluid
                                inputId="offer_quantity_item" :placeholder="ctrans('Enter minimum quantity')"
                                :disabled="typeOffer !== 'quantity'" :min="0" class="w-full" inputClass="w-full"
                                :suffix="' ' + ((offerQtyItems ?? 0) > 1 ? ctrans('items') : ctrans('item'))" />
                            </div>
                        </div>

                        <div class="space-y-2 flex-1">
                            <div class="flex items-center gap-2">
                                <RadioButton v-model="typeOffer" inputId="type-amount" name="amount" value="amount"
                                    size="small" />
                                <label for="type-amount" class="cursor-pointer">{{ ctrans('By minimum amount')
                                    }}</label>
                            </div>
                            <div class="min-h-[40px]">
                            <InputNumber v-show="typeOffer === 'amount'" v-model="offerAmount"   fluid inputId="offer_amount" mode="currency" inputClass="w-full" :placeholder="ctrans('Enter minimum amount')" 
                                :currency="props.shop_data.currency_code" locale="en-US" class="w-full"
                                :disabled="typeOffer !== 'amount'" />
                                </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Discount -->
                <div class="space-y-2">
                    <div class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" fixed-width />
                        {{ ctrans('Discount') }}:
                    </div>


                    <div v-if="canOfferFreeStock" class="flex flex-wrap items-center gap-3">
                        <label for="discount-mode-percentage"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                            :class="discountMode === 'percentage'
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            <RadioButton v-model="discountMode" inputId="discount-mode-percentage" value="percentage" />
                            <span>{{ ctrans('Percentage off') }}</span>
                        </label>

                        <label for="discount-mode-free-stock"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                            :class="discountMode === 'free_stock'
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            <RadioButton v-model="discountMode" inputId="discount-mode-free-stock" value="free_stock" />
                            <span>{{ ctrans('Free discontinued stock') }}</span>
                            <InformationIcon :information="ctrans('Gives free items from the discontinued products of this family that still have stock. The offer ends when they are all gone')" />
                        </label>
                    </div>

                    <InputNumber v-if="!isFreeStock" v-model="discountPercentage" inputId="offer_discount"
                        :placeholder="ctrans('Enter percentage')" suffix="%" :min="0" :max="100" class="w-full" />

                    <div v-else class="space-y-2">
                        <InputNumber v-model="freeQuantity" inputId="offer_free_quantity" fluid
                            :placeholder="ctrans('How many free items')" :min="1" class="w-full" inputClass="w-full"
                            :suffix="' ' + ctrans('free')" />

                        <div v-if="isLoadingDiscontinuing" class="text-sm text-gray-500">
                            <FontAwesomeIcon icon="fas fa-spinner" spin fixed-width />
                            {{ ctrans('Loading') }}
                        </div>
                        <div v-else-if="!discontinuingProducts.length" class="text-sm text-red-500">
                            {{ ctrans('This family has no discontinued products with stock') }}
                        </div>
                        <Select v-else v-model="freeProductId" :options="discontinuingProducts" optionLabel="label"
                            optionValue="id" showClear class="w-full"
                            :placeholder="ctrans('Let the system choose, cheapest first')" />

                        <div v-if="isGivingAwayTooMuch" class="rounded-lg border-2 border-red-500 bg-red-50 p-4 space-y-3 text-red-800">
                            <div class="flex items-start gap-x-3">
                                <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="text-3xl text-red-600 mt-1" fixed-width />
                                <div class="space-y-2">
                                    <div class="text-lg font-bold uppercase">{{ ctrans('Warning: you are about to give stock away') }}</div>
                                    <div class="font-semibold">
                                        {{ ctrans('Customers will get :free free products for buying only :quantity from this family. Almost every order with this family will get free products.', { free: String(freeQuantity), quantity: String(offerQtyItems ?? 0) }) }}
                                    </div>
                                    <div>
                                        {{ ctrans('We strongly advise you not to do this. The minimum quantity should be much higher than the free quantity (for example buy 24, get 2 free).') }}
                                    </div>
                                    <div>
                                        {{ ctrans('If you still want to save it, type :phrase below.', { phrase: responsibilityPhrase }) }}
                                    </div>
                                </div>
                            </div>
                            <InputText v-model="acceptResponsibility" fluid :placeholder="responsibilityPhrase"
                                :invalid="!hasAcceptedResponsibility" />
                        </div>
                    </div>

                </div>

                <!-- Section: Discount target -->
                <div v-if="!isFreeStock" class="space-y-2">
                    <div class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" fixed-width />
                        {{ ctrans('Apply discount to') }}:
                        <InformationIcon :information="ctrans('The discount can apply to this category, or to another family (e.g. spend on this category to get a discount on another family)')" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <label for="discount-target-same"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                            :class="discountTarget === 'same'
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            <RadioButton v-model="discountTarget" inputId="discount-target-same" value="same" />
                            <span>{{ ctrans('This category') }}</span>
                        </label>

                        <label for="discount-target-other"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                            :class="discountTarget === 'other'
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            <RadioButton v-model="discountTarget" inputId="discount-target-other" value="other" />
                            <span>{{ ctrans('Another family') }}</span>
                        </label>
                    </div>

                    <PureMultiselectInfiniteScroll v-if="discountTarget === 'other'"
                        v-model="targetCategoryId"
                        :fetchRoute="categoryRoutes.family"
                        required
                        :placeholder="ctrans('Select the family that gets the discount')"
                        valueProp="id"
                        labelProp="name" />
                </div>

                <!-- Section: Offer Duration -->
                <div class="space-y-3">

                    <div class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" fixed-width />
                        {{ ctrans('Offer Duration') }}:
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <label for="permanent"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                            :class="dateType === 'permanent'
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            <RadioButton v-model="dateType" inputId="permanent" value="permanent" />
                            <span>{{ ctrans('Permanent') }}</span>
                        </label>

                        <label for="interval"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                            :class="dateType === 'interval'
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            <RadioButton v-model="dateType" inputId="interval" value="interval" />
                            <span>{{ ctrans('Interval') }}</span>
                        </label>

                        <button v-if="dateType === 'interval'" v-for="days in quickIntervalPresets" :key="days" type="button"
                            @click="applyQuickInterval(days)"
                            class="px-3.5 py-2.5 rounded-lg border text-sm cursor-pointer transition-colors"
                            :class="quickIntervalDays === days
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            {{ ctrans(':count day', { count: String(days) }) }}
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Start Date -->
                        <div class="space-y-2">
                            <label class="font-medium mb-2 block">
                                <FontAwesomeIcon icon="fas fa-asterisk"
                                    class="font-light text-xs text-red-400 align-middle" fixed-width />
                                {{ ctrans('Start Date') }}
                                <InformationIcon
                                    :information="ctrans('If start date is empty, will start immediately')" />:
                            </label>

                            <DatePicker v-model="startDate" :minDate="today" showIcon dateFormat="yy-mm-dd" class="w-full"
                                :placeholder="ctrans('Select start date')" />
                        </div>

                        <!-- End Date (Only for Interval) -->
                        <div v-if="dateType === 'interval'" class="space-y-2">
                            <label class="font-medium mb-2 block">
                                {{ ctrans('End Date') }}
                                <InformationIcon
                                    :information="ctrans('If start date is empty, will start immediately')" />:
                            </label>

                            <DatePicker v-model="endDate" showIcon dateFormat="yy-mm-dd" class="w-full"
                                :minDate="startDate || undefined" :placeholder="ctrans('Select end date')" />
                        </div>
                    </div>

                </div>


                <div class="mt-8 flex justify-end gap-x-4">
                    <Button @click="closeModal" type="cancel" />

                   <Button
                        full
                        icon="fad fa-save"
                        :label="isLoadingSubmit ? ctrans('Loading') : ctrans('Save')"
                        @click="submitCategoryOffer"
                        :disabled="isFormInvalid || isLoadingSubmit"
                        :loading="isLoadingSubmit"
                    />
                </div>
            </div>
        </Modal>
    </div>
</template>
