<script setup lang="ts">

import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { ref, computed, watch } from 'vue'
import PureMultiselectInfiniteScroll from '../Pure/PureMultiselectInfiniteScroll.vue'
import { InputNumber, RadioButton, DatePicker } from 'primevue'
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { trans } from 'laravel-vue-i18n'
import InformationIcon from '../Utils/InformationIcon.vue'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'
import PureInput from '../Pure/PureInput.vue'
import axios from 'axios'
import {
    faSpinner
} from "@fas";
library.add(
    faSpinner
);

const props = defineProps<{
    shop_data: {
        id: number
        slug: string
        currency_code: string
        organisation?: string
        offercampaign?: string
    }
    product_category_id?: number
}>()

const isOpenModal = ref(false)
const offerLabel = ref('')
const typeOffer = ref('quantity')
const offerQtyItems = ref<number | null>(1)
const offerAmount = ref<number | null>(0)
const discountPercentage = ref<number | null>(null)
const offerCategoryId = ref<number | null>(null)
const categoryType = ref<'department' | 'subdepartment' | 'family'>('department')
const isLoadingSubmit = ref(false)
const dateType = ref<'permanent' | 'interval'>('permanent')
const startDate = ref<Date | null>(null)
const endDate = ref<Date | null>(null)

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
            product_category_id: offerCategoryId.value || props.product_category_id,
            trigger_data_item_quantity: offerQtyItems.value != null ? Math.floor(offerQtyItems.value) : null,
            trigger_data_item_amount: offerAmount.value,
            percentage_off: discountPercentage.value != null ? discountPercentage.value / 100 : null,
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
        resetForm();
        isOpenModal.value = false

        if (!props.product_category_id) {
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
    discountPercentage.value = null
    offerQtyItems.value = 1
    offerAmount.value = null
    categoryType.value = 'department'
    offerCategoryId.value = props.product_category_id ?? null
    dateType.value = 'permanent'
    startDate.value = null
    endDate.value = null
}

const isFormInvalid = computed(() => {
    if (!offerCategoryId.value && !props.product_category_id) return true

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
        offerAmount.value = null
    } else if (val === 'amount') {
        offerQtyItems.value = 1
    }
})

watch(dateType, (val) => {
    if (val === 'permanent') {
        endDate.value = null
    }
})

watch(categoryType, () => {
    if (!props.product_category_id) {
        offerCategoryId.value = null
    }
})

resetForm();

</script>

<template>
    <div>
        <Button :label="trans('Create Category Offer')" @click="isOpenModal = true; resetForm();" icon="fas fa-badge-percent" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="isOpenModal = false; resetForm();">
            <div class="p-1 space-y-3">
                <h2 class="text-2xl font-bold mb-4 text-center">{{ trans('Create Category Offer') }}</h2>

                <div class="space-y-2">
                    <label for="amount" class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />

                        {{ trans('Offer name') }}:
                    </label>


                    <PureInput v-model="offerLabel" :placeholder="trans('Enter offer name')" />

                </div>

                <div class="space-y-2" v-if="!product_category_id">
                    <label for="amount" class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />

                        {{ trans('Select category') }}:
                    </label>

                    <div class="flex gap-4">
                        <div class="flex items-center gap-2">
                            <RadioButton v-model="categoryType" value="department" inputId="category-type-department" />
                            <label for="category-type-department">{{ trans('Department') }}</label>
                        </div>

                        <div class="flex items-center gap-2">
                            <RadioButton v-model="categoryType" value="subdepartment" inputId="category-type-subdepartment" />
                            <label for="category-type-subdepartment">{{ trans('Sub Department') }}</label>
                        </div>

                        <div class="flex items-center gap-2">
                            <RadioButton v-model="categoryType" value="family" inputId="category-type-family" />
                            <label for="category-type-family">{{ trans('Family') }}</label>
                        </div>
                    </div>

                    <PureMultiselectInfiniteScroll
                        :key="categoryType"
                        v-model="offerCategoryId"
                        :fetchRoute="activeCategoryRoute"
                        required
                        :placeholder="trans('Select category from the list')"
                        valueProp="id"
                        labelProp="name" />

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

                            <DatePicker v-model="startDate" :minDate="today" showIcon dateFormat="yy-mm-dd" class="w-full"
                                :placeholder="trans('Select start date')" />
                        </div>

                        <!-- End Date (Only for Interval) -->
                        <div v-if="dateType === 'interval'" class="space-y-2">
                            <label class="font-medium mb-2 block">
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

                   <Button
                        full
                        icon="fad fa-save"
                        :label="isLoadingSubmit ? trans('Loading') : trans('Save')"
                        @click="submitCategoryOffer"
                        :disabled="isFormInvalid || isLoadingSubmit"
                        :loading="isLoadingSubmit"
                    />
                </div>


            </div>
        </Modal>
    </div>
</template>
