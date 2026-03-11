<script setup lang="ts">

import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { ref, reactive, inject, computed, watch } from 'vue'
import PureMultiselectInfiniteScroll from '../Pure/PureMultiselectInfiniteScroll.vue'
import { InputNumber, RadioButton, Checkbox, DatePicker } from 'primevue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'
import InformationIcon from '../Utils/InformationIcon.vue'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'
import Multiselect from "@vueform/multiselect"

const props = defineProps<{
    shop_data: {
        slug: string
        currency_code: string
    }
}>()

const layout = inject('layout', {})
const currentParams = layout?.currentParams ?? {}
const isOpenModal = ref(false)
const isLoadingSubmit = ref(false)

const offerAmount = ref<number | null>(0)
const discountPercentage = ref<number | null>(null)

const organisation = currentParams.organisation
const shopCode = currentParams.shop
const offerCampaign = currentParams.offerCampaign

const selectedFilters = ref<number[]>([])
const selectedShops = ref([])
const categoryType = ref<'department' | 'subdepartment' | 'family' | null>(null)
const categoryFilters = ref<number[]>([])
const collectionFilters = ref<number[]>([])
const productFilters = ref<number[]>([])
const dateType = ref<'permanent' | 'interval'>('permanent')
const startDate = ref<Date | null>(null)
const endDate = ref<Date | null>(null)

const target = reactive({
    shop: false,
    category: false,
    collection: false,
    product: false
})

const shopId = layout?.group?.id
const shopSlug = props.shop_data.slug

