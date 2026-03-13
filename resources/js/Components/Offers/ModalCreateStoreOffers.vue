<script setup lang="ts">

import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { ref, reactive, computed, inject, watch } from 'vue'
import { DatePicker, InputNumber, Checkbox, RadioButton } from 'primevue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'
import PureInput from '../Pure/PureInput.vue'
import InformationIcon from '../Utils/InformationIcon.vue'
import Toggle from '../Pure/Toggle.vue'
import PureMultiselectInfiniteScroll from '../Pure/PureMultiselectInfiniteScroll.vue'
import Multiselect from "@vueform/multiselect"

const props = defineProps<{
    shop_data: {
        slug: string
        currency_code: string
    }
}>()

const isOpenModal = ref(false)
const isLoadingSubmit = ref(false)
const layout = inject('layout', {})

const offerVoucher = ref('')
const offerLabel = ref('')
const offerAmount = ref<number | null>(0)
const startDate = ref(null)
const endDate = ref(null)
const reuseCustomer = ref(false)

// tools step
const step = ref(1)

// target
const target = reactive({
    shop: false,
    category: false,
    collection: false,
    product: false
})

const currentParams = layout?.currentParams ?? {}
const organisation = currentParams.organisation
const shopCode = currentParams.shop

const selectedFilters = ref<number[]>([])
const selectedShops = ref([])
const categoryType = ref<'department' | 'subdepartment' | 'family' | null>(null)
const categoryFilters = ref<number[]>([])
const collectionFilters = ref<number[]>([])
const productFilters = ref<number[]>([])
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

