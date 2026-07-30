<script setup lang="ts">

import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { ref, computed, nextTick, watch } from 'vue'
import PureMultiselectInfiniteScroll from '../Pure/PureMultiselectInfiniteScroll.vue'
import { InputNumber, RadioButton, DatePicker } from 'primevue'
import { trans } from 'laravel-vue-i18n'
import InformationIcon from '../Utils/InformationIcon.vue'
import { notify } from '@kyvg/vue3-notification'
import { faPlus, faTrash, faLayerGroup } from "@fas"
import { router } from '@inertiajs/vue3'
import PureInput from '../Pure/PureInput.vue'
import Image from '../../Common/Components/Image.vue'
import axios from 'axios'
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faPlus, faTrash, faLayerGroup)

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

interface DiscountStep {
    min_quantity: number | null
    percentage: number | null
}

const today = new Date(new Date().setHours(0, 0, 0, 0))
const isOpenModal = ref(false)
const offerLabel = ref('')
const productId = ref<number | null>(null)
const selectedProduct = ref<any | null>(null)
const steps = ref<DiscountStep[]>([])
const dateType = ref<'permanent' | 'interval'>('permanent')
const startDate = ref<Date | null>(today)
const endDate = ref<Date | null>(null)

const quickIntervalDays = ref<number | null>(null)
const quickIntervalPresets = [1, 2, 3, 7]

const isLoadingSubmit = ref(false)

let isApplyingPreset = false

const buildDefaultSteps = (): DiscountStep[] => [
    { min_quantity: 1, percentage: null },
    { min_quantity: 10, percentage: null },
]

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

const productFetchRoute = {
    name: 'grp.json.shop.products_including_not_for_sale',
    parameters: {
        shop: (route().params as any).shop
    }
}

const selectedProductImage = computed(() =>
    selectedProduct.value?.web_images?.main?.original || null
)

const addStep = () => {
    const previous = steps.value[steps.value.length - 1]
    const nextMinQuantity = previous?.min_quantity != null ? previous.min_quantity + 1 : null

    steps.value.push({ min_quantity: nextMinQuantity, percentage: null })
}

const removeStep = (index: number) => {
    if (steps.value.length <= 1) {
        return
    }

    steps.value.splice(index, 1)
}

const rangeLabel = (index: number): string => {
    const from = steps.value[index]?.min_quantity

    if (from == null) {
        return '—'
    }

    const nextMinQuantity = steps.value[index + 1]?.min_quantity

    if (nextMinQuantity == null) {
        return trans(':from and above', { from: String(from) })
    }

    return `${from} – ${Math.max(from, nextMinQuantity - 1)}`
}

const stepErrors = computed(() =>
    steps.value.map((step, index) => {
        const errors: { min_quantity?: string; percentage?: string } = {}
        const previous = index > 0 ? steps.value[index - 1] : null

        if (step.min_quantity == null) {
            errors.min_quantity = trans('Minimum quantity is required')
        } else if (!Number.isInteger(step.min_quantity) || step.min_quantity < 1) {
            errors.min_quantity = trans('Minimum quantity must be a whole number of at least 1')
        } else if (previous?.min_quantity != null && step.min_quantity <= previous.min_quantity) {
            errors.min_quantity = trans('Minimum quantity must be greater than the previous step')
        }

        if (step.percentage == null) {
            errors.percentage = trans('Discount is required')
        } else if (step.percentage <= 0 || step.percentage > 100) {
            errors.percentage = trans('Discount must be between 1 and 100')
        } else if (previous?.percentage != null && step.percentage <= previous.percentage) {
            errors.percentage = trans('Discount must be greater than the previous step')
        }

        return errors
    })
)

const hasStepErrors = computed(() =>
    stepErrors.value.some((errors) => Object.keys(errors).length > 0)
)

