<script setup lang="ts">

import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { ref, computed, watch } from 'vue'
import PureMultiselectInfiniteScroll from '../Pure/PureMultiselectInfiniteScroll.vue'
import { InputNumber, RadioButton, DatePicker } from 'primevue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'
import InformationIcon from '../Utils/InformationIcon.vue'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps<{
    shop_data: {
        id: number
        organisation?: string
        offercampaign?: string
        slug: string
        currency_code: string
        default_dates: {
            start: string
            end: string
        }
    }
    customer_id?: number
}>()

console.log("props.show", props)
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
const isLoadingSubmit = ref(false)

const customerId = ref<number | null>(null)
const offerAmount = ref<number | null>(0)
const discountPercentage = ref<number | null>(null)

const dateType = ref<'permanent' | 'interval'>('permanent')
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

type TargetType = 'shop' | 'department' | 'subdepartment' | 'family' | 'collection' | 'product'
const target = ref<TargetType | null>(null)

const categoryFilters = ref<number | null>(null)
const collectionFilters = ref<number | null>(null)
const productFilters = ref<number | null>(null)

const shopId = props.shop_data.id
const shopSlug = props.shop_data.slug

const customerFetchRoute = {
    name: 'grp.json.shop.customers',
    parameters: { shop: shopId }
}

type CategoryTarget = 'department' | 'subdepartment' | 'family'

const categoryRoutes: Record<CategoryTarget, { name: string; parameters: Record<string, unknown> }> = {
    department: {
        name: 'grp.json.shop.departments',
        parameters: { shop: shopSlug }
    },
    subdepartment: {
        name: 'grp.json.shop.sub_departments',
        parameters: { shop: shopId }
    },
    family: {
        name: 'grp.json.shop.families',
        parameters: { shop: shopId }
    }
}

const isCategoryTarget = (t: TargetType | null): t is CategoryTarget =>
    t === 'department' || t === 'subdepartment' || t === 'family'

const activeCategoryRoute = computed(() =>
    isCategoryTarget(target.value) ? categoryRoutes[target.value] : null
)

const collectionRoute = {
    name: 'grp.json.shop.catalogue.collections',
    parameters: { shop: shopSlug, scope: shopSlug }
}

const productFetchRoute = {
    name: 'grp.json.shop.products',
    parameters: { shop: shopSlug }
}

const targetOptions: { value: TargetType; label: string }[] = [
    { value: 'shop', label: 'Shop' },
    { value: 'department', label: 'Department' },
    { value: 'subdepartment', label: 'Sub Department' },
    { value: 'family', label: 'Family' },
    { value: 'collection', label: 'Collection' },
    { value: 'product', label: 'Product' },
]

const today = new Date(new Date().setHours(0, 0, 0, 0))