const categoryTypeKey = computed(() => {
    return categoryType.value ?? 'empty'
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

const productFetchRoute = {
    name: 'grp.json.shop.products_for_website_workshop',
    parameters: {
        shop: (route().params as any).shop
    }
}

const submitCategoryOffer = () => {
    // Section: Submit
    router.post(
        route('grp.org.shops.show.discounts.campaigns.store', {
            organisation: 'sk',
            shop: 'se',
            offerCampaign: 'co-se',
        }),
        {
            voucher: offerVoucher.value,
            name: offerLabel.value,
            type: 'amount',
            offer_amount: offerAmount.value,
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

const nextStep = () => {
    step.value = 2
}

const prevStep = () => {
    step.value = 1
}

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


const resetForm = () => {
    offerLabel.value = ''
    offerVoucher.value = ''
    startDate.value = null
    endDate.value = null
    reuseCustomer.value = false
    offerAmount.value = null
}

const isFormInvalid = computed(() => {
    if (!offerVoucher.value) return true
    if (!offerLabel.value) return true
    if (!startDate.value) return true

    if (!target.shop && !target.category && !target.collection && !target.product) {
        return true
    }

    // product override others
    if (target.product) {
        if (!productFilters.value.length) return true
        return false
    }

    // shop validation
    if (target.shop && !selectedShops.value.length) {
        return true
    }

    // category validation
    if (target.category) {
        if (!categoryType.value) return true
        if (!categoryFilters.value.length) return true
    }

    // collection validation
    if (target.collection && !collectionFilters.value.length) {
        return true
    }

    return false
})
</script>

<template>
    <div>
        <Button :label="trans('Create Voucher')" @click="isOpenModal = true" icon="fas fa-badge-percent" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="isOpenModal = false">
            <div class="p-1 space-y-6">
                <h2 class="text-2xl font-bold mb-4 text-center">
                    {{ trans('Create Voucher') }}
                </h2>

                <!-- Step Indicator -->
                <div class="flex items-center justify-center gap-6 mb-6">

                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 flex items-center justify-center rounded-full text-sm font-semibold"
                            :class="step >= 1 ? 'bg-blue-600 text-white' : 'bg-gray-200'">
                            1
                        </div>
                        <span class="text-sm font-medium">{{ trans('Voucher') }}</span>
                    </div>

                    <div class="w-10 h-[2px] bg-gray-300"></div>

                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 flex items-center justify-center rounded-full text-sm font-semibold"
                            :class="step >= 2 ? 'bg-blue-600 text-white' : 'bg-gray-200'">
                            2
                        </div>
                        <span class="text-sm font-medium">{{ trans('Target') }}</span>
                    </div>

                </div>

                <template v-if="step === 1">
                    <div class="space-y-2">
                        <label for="amount" class="font-medium mb-2 flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk"
                                class="font-light text-xs text-red-400 align-middle" />

                            {{ trans('Voucher name') }}:
                        </label>

                        <PureInput v-model="offerVoucher" :placeholder="trans('Enter Voucher name')" />

                    </div>
                    <!-- offer name -->
                    <div class="space-y-2">
                        <label for="amount" class="font-medium mb-2 flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk"
                                class="font-light text-xs text-red-400 align-middle" />

                            {{ trans('Offer name') }}:
                        </label>

                        <PureInput v-model="offerLabel" :placeholder="trans('Enter offer name')" />
                    </div>

                    <!-- amount -->
                    <div class="space-y-2">
                        <label class="font-medium mb-2 flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk"
                                class="font-light text-xs text-red-400 align-middle" />
                            {{ trans('Minimum purchase amount') }}:
                        </label>


                        <InputNumber v-model="offerAmount" inputId="offer_amount" class="w-full" mode="currency"
                            :currency="props.shop_data.currency_code" locale="en-US"
                            :placeholder="trans('Enter minimum amount')" />

                    </div>

                    <!-- Start date - end date -->
                    <div class="grid grid-cols-2 gap-x-6 ">
                        <div>
                            <label class="font-medium mb-2 flex items-center gap-x-1">
                                {{ trans('Start date') }}
                                <InformationIcon
                                    :information="trans('If start date is empty, will start immediately')" />:
                            </label>

                            <DatePicker v-model="startDate" showButtonBar showIcon />

                        </div>

                        <div>
                            <label class="font-medium mb-2 flex items-center gap-x-1">
                                {{ trans('End date') }}
                                <InformationIcon
                                    :information="trans('If end date is empty, will treat as permanent')" />:
                            </label>

                            <DatePicker v-model="endDate" showButtonBar showIcon :minDate="startDate" />

                        </div>
                    </div>

                    <!-- Discount -->
                    <div class="space-y-2">
                        <label class="font-medium flex items-center gap-x-1">
                            {{ trans('Can customers reuse the voucher') }}?
                        </label>

                        <Toggle v-model="reuseCustomer" />

                    </div>

                </template>
                <template v-if="step === 2">
                    <!-- target -->

                    <div class="min-h-[90px] space-y-2">
                        <div class="space-y-3 mb-2">
                            <h3 class="text-sm text-gray-500">
                                {{ trans('Choose where this voucher will apply') }}
                            </h3>
                            <label class="font-semibold">
                                <FontAwesomeIcon icon="fas fa-asterisk"
                                    class="font-light text-xs text-red-400 align-middle" />
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

                            <PureMultiselectInfiniteScroll :key="categoryTypeKey" v-model="categoryFilters"
                                mode="multiple" :fetchRoute="activeCategoryRoute" valueProp="id" labelProp="name"
                                :placeholder="trans('Select items')" />

                        </div>

                        <div v-if="target.shop">

                            <label class="font-medium">
                                {{ trans('Select Shop') }}
                            </label>
                            <Multiselect v-model="selectedShops" :options="authorisedShops" valueProp="slug"
                                label="label" mode="multiple" :closeOnSelect="false" :searchable="true"
                                placeholder="Select shops">

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

                            <PureMultiselectInfiniteScroll :key="categoryFilters" v-model="collectionFilters"
                                mode="multiple" :fetchRoute="collectionRoute" valueProp="id" labelProp="name" />

                        </div>

                        <div v-if="target.product">

                            <label class="font-medium">
                                {{ trans('Select Product') }}
                            </label>

                            <PureMultiselectInfiniteScroll v-model="productFilters" :fetchRoute="productFetchRoute"
                                valueProp="id" labelProp="name" mode="multiple" />

                        </div>
                    </div>
                </template>

                <div class="mt-8 flex justify-end gap-x-4">
                    <Button v-if="step === 2" @click="prevStep" :label="trans('Back')" type="cancel" />
                    <Button v-if="step === 1" @click="isOpenModal = false" type="cancel" />
                    <Button v-if="step === 1" full icon="fas fa-arrow-right" :label="trans('Next')" @click="nextStep"
                        :isLoading="isLoadingSubmit" :disabled="!offerLabel" />
                    <Button v-if="step === 2" full icon="fad fa-save" :label="trans('Save')"
                        @click="submitCategoryOffer" :isLoading="isLoadingSubmit" :disabled="isFormInvalid" />
                </div>

            </div>
        </Modal>
    </div>
</template>
