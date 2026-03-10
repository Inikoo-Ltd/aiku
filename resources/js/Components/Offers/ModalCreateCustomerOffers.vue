<script setup lang="ts">

import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { ref, reactive, inject, computed, watch } from 'vue'
import PureMultiselectInfiniteScroll from '../Pure/PureMultiselectInfiniteScroll.vue'
import { InputNumber, RadioButton } from 'primevue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'
import InformationIcon from '../Utils/InformationIcon.vue'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'
import PureInput from '../Pure/PureInput.vue'
import axios from 'axios'

const props = defineProps<{
    shop_data: {
        slug: string
        currency_code: string
    }
}>()

const layout = inject('layout', {})
console.log("layout", layout)
const isOpenModal = ref(false)

const offerLabel = ref('')
const typeOffer = ref('quantity')
const offerQtyItems = ref<number | null>(null)
const offerAmount = ref<number | null>(0)
const discountPercentage = ref<number | null>(null)
const offerCategoryId = ref(null)

const departmentId = ref<number | null>(null)
const subDepartmentId = ref<number | null>(null)
const familyId = ref<number | null>(null)
const collectionId = ref<number | null>(null)
const filterType = ref<'department' | 'subdepartment' | 'family' | 'collection' | null>(null)
const selectedFilters = ref<number[]>([])
const isLoadingSubmit = ref(false)

const shopId = layout?.group?.id
const shopSlug = props.shop_data.slug

const entityRoutes = {
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
    },
    collection: {
        name: 'grp.org.shops.show.catalogue.collections.active.index',
        parameters: {
            organisation: 'se',
            shop: shopId
        }
    }
}

function getEntityFetchRoute(key: string) {
    if (key === 'by_family') {
        return {
            name: 'grp.json.shop.families',
            parameters: { shop: layout.group?.id }
        }
    }

    if (key === 'by_subdepartment') {
        return {
            name: 'grp.json.shop.sub_departments',
            parameters: { shop: layout.group?.id }
        }
    }

    if (key === 'by_departments') {
        return {
            name: 'grp.json.shop.departments',
            parameters: { shop: props.shop_data.slug }
        }
    }


    if (key === 'by_collection') {
        const routeConfig = {
            name: 'grp.org.shops.show.catalogue.collections.active.index',
            parameters: { organisation: 'se', shop: layout.group?.id }
        }

        console.log('collection route:', route(routeConfig.name, routeConfig.parameters))

        return routeConfig
    }



    return null
}

const activeFilterRoute = computed(() => {
    if (!filterType.value) return null
    return entityRoutes[filterType.value]
})

const productFetchRoute = {
    name: 'grp.json.shop.products_for_website_workshop',
    parameters: {
        shop: (route().params as any).shop
    }
}

const preloadedEntities = reactive<Record<string, any[]>>({})

const preloadEntityOptions = async (key: string, ids: number[]) => {
    if (!ids?.length) return

    const routeMap: Record<string, { name: string, param: number | string }> = {
        by_family: {
            name: 'grp.json.shop.families',
            param: layout.group.id
        },

        by_subdepartment: {
            name: 'grp.json.shop.sub_departments',
            param: layout.group.id
        },

        by_departments: {
            name: 'grp.json.shop.departments',
            param: props.shop_data.slug
        },

        by_collection: {
            name: 'grp.org.shops.show.catalogue.collections.active.index',
            param: {
                organisation: 'se',
                shop: layout.group.id
            }
        }
    }

    const cfg = routeMap[key]
    if (!cfg) return

    try {
        const res = await axios.get(route(cfg.name, {
            shop: cfg.param,
            ids
        }))

        preloadedEntities[key] = res.data?.data ?? []
    } catch (err) {
        console.error(err)
    }
}

const submitCategoryOffer = () => {
    // Section: Submit
    router.post(
        route('grp.org.shops.show.discounts.campaigns.campaigns.store_customer', {
            organisation: 'sk',
            shop: 'se',
            offerCampaign: 'co-se',
        }),
        {
            name: offerLabel.value,
            type: typeOffer.value,
            offer_qty_items: offerQtyItems.value,
            offer_amount: offerAmount.value,
            discount_percentage: discountPercentage.value,

            department_id: departmentId.value,
            sub_department_id: subDepartmentId.value,
            family_id: familyId.value,
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

function onOfferQtyInput(e: { value: string | number | undefined }) {
    offerQtyItems.value = e.value == null ? null : Number(e.value)
}

const resetForm = () => {
    offerLabel.value = ''
    typeOffer.value = 'quantity'
    offerQtyItems.value = null
    offerAmount.value = null
    discountPercentage.value = null
    offerCategoryId.value = null

    departmentId.value = null
    subDepartmentId.value = null
    familyId.value = null
}

const isFormInvalid = computed(() => {
    if (!offerCategoryId.value) return true
    if (!discountPercentage.value) return true
    return false
})

watch(filterType, () => {
    selectedFilters.value = []
    offerCategoryId.value = null
})
</script>

<template>
    <div>
        <Button :label="trans('Create Customer Offer')" @click="isOpenModal = true" icon="fas fa-badge-percent" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="isOpenModal = false">
            <div class="p-1 space-y-3">
                <h2 class="text-2xl font-bold mb-4 text-center">{{ trans('Create Customer Offer') }}</h2>
                <div class="space-y-3">

                    <label class="font-semibold">
                        {{ trans('Apply Discount To') }}
                    </label>

                    <div class="flex flex-wrap gap-4">

                        <div v-for="type in ['department', 'subdepartment', 'family', 'collection']" :key="type"
                            class="flex items-center gap-2">

                            <RadioButton v-model="filterType" :value="type" :inputId="type" />

                            <label :for="type" class="cursor-pointer">
                                {{ trans(type) }}
                            </label>

                        </div>

                    </div>

                </div>
                <div v-if="activeFilterRoute" class="space-y-2">

                    <label class="block font-medium mb-2">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Select ' + filterType) }}
                    </label>

                    <PureMultiselectInfiniteScroll :key="filterType" v-model="selectedFilters" mode="multiple"
                        :fetchRoute="activeFilterRoute" valueProp="id" labelProp="name"
                        :placeholder="trans('Select items')" />

                </div>
                <div class="space-y-2">
                    <label class="font-medium">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Select Product') }}
                    </label>

                    <PureMultiselectInfiniteScroll v-model="offerCategoryId" :fetchRoute="productFetchRoute"
                        valueProp="id" labelProp="name" :required="true" :placeholder="trans('Select product')" />
                </div>
                <div class="space-y-2">
                    <label class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Minimum purchase amount') }}:
                    </label>
                    <InputNumber v-model="offerAmount" inputId="offer_amount" class="w-full" mode="currency"
                        :currency="props.shop_data.currency_code" locale="en-US"
                        :placeholder="trans('Enter minimum amount')" />
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