const resetForm = () => {    
    offerLabel.value = ''
    productId.value = props.product_id || null
    selectedProduct.value = null
    steps.value = buildDefaultSteps()
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

const isFormInvalid = computed(() => {
    if (!offerLabel.value) return true
    if (!productId.value && !props.product_id) return true
    if (steps.value.length === 0) return true
    if (hasStepErrors.value) return true
    if (!startDate.value) return true
    if (dateType.value === 'interval' && !endDate.value) return true
    return false
})

const submitStepDiscount = () => {
    isLoadingSubmit.value = true

    const payload = {
        name: offerLabel.value,
        product_id: productId.value || props.product_id,
        steps: steps.value.map((step) => ({
            min_quantity: step.min_quantity,
            percentage_off: step.percentage != null ? step.percentage / 100 : null,
        })),
        duration: dateType.value,
        start_at: formatDate(startDate.value),
        end_at: dateType.value === 'interval' ? formatDate(endDate.value) : null,
    }
    axios.post(
        route('grp.models.step_discount.store', {
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

resetForm()
</script>

<template>
    <div>
        <Button :label="trans('Create Step Discount')" @click="openModal" icon="fas fa-layer-group" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="closeModal">
            <div class="p-1 space-y-3">
                <h2 class="text-2xl font-bold mb-4 text-center">{{ trans('Create Step Discount') }}</h2>

                <div class="space-y-2" v-if="!props.product_id">
                    <label class="font-medium mb-2 flex items-center gap-x-1">
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
                    <div class="h-24 rounded-lg border border-gray-200 shadow-sm flex items-center justify-center">
                        <Image :src="selectedProductImage" alt="Product image" object-cover />
                    </div>
                </div>

                <div class="space-y-2">
                    <label for="amount" class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Offer name') }}:
                    </label>

                    <PureInput v-model="offerLabel" :placeholder="trans('Enter offer name')" />
                </div>

                <!-- Section: Discount Steps -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="font-medium flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                            {{ trans('Discount steps') }}
                            <InformationIcon :information="trans('The more quantity a customer buys, the bigger the discount they get')" />:
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div v-for="(step, index) in steps" :key="index"
                            class="rounded-lg border border-gray-200 p-3 space-y-2">
                            <div class="flex items-start gap-x-3">
                                <div class="space-y-1 flex-1">
                                    <label class="text-sm text-gray-500">{{ trans('Minimum quantity') }}</label>
                                    <InputNumber v-model="step.min_quantity" :min="1" class="w-full" inputClass="w-full"
                                        :placeholder="trans('Enter minimum quantity')"
                                        :suffix="' ' + ((step.min_quantity ?? 0) > 1 ? trans('items') : trans('item'))"
                                        :invalid="!!stepErrors[index]?.min_quantity" />
                                    <p v-if="stepErrors[index]?.min_quantity" class="text-xs text-red-500">
                                        {{ stepErrors[index]?.min_quantity }}
                                    </p>
                                </div>

                                <div class="space-y-1 flex-1">
                                    <label class="text-sm text-gray-500">{{ trans('Discount') }}</label>
                                    <InputNumber v-model="step.percentage" :min="0" :max="100" suffix="%" class="w-full"
                                        inputClass="w-full" :placeholder="trans('Enter percentage')"
                                        :invalid="!!stepErrors[index]?.percentage" />
                                    <p v-if="stepErrors[index]?.percentage" class="text-xs text-red-500">
                                        {{ stepErrors[index]?.percentage }}
                                    </p>
                                </div>

                                <button type="button" @click="removeStep(index)" :disabled="steps.length <= 1"
                                    class="mt-6 h-12 w-12 shrink-0 flex items-center justify-center rounded-lg border border-gray-200 text-gray-400 transition-colors hover:border-red-300 hover:text-red-500 disabled:opacity-40 disabled:cursor-not-allowed">
                                    <FontAwesomeIcon icon="fas fa-trash" />
                                </button>
                            </div>

                            <div class="text-xs text-gray-500">
                                {{ trans('Applies to quantity') }}:
                                <span class="font-medium text-gray-700">{{ rangeLabel(index) }}</span>
                            </div>
                        </div>
                    </div>

                    <button type="button" @click="addStep"
                        class="w-full flex items-center justify-center gap-x-2 rounded-lg border border-dashed border-gray-300 py-2 text-sm text-gray-500 transition-colors hover:border-green-400 hover:text-green-600">
                        <FontAwesomeIcon icon="fas fa-plus" />
                        {{ trans('Add step') }}
                    </button>
                </div>

                <!-- Section: Offer Duration -->
                <div class="space-y-3">
                    <div class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Offer Duration') }}:
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <label for="step-permanent"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                            :class="dateType === 'permanent'
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            <RadioButton v-model="dateType" inputId="step-permanent" value="permanent" />
                            <span>{{ trans('Permanent') }}</span>
                        </label>

                        <label for="step-interval"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                            :class="dateType === 'interval'
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            <RadioButton v-model="dateType" inputId="step-interval" value="interval" />
                            <span>{{ trans('Interval') }}</span>
                        </label>

                        <button v-if="dateType === 'interval'" v-for="days in quickIntervalPresets" :key="days"
                            type="button" @click="applyQuickInterval(days)"
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
                                    :information="trans('If end date is empty, will treat as permanent')" />:
                            </label>

                            <DatePicker v-model="endDate" showIcon dateFormat="yy-mm-dd" class="w-full"
                                :minDate="startDate || undefined" :placeholder="trans('Select end date')" />
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-x-4">
                    <Button @click="closeModal" type="cancel" />
                    <Button full icon="fad fa-save" :label="isLoadingSubmit ? trans('Loading') : trans('Save')"
                        @click="submitStepDiscount" :loading="isLoadingSubmit" :disabled="isFormInvalid || isLoadingSubmit">
                    </Button>
                </div>
            </div>
        </Modal>
    </div>
</template>
