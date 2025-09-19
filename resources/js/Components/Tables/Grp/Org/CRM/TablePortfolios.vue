<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Product } from "@/types/product"
import { library } from "@fortawesome/fontawesome-svg-core"
import { inject, onMounted, ref, computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Components/Image.vue"
import { debounce, get, set } from "lodash-es"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import {
    faConciergeBell,
    faGarage,
    faExclamationTriangle,
    faSyncAlt,
    faPencil,
    faSearch,
    faThLarge,
    faListUl,
    faStar as falStar,
    faTrashAlt,
    faExclamationCircle,
    faClone,
    faLink, faScrewdriver, faTools,
    faRecycle, faHandPointer, faHandshakeSlash, faHandshake,
    faCheckCircle,
    faPlus
} from "@fal"
import { faStar, faFilter } from "@fas"
import { faExclamationTriangle as fadExclamationTriangle } from "@fad"
import { faCheck } from "@far"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { notify } from "@kyvg/vue3-notification"
import Modal from "@/Components/Utils/Modal.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import axios from "axios"
import { routeType } from "@/types/route";


library.add(faHandshake, faHandshakeSlash, faHandPointer, fadExclamationTriangle, faSyncAlt, faConciergeBell, faGarage, faExclamationTriangle, faPencil, faSearch, faThLarge, faListUl, faStar, faFilter, falStar, faTrashAlt, faCheck, faExclamationCircle, faClone, faLink, faScrewdriver, faTools)

const props = defineProps<{
    data: {}
    tab?: string
    routes: {}
    customerSalesChannel: {}
}>()
console.log('ddddd', props)
const locale = useLocaleStore()
const selectedPortfolio = ref(null)
const isLoadingTable = ref<null | string>(null)
const selectedProducts = defineModel<number[]>('selectedProducts')
const isLoadingSubmit = ref(false)
const isOpenModal = ref(false)
const querySearchPortfolios = ref('')
const selectedVariant = ref<Product | null>(null)
function itemRoute(portfolio: Portfolio) {
    return route(
        "grp.helpers.redirect_portfolio_item",
        [portfolio.id])
}

const onSubmitVariant = () => {
    router.post(
        route(props.routes.single_match.name, {
            portfolio: selectedPortfolio.value?.id,
            platform_product_id: selectedVariant.value?.id
        }),
        {

        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                isLoadingSubmit.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully match the product"),
                    type: "success"
                })

                isOpenModal.value = false
                setTimeout(() => {
                    selectedVariant.value = null
                    selectedPortfolio.value = null
                }, 700)

            },
            onError: errors => {
                console.log(errors)
                notify({
                    title: trans("Something went wrong"),
                    text: errors.message ?? trans("Failed to match the product to platform"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingSubmit.value = false
            },
        }
    )
}

const isDeleteLoading = ref<boolean | string>(false)
const onDeletePortfolio = async (routeDelete: routeType, portfolioReference: string) => {
    isDeleteLoading.value = portfolioReference
    try {
        router[routeDelete.method || "delete"](route(routeDelete.name, routeDelete.parameters),
            {
                onStart: () => {
                    isDeleteLoading.value = portfolioReference
                },
                onFinish: () => {
                    isDeleteLoading.value = false
                },
                onSuccess: () => {
                    notify({
                        title: "Success",
                        text: `Portfolio ${portfolioReference} has been deleted`,
                        type: "success"
                    })
                }
            })

    } catch {
        notify({
            title: "Something went wrong.",
            type: "error"
        })
    }
}

const resultOfFetchPlatformProduct = ref<PlatformProduct[]>([])
const isLoadingFetchPlatformProduct = ref(false)
const fetchRoute = async () => {
    isLoadingFetchPlatformProduct.value = true
    isLoadingSubmit.value = true

    try {
        const www = await axios.get(route(props.routes.fetch_products.name, {
            customerSalesChannel: props.customerSalesChannel?.id,
            query: querySearchPortfolios.value
        }))
        isLoadingSubmit.value = false
        resultOfFetchPlatformProduct.value = www.data
    } catch (e) {
        console.error("Error processing products", e)
        isLoadingSubmit.value = false
    }
    isLoadingFetchPlatformProduct.value = false

}




const onChangeCheked = (checked: boolean, item) => {
    if (!selectedProducts.value) return

    if (checked) {
        if (!selectedProducts.value.includes(item.id)) {
            selectedProducts.value.push(item.id)
        }
    } else {
        selectedProducts.value = selectedProducts.value.filter(id => id != item.id)
    }
}

const onCheckedAll = ({ data, allChecked }) => {
    if (!selectedProducts.value) return

    if (allChecked) {
        const newIds = data.map(row => row.id)
        selectedProducts.value = Array.from(new Set([...selectedProducts.value, ...newIds]))
    } else {
        const uncheckIds = data.map(row => row.id)
        selectedProducts.value = selectedProducts.value.filter(id => !uncheckIds.includes(id))
    }
}

const onDisableCheckbox = (item) => {
    if (item.platform_status && item.exist_in_platform && item.has_valid_platform_product_id) return true
    return false
}


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" :isCheckBox="false"
        @onChecked="(item) => onChangeCheked(true, item)" @onUnchecked="(item) => onChangeCheked(false, item)"
        @onCheckedAll="(data) => onCheckedAll(data)" checkboxKey='id'
        :isChecked="(item) => selectedProducts?.includes(item.id)" :disabledCheckbox="(item) => onDisableCheckbox(item)"
        :rowColorFunction="(item) => {
            if (!isPlatformManual && is_platform_connected && !item.platform_product_id && get(progressToUploadToShopify, [item.id], undefined) != 'success') {
                return 'bg-yellow-50'
            } else {
                return ''
            }
        }">
        <template #cell(item_code)="{ item: portfolio }">
            <Link :href="itemRoute(portfolio)" class="primaryLink">
            {{ portfolio["item_code"] }}
            </Link>
        </template>


        <template #cell(location)="{ item: portfolio }">
            <AddressLocation :data="portfolio['location']" />
        </template>


        <template #cell(created_at)="{ item: portfolio }">
            <div class="text-gray-500">{{ useFormatTime(portfolio["created_at"], {
                localeCode: locale.language.code,
                formatTime: "hm"
            }) }}
            </div>
        </template>


        <template #cell(platform_status)="{ item }">
            <div class="whitespace-nowrap">
                <FontAwesomeIcon v-if="item.has_valid_platform_product_id"
                    v-tooltip="trans('Has valid platform product id')" icon="fal fa-check" class="text-green-500"
                    fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-else v-tooltip="trans('Has valid platform product id')" icon="fal fa-times"
                    class="text-red-500" fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-if="item.exist_in_platform" v-tooltip="trans('Exist in platform')"
                    icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-else v-tooltip="trans('Exist in platform')" icon="fal fa-times" class="text-red-500"
                    fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-if="item.platform_status" v-tooltip="trans('Platform status')" icon="fal fa-check"
                    class="text-green-500" fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-else v-tooltip="trans('Platform status')" icon="fal fa-times" class="text-red-500"
                    fixed-width aria-hidden="true" />
            </div>
        </template>

        <template #cell(matches)="{ item }">
            <template v-if="item.customer_sales_channel_platform_status">
                <template v-if="!item.platform_status">

                    <div v-if="item.platform_possible_matches?.number_matches" class="border  rounded p-1"
                        :class="selectedProducts?.includes(item.id) ? 'bg-green-200 border-green-400' : 'border-gray-300'">
                        <div class="flex gap-x-2 items-center border border-gray-300 rounded p-1">
                            <div v-if="item.platform_possible_matches?.raw_data?.[0].images?.[0]?.src"
                                class="min-h-5 h-auto max-h-9 min-w-9 w-auto max-w-9 shadow border border-gray-300 rounded">
                                <img :src="item.platform_possible_matches?.raw_data?.[0]?.images?.[0]?.src" />
                            </div>
                            <div>
                                <span class="mr-1">{{ item.platform_possible_matches?.matches_labels[0] }}</span>
                            </div>
                        </div>

                        <ButtonWithLink v-if="item.platform_possible_matches?.number_matches"
                            v-tooltip="trans('Match to existing Shopify product')" :routeTarget="{
                                method: 'post',
                                name: props.routes.single_match.name,
                                parameters: {
                                    portfolio: item.id,
                                    platform_product_id: item.platform_possible_matches.raw_data?.[0]?.id
                                }
                            }" :bindToLink="{
                                preserveScroll: true,
                            }" type="primary" :label="trans('Match with this product')" size="xxs"
                            icon="fal fa-hand-pointer" />

                    </div>

                    <Button v-if="item.platform_possible_matches?.number_matches"
                        @click="() => { fetchRoute(), isOpenModal = true, selectedPortfolio = item }"
                        :label="trans('Choose another product from your shop')" :capitalize="false" size="xxs"
                        type="tertiary" />
                    <Button v-else @click="() => { fetchRoute(), isOpenModal = true, selectedPortfolio = item }"
                        :label="trans('Match it with an existing product in your shop')" :capitalize="false" size="xxs"
                        type="tertiary" />
                </template>

                <template v-else>

                    <template v-if="item.platform_product_data?.name">
                        <div class="flex gap-x-2 items-center">
                            <div v-if="item.platform_product_data?.images?.[0]?.src"
                                class="min-h-5 h-auto max-h-9 min-w-9 w-auto max-w-9 shadow border border-gray-300 rounded">
                                <img :src="item.platform_product_data?.images?.[0]?.src" />
                            </div>

                            <div>
                                <span class="mr-1">{{ item.platform_product_data?.name }}</span>
                            </div>
                        </div>
                    </template>


                    <Button class="mt-2" @click="() => (fetchRoute(), isOpenModal = true, selectedPortfolio = item)"
                        :label="trans('Connect with other product')" :capitalize="false" :icon="faRecycle" size="xxs"
                        type="tertiary" />

                </template>
            </template>
        </template>


        <!-- Column: actions -->
        <template #cell(actions)="{ item }">
            <div class="flex gap-2">
                <div v-if="item.customer_sales_channel_platform_status && !item.platform_status" class="flex gap-x-2 items-center">
                    <ButtonWithLink v-tooltip="trans('Will create new product')" :routeTarget="{
                        method: 'post',
                        name: props.routes.single_create_new.name,
                        parameters: {
                            portfolio: item.id
                        },
                    }" isWithError :label="trans('Create new product')" size="xs" :icon="faPlus" type="tertiary" :bindToLink="{
                    preserveScroll: true,
                }" />
                </div>

                <ButtonWithLink v-tooltip="trans('Unselect product')" type="negative" icon="fal fa-skull"
                    :routeTarget="item.update_portfolio" :body="{
                        'status': false,
                    }" size="xs" :bindToLink="{
                    preserveScroll: true,
                }" />
            </div>

        </template>
    </Table>


    <Modal :isOpen="isOpenModal" width="w-full max-w-2xl h-full max-h-[570px]" @close="isOpenModal = false">
        <div class="relative isolate">

            <div v-if="isLoadingSubmit"
                class="flex justify-center items-center text-7xl text-white absolute z-10 inset-0 bg-black/40">
                <LoadingIcon />
            </div>

            <div class="mb-2">
                <PureInput v-model="querySearchPortfolios" aupdate:modelValue="() => debounceGetPortfoliosList()"
                    :placeholder="trans('Input to search portfolios')" />
                <slot name="afterInput">
                </slot>
            </div>

            <div class="xh-full xmd:h-[570px] text-base font-normal">
                <div class="col-span-4 pb-8 md:pb-2 h-fit overflow-auto flex flex-col">
                    <div class="flex justify-between items-center">
                        <!-- <div class="font-semibold text-lg py-1">{{ trans("Result") }} ({{ locale?.number(portfoliosMeta?.total || 0) }})</div> -->

                    </div>
                    <div class="border-t border-gray-300 mb-1"></div>
                    <div class="h-full md:h-[400px] overflow-auto py-2 relative">
                        <!-- Products list -->
                        <div class="grid grid-cols-2 gap-3 pb-2">
                            <template v-if="resultOfFetchPlatformProduct?.length > 0">
                                <div v-for="(item, index) in resultOfFetchPlatformProduct" :key="index"
                                    @click="() => selectedVariant = item"
                                    class="relative h-fit rounded cursor-pointer p-2 flex flex-col md:flex-row gap-x-2 border"
                                    :class="[
                                        selectedVariant?.id === item.id ? 'bg-green-100 border-green-400' : ''
                                    ]">
                                    <Transition name="slide-to-right">
                                        <FontAwesomeIcon v-if="selectedVariant?.id === item.id" :icon="faCheckCircle"
                                            class="bottom-2 right-2 absolute text-green-500" fixed-width
                                            aria-hidden="true" />
                                    </Transition>
                                    <slot name="product" :item="item">
                                        <Image v-if="item.images?.src" :src="item.images?.src"
                                            class="w-16 h-16 overflow-hidden mx-auto md:mx-0 mb-4 md:mb-0" imageCover
                                            :alt="item.name" />
                                        <div class="flex flex-col justify-between">
                                            <div class="w-fit" xclick="() => selectProduct(item)">
                                                <div v-tooltip="trans('Name')"
                                                    class="w-fit font-semibold leading-none mb-1">
                                                    {{ item.name || 'no name' }}
                                                </div>
                                                <div v-if="!item.no_code" v-tooltip="trans('Code')"
                                                    class="w-fit text-xs text-gray-400 italic">
                                                    {{ item.code || 'no code' }}
                                                </div>
                                                <div v-if="item.reference" v-tooltip="trans('Reference')"
                                                    class="w-fit text-xs text-gray-400 italic">
                                                    {{ item.reference || 'no reference' }}
                                                </div>
                                                <div v-if="item.gross_weight" v-tooltip="trans('Weight')"
                                                    class="w-fit text-xs text-gray-400 italic">{{ item.gross_weight }}
                                                </div>
                                            </div>
                                            <div v-if="!item.no_price" xclick="() => selectProduct(item)"
                                                v-tooltip="trans('Price')" class="w-fit text-xs text-gray-x500">
                                                {{
                                                    locale?.currencyFormat(item.currency_code || 'usd', item.price || 0)
                                                }}
                                            </div>
                                        </div>
                                    </slot>
                                </div>
                            </template>
                            <div v-else class="text-center text-gray-500 col-span-3">
                                {{ trans("No products found") }}
                            </div>
                        </div>
                    </div>


                    <div class="mt-4">
                        <Button @click="() => onSubmitVariant()" label="Select as variant" type="primary" full
                            :loading="isLoadingSubmit" />
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>