function formatDate(date: Date | null) {
    if (!date) return null

    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

const buildTargetPayload = () => {
    const t = target.value
    if (!t) return null

    if (t === 'shop') {
        return shopId ? { type: 'shop', id: shopId } : null
    }
    if (t === 'product') {
        return productFilters.value ? { type: 'product', id: productFilters.value } : null
    }
    if (t === 'collection') {
        return collectionFilters.value ? { type: 'collection', id: collectionFilters.value } : null
    }
    if (isCategoryTarget(t)) {
        return categoryFilters.value ? { type: t, id: categoryFilters.value } : null
    }
    return null
}

const submitCustomerOffer = () => {
    const targetPayload = buildTargetPayload()
    const targets = targetPayload ? [targetPayload] : []

    const payload = {
        customer_id: customerId.value || props.customer_id,
        offer_amount: offerAmount.value,
        discount_percentage: discountPercentage.value,
        targets: targets,
        date_type: dateType.value,
        start_at: formatDate(startDate.value),
        end_at: dateType.value === 'interval' ? formatDate(endDate.value) : null,
    }

    // axios.post(
    //     route('grp.models.store_customer_offer', {
    //         shop: props.shop_data.id,
    //     }),
    //     payload
    // )
    // .then((response) => {
    //     notify({
    //         title: trans("Success"),
    //         text: trans("Successfully submit the data"),
    //         type: "success"
    //     })
    //     resetForm();
    //     isOpenModal.value = false

    //     if (!props.customer_id) {
    //         router.visit(route('grp.org.shops.show.discounts.campaigns.offer.show', {
    //             organisation: props.shop_data.organisation,
    //             shop: props.shop_data.slug,
    //             offerCampaign: props.shop_data.offercampaign,
    //             offer: response.data.slug
    //         }))
    //     }
    //     router.reload()
    // })
    // .catch((error) => {
    //     const errors = error.response?.data?.errors || {}
    //     const errMsg = Object.values(errors).join('. ') || trans("Failed to submit the data, please try again");
    //     notify({
    //         title: trans("Something went wrong"),
    //         text: errMsg,
    //         type: "error"
    //     })
    // })
    // .finally(() => {
    //     isLoadingSubmit.value = false
    // })
    router.post(
        route('grp.models.store_customer_offer', {
            shop: props.shop_data.id,
        }),
        payload,
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                isLoadingSubmit.value = true
            },
            onSuccess: () => {
                closeModal()
                notify({
                    title: trans("Success"),
                    text: trans("Successfully submit the data"),
                    type: "success"
                })
            },
            onError: () => {
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
    customerId.value = null
    offerAmount.value = 0
    discountPercentage.value = null
    target.value = null
    categoryFilters.value = null
    collectionFilters.value = null
    productFilters.value = null
    dateType.value = 'permanent'
    startDate.value = null
    endDate.value = null
}

watch(target, () => {
    categoryFilters.value = null
    collectionFilters.value = null
    productFilters.value = null
})

const isFormInvalid = computed(() => {
    if (!customerId.value && !props.customer_id) return true

    if (offerAmount.value === null || offerAmount.value === undefined || offerAmount.value < 0) return true

    const pct = discountPercentage.value
    if (pct === null || pct === undefined || pct <= 0 || pct > 100) return true

    if (buildTargetPayload() === null) return true

    if (!startDate.value) return true

    if (dateType.value === 'interval' && !endDate.value) return true

    return false
})
</script>

<template>
    <div>
        <Button :label="trans('Create Customer Offer')" @click="openModal" icon="fas fa-badge-percent" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="closeModal">
            <div class="p-1 space-y-3">
                <h2 class="text-2xl font-bold mb-4 text-center">{{ trans('Create Customer Offer') }}</h2>

                <!-- Customer -->
                <div class="space-y-2" v-if="!props.customer_id">
                    <label class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Select Customer') }}:
                    </label>
                    <PureMultiselectInfiniteScroll 
                    v-model="customerId" :fetchRoute="customerFetchRoute" valueProp="id"
                        labelProp="name" labelAdditionalProp="reference" :placeholder="trans('Select customer')" />
                </div>

                <!-- Minimum purchase amount -->
                <div class="space-y-2">
                    <label class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Minimum purchase amount') }}:
                    </label>
                    <InputNumber v-model="offerAmount" inputId="offer_amount" class="w-full" mode="currency"
                        :currency="props.shop_data.currency_code" locale="en-US"
                        :placeholder="trans('Enter minimum amount')" />
                </div>

                <!-- Target -->
                <div class="space-y-3">
                    <h3 class="text-sm text-gray-500">
                        {{ trans('Choose where this offer will apply') }}
                    </h3>
                    <label class="font-semibold">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Target') }}
                    </label>

                    <div class="flex flex-wrap gap-4">
                        <label v-for="opt in targetOptions" :key="opt.value" :for="`target-${opt.value}`"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                            :class="target === opt.value
                                ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                : 'border-gray-200 hover:border-gray-300'">
                            <RadioButton v-model="target" :value="opt.value" :inputId="`target-${opt.value}`" />
                            <span>{{ trans(opt.label) }}</span>
                        </label>
                    </div>

                    <div v-if="activeCategoryRoute" class="space-y-2">
                        <label class="font-medium">
                            {{ trans('Select Item') }}
                        </label>
                        <PureMultiselectInfiniteScroll :key="target ?? 'none'" v-model="categoryFilters"
                            :fetchRoute="activeCategoryRoute" valueProp="id" labelProp="name" />
                    </div>

                    <div v-if="target === 'collection'" class="space-y-2">
                        <label class="font-medium">
                            {{ trans('Select Item') }}
                        </label>
                        <PureMultiselectInfiniteScroll v-model="collectionFilters" :fetchRoute="collectionRoute"
                            valueProp="id" labelProp="name" />
                    </div>

                    <div v-if="target === 'product'" class="space-y-2">
                        <label class="font-medium">
                            {{ trans('Select Item') }}
                        </label>
                        <PureMultiselectInfiniteScroll v-model="productFilters" :fetchRoute="productFetchRoute"
                            valueProp="id" labelProp="name" />
                    </div>
                </div>

                <!-- Discount -->
                <div class="space-y-2">
                    <label class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="text-xs text-red-400" />
                        {{ trans('Percentage Discount') }}
                    </label>

                    <InputNumber v-model="discountPercentage" inputId="offer_discount" suffix="%" :min="0" :max="100"
                        class="w-full" :placeholder="trans('Enter percentage')" />
                </div>

                <!-- Offer Duration -->
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

                        <div v-if="dateType === 'interval'" class="space-y-2">
                            <label class="font-medium mb-2 block">
                                {{ trans('End Date') }}
                                <InformationIcon
                                    :information="trans('If end date is empty, will treat as permanent')" />:
                            </label>

                            <DatePicker v-model="endDate" showIcon dateFormat="yy-mm-dd" class="w-full"
                                :minDate="startDate" :placeholder="trans('Select end date')" />
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-x-4">
                    <Button @click="closeModal" type="cancel" />
                    <Button full icon="fad fa-save" :label="isLoadingSubmit ? trans('Loading') : trans('Save')"
                        @click="submitCustomerOffer" :loading="isLoadingSubmit"
                        :disabled="isFormInvalid || isLoadingSubmit" />
                </div>
            </div>
        </Modal>
    </div>
</template>