const categoryRoutes = {
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

const activeCategoryRoute = computed(() => {
    if (!categoryType.value) return null
    return categoryRoutes[categoryType.value]
})

const productFetchRoute = {
    name: 'grp.json.shop.products_for_website_workshop',
    parameters: {
        shop: (route().params as any).shop
    }
}

const submitCategoryOffer = () => {

    const payload = {
        target,
        category_type: categoryType.value,
        category_ids: categoryFilters.value,
        collection_ids: collectionFilters.value,
        product_ids: productFilters.value,
        offer_amount: offerAmount.value,
        discount_percentage: discountPercentage.value,
        shops: selectedShops.value
    }

    console.log("SUBMIT PAYLOAD:", payload)
    // return
    router.post(
        route('grp.org.shops.show.discounts.campaigns.store_customer', {
            organisation: 'sk',
            shop: 'se',
            offerCampaign: 'co-se',
        }),
        {
            target,
            category_type: categoryType.value,
            category_ids: categoryFilters.value,
            collection_ids: collectionFilters.value,
            product_ids: productFilters.value,
            offer_amount: offerAmount.value,
            discount_percentage: discountPercentage.value,
            date_type: dateType.value,
            start_date: startDate.value,
            end_date: endDate.value
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
    offerAmount.value = null
    discountPercentage.value = null
    categoryType.value = null
    categoryFilters.value = []
    collectionFilters.value = []
    productFilters.value = []
    target.category = false
    target.collection = false
    target.shop = false
    target.product = false
    dateType.value = 'permanent'
    startDate.value = null
    endDate.value = null
}

const isFormInvalid = computed(() => {

    if (!discountPercentage.value) return true

    if (!target.shop && !target.category && !target.collection && !target.product) {
        return true
    }

    if (target.product) {
        if (!productFilters.value.length) return true
        return false
    }

    if (target.shop && !selectedShops.value.length) {
        return true
    }

    if (target.category) {

        if (!categoryType.value) return true

        if (!categoryFilters.value.length) return true
    }

    if (target.collection && !collectionFilters.value.length) {
        return true
    }

    if (!startDate.value) return true

    if (dateType.value === 'interval' && !endDate.value) return true
    return false
})

const collectionRoute = computed(() => {

    const routeConfig = {
        name: 'grp.json.shop.catalogue.collections',
        parameters: {
            shop: shopCode,
            scope: shopCode
        }
    }

    return routeConfig
})

watch(() => target.product, (val) => {
    if (val) {
        target.category = false
        target.collection = false
        categoryType.value = null
        categoryFilters.value = []
        collectionFilters.value = []
    }
})

watch(categoryType, () => {
    categoryFilters.value = []
})

watch(categoryFilters, () => {
    collectionFilters.value = []
})

watch(() => target.category, (val) => {

    if (!val) {
        categoryType.value = null
        selectedFilters.value = []
    }

})

const authorisedShops =
    layout.organisations.data
        .find((o: any) => o.slug === organisation)
        ?.authorised_shops ?? []

console.log("authorisedShops", authorisedShops)
console.log("layout create", layout.organisations.data)
</script>

<template>
    <div>
        <Button :label="trans('Create Customer Offer')" @click="isOpenModal = true" icon="fas fa-badge-percent" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="isOpenModal = false">
            <div class="p-1 space-y-3">
                <h2 class="text-2xl font-bold mb-4 text-center">{{ trans('Create Customer Offer') }}</h2>

                <div class="space-y-2">
                    <label class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Minimum purchase amount') }}:
                    </label>
                    <InputNumber v-model="offerAmount" inputId="offer_amount" class="w-full" mode="currency"
                        :currency="props.shop_data.currency_code" locale="en-US"
                        :placeholder="trans('Enter minimum amount')" />
                </div>

                <div class="space-y-3">

                    <label class="font-semibold">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Target') }}
                    </label>

                    <div class="flex flex-wrap gap-4">

                        <div class="flex items-center gap-2">
                            <Checkbox v-model="target.shop" binary />
                            <label>{{ trans('Shop') }}</label>
                        </div>

                        <div class="flex items-center gap-2">
                            <Checkbox v-model="target.category" :disabled="target.product" binary />
                            <label>{{ trans('Category') }}</label>
                        </div>

                        <div class="flex items-center gap-2">
                            <Checkbox v-model="target.collection" :disabled="target.product" binary />
                            <label>{{ trans('Collection') }}</label>
                        </div>

                        <div class="flex items-center gap-2">
                            <Checkbox v-model="target.product" binary />
                            <label>{{ trans('Product') }}</label>
                        </div>

                    </div>

                </div>

                <div v-if="target.category" class="space-y-2">

                    <label class="font-medium">
                        {{ trans('Category Type') }}
                    </label>

                    <div class="flex gap-4">

                        <div class="flex items-center gap-2">
                            <RadioButton v-model="categoryType" value="department" />
                            <label>{{ trans('Department') }}</label>
                        </div>

                        <div class="flex items-center gap-2">
                            <RadioButton v-model="categoryType" value="subdepartment" />
                            <label>{{ trans('Sub Department') }}</label>
                        </div>

                        <div class="flex items-center gap-2">
                            <RadioButton v-model="categoryType" value="family" />
                            <label>{{ trans('Family') }}</label>
                        </div>

                    </div>

                </div>

                <div v-if="activeCategoryRoute">

                    <label class="font-medium">
                        {{ trans('Select Category') }}
                    </label>

                    <PureMultiselectInfiniteScroll :key="categoryType" v-model="categoryFilters" mode="multiple"
                        :fetchRoute="activeCategoryRoute" valueProp="id" labelProp="name"
                        :placeholder="trans('Select items')" />

                </div>

                <div v-if="target.shop">

                    <label class="font-medium">
                        {{ trans('Select Shop') }}
                    </label>
                    <Multiselect v-model="selectedShops" :options="authorisedShops" valueProp="slug" label="label"
                        mode="multiple" :closeOnSelect="false" :searchable="true" placeholder="Select shops">

                        <template #option="{ option }">
                            <div class="flex justify-between w-full">
                                <span>{{ option.label }}</span>
                                <span class="text-gray-400 text-sm">{{ option.code }}</span>
                            </div>
                        </template>

                    </Multiselect>

                </div>

                <div v-if="target.collection && collectionRoute">

                    <label class="font-medium">
                        {{ trans('Select Collection') }}
                    </label>

                    <PureMultiselectInfiniteScroll :key="categoryFilters" v-model="collectionFilters" mode="multiple"
                        :fetchRoute="collectionRoute" valueProp="id" labelProp="name"
                        @optionsList="(data) => console.log('Collection API result:', data)" />

                </div>

                <div v-if="target.product">

                    <label class="font-medium">
                        {{ trans('Select Product') }}
                    </label>

                    <PureMultiselectInfiniteScroll v-model="productFilters" :fetchRoute="productFetchRoute"
                        valueProp="id" labelProp="name" mode="multiple" />

                </div>


                <!-- DISCOUNT -->
                <div class="space-y-2">

                    <label class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="text-xs text-red-400" />
                        {{ trans('Percentage Discount') }}
                    </label>

                    <InputNumber v-model="discountPercentage" inputId="offer_discount" suffix="%" :min="0" :max="100"
                        class="w-full" :placeholder="trans('Enter percentage')" />
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

                            <DatePicker v-model="startDate" showIcon dateFormat="yy-mm-dd" class="w-full"
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
                                :minDate="startDate" :placeholder="trans('Select end date')" />
                        </div>
                    </div>

                </div>


                <div class="mt-8 flex justify-end gap-x-4">
                    <Button @click="isOpenModal = false" type="cancel" />
                    <Button full icon=" fad fa-save" :label="trans('Save')" @click="submitCategoryOffer" class="w-full"
                        :isLoading="isLoadingSubmit" :disabled="isFormInvalid">
                    </Button>
                </div>
            </div>
        </Modal>
    </div>
</template>
