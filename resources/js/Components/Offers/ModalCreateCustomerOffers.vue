<script setup lang="ts">

import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { ref, reactive, inject } from 'vue'
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
console.log("[props]", props)

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

function getEntityFetchRoute(key: string) {
    if (key === 'by_family') {
        return {
            name: 'grp.json.shop.families',
            parameters: { shop: layout.group.id }
        }
    }

    if (key === 'by_subdepartment') {
        return {
            name: 'grp.json.shop.sub_departments',
            parameters: { shop: layout.group.id }
        }
    }

    if (key === 'by_departments') {
        return {
            name: 'grp.json.shop.departments',
            parameters: { shop: props.shop_data.slug }
        }
    }


    if (key === 'by_collection') {
        return {
            name: 'grp.json.shop.collection.webpages',
            parameters: { shop: layout.group.id }
        }
    }



    return null
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
            name: 'grp.json.shop.collection.webpages',
            param: layout.group.id
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

const isLoadingSubmit = ref(false)
const submitCategoryOffer = () => {
    // Section: Submit
    router.post(
        route('grp.org.shops.show.discounts.offers.campaigns.store', {
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
const productFetchRoute = {
    name: 'grp.json.shop.products_for_website_workshop',
    parameters: {
        shop: (route().params as any).shop
    }
}
</script>

<template>
    <div>
        <Button :label="trans('Create Customer Offer')" @click="isOpenModal = true" icon="fas fa-badge-percent" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="isOpenModal = false">
            <div class="px-6">
                <h2 class="text-2xl font-bold mxb-4 text-center">{{ trans('Create Customer Offer') }}</h2>
                <div class="mt-8 space-y-8">
                    <!-- <div>
                        <label class="font-medium mb-2 flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk" class="text-xs text-red-400" />
                            {{ trans('Offer name') }}
                        </label>

                        <PureInput v-model="offerLabel" :placeholder="trans('Enter offer name')" />
                    </div> -->

                    <!-- Product Filters -->
                    <div class="space-y-4">

                        <div class="font-semibold text-gray-700 border-b pb-2">
                            <FontAwesomeIcon icon="fas fa-asterisk" class="text-xs text-red-400" />
                            {{ trans('Filters') }}
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                            <!-- Department -->
                            <div>
                                <label class="block font-medium mb-2">
                                    {{ trans('Department') }}
                                </label>

                                <PureMultiselectInfiniteScroll v-if="getEntityFetchRoute('by_departments')"
                                    mode="single" v-model="departmentId"
                                    :initOptions="preloadedEntities.by_departments || []"
                                    :fetchRoute="getEntityFetchRoute('by_departments')!" valueProp="id" labelProp="name"
                                    :placeholder="trans('Select department')" />
                            </div>

                            <!-- Sub Department -->
                            <div>
                                <label class="block font-medium mb-2">
                                    {{ trans('Sub Department') }}
                                </label>

                                <PureMultiselectInfiniteScroll v-if="getEntityFetchRoute('by_subdepartment')"
                                    mode="single" v-model="subDepartmentId"
                                    :initOptions="preloadedEntities.by_subdepartment || []"
                                    :fetchRoute="getEntityFetchRoute('by_subdepartment')!" valueProp="id"
                                    labelProp="name" :placeholder="trans('Select sub department')" />
                            </div>

                            <!-- Family -->
                            <div>
                                <label class="block font-medium mb-2">
                                    {{ trans('Family') }}
                                </label>

                                <PureMultiselectInfiniteScroll v-if="getEntityFetchRoute('by_family')" mode="single"
                                    v-model="familyId" :fetchRoute="getEntityFetchRoute('by_family')!" valueProp="id"
                                    :initOptions="preloadedEntities.by_family || []" labelProp="name"
                                    :placeholder="trans('Select family')" />
                            </div>


                            <div>
                                <label class="block font-medium mb-2">
                                    {{ trans('Collection') }}
                                </label>

                                <PureMultiselectInfiniteScroll v-if="getEntityFetchRoute('by_collection')" mode="single"
                                    v-model="collectionId" :initOptions="preloadedEntities.by_collection || []"
                                    :fetchRoute="getEntityFetchRoute('by_collection')!" valueProp="id" labelProp="name"
                                    :placeholder="trans('Select Collection')" />
                            </div>

                            <!-- product filter -->
                            <div>
                                <label class="block font-medium mb-2">
                                    {{ trans('Select Product') }}
                                </label>

                                <PureMultiselectInfiniteScroll v-model="offerCategoryId" :fetchRoute="productFetchRoute"
                                    valueProp="id" labelProp="name" :required="true"
                                    :placeholder="trans('Select product')" />
                            </div>


                        </div>
                    </div>

                    <!-- Discount -->
                    <div>
                        <label class="font-medium mb-2 flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk" class="text-xs text-red-400" />
                            {{ trans('Percentage Discount') }}
                        </label>

                        <InputNumber v-model="discountPercentage" inputId="offer_discount" suffix="%" :min="0"
                            :max="100" class="w-full" :placeholder="trans('Enter percentage')" />
                    </div>

                </div>

                <div class="pl-4 mt-8 flex justify-end gap-x-4">
                    <Button @click="isOpenModal = false" type="cancel" />
                    <Button full icon="fad fa-save" :label="trans('Save')" @click="submitCategoryOffer"
                        :isLoading="isLoadingSubmit" :disabled="!offerCategoryId ||
                            !offerLabel ||
                            discountPercentage === null ||
                            (typeOffer === 'quantity' && offerQtyItems === null) ||
                            (typeOffer === 'amount' && offerAmount === null)
                            ">
                    </Button>
                </div>
            </div>
        </Modal>
    </div>
</template>
