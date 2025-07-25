<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { computed, ref } from "vue"
import { routeType } from "@/types/route"
import { notify } from "@kyvg/vue3-notification"
import { faTrashAlt, faTools } from "@fal"
import { faCheckCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Portfolio } from "@/types/portfolio"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import Modal from "@/Components/Utils/Modal.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import axios from "axios"
import { debounce } from "lodash"

library.add(faTrashAlt, faTools, faCheckCircle)

interface ShopifyProduct {
    id: string // "gid://shopify/Product/12148498727252"
    title: string // "Aarhus Atomiser - Classic Pod - USB - Colour Change - Timer"
    handle: string // "aarhus-atomiser-classic-pod-usb-colour-change-timer"
    vendor: string // "AW-Dropship"
    images: {
        src: string
    }[] // []
}

const props = defineProps<{
    data: {}
    tab?: string
    customerSalesChannel:{}
}>()

const locale = useLocaleStore()

function itemRoute(portfolio: Portfolio) {
    return route(
        "grp.helpers.redirect_portfolio_item",
        [portfolio.id])


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

// Section: open modal to select variant
const isOpenModalVariant = ref(false)
const selectedPortfolio = ref<Portfolio | null>(null)
const isLoadingSubmit = ref(false)
const querySearchPortfolios = ref("")
const selectedVariant = ref<number | null>(null)
const onSubmitVariant = () => {
    console.log('qqq', selectedPortfolio.value)
    console.log('www', selectedVariant.value)

    if(!selectedPortfolio.value || !selectedVariant.value) {
        notify({
            title: trans("Something went wrong"),
            text: "",
            type: "error",
        })
    }

    // Section: Submit
    router.post(
        route('grp.models.portfolio.match_to_existing_shopify_product', {
            portfolio: selectedPortfolio.value?.id,
            shopify_product_id: selectedVariant.value?.id
        }),
        {
            // data: 'qqq'
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

                isOpenModalVariant.value = false
                setTimeout(() => {
                    selectedVariant.value = null
                    selectedPortfolio.value = null
                }, 700)

            },
            onError: errors => {
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
const filteredPortfolios = computed(() => {
    if (!querySearchPortfolios.value) {
        return selectedPortfolio.value?.platform_possible_matches?.raw_data
    }
    return selectedPortfolio.value?.platform_possible_matches?.raw_data.filter(portfolio => {
        return portfolio.title.toLowerCase().includes(querySearchPortfolios.value.toLowerCase())
    })
})

// Section: search in Shopify
const resultOfFetchShopifyProduct = ref<ShopifyProduct[]>([])
const isLoadingFetchShopifyProduct = ref(false)
const fetchRoute = async () => {
    isLoadingFetchShopifyProduct.value = true
    

    try {
        const www = await axios.get(route('grp.json.dropshipping.customer_sales_channel.shopify_products', {
            customerSalesChannel: props.customerSalesChannel?.id
        }))
        resultOfFetchShopifyProduct.value = www.data.products
        // console.log('qweqw', www)
    } catch (e) {
        console.error("Error processing products", e)
    }
    isLoadingFetchShopifyProduct.value = false

}
const debFetchShopifyProduct = debounce(() => fetchRoute(), 700)
</script>

<template>
    <!-- <pre>{{ data.data[0] }}</pre> -->
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(item_code)="{ item: portfolio }">
            <Link :href="itemRoute(portfolio)" class="primaryLink">
                {{ portfolio["item_code"] }}
            </Link>
        </template>


        <template #cell(location)="{ item: portfolio }">
            <AddressLocation :data="portfolio['location']" />
        </template>

        <template #cell(platform_status)="{ item: portfolio }">
            <FontAwesomeIcon v-if="portfolio.has_valid_platform_product_id" v-tooltip="trans('Has valid platform product id')" icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />
            <FontAwesomeIcon v-else v-tooltip="trans('Has valid platform product id')" icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
            <FontAwesomeIcon v-if="portfolio.exist_in_platform" v-tooltip="trans('Exist in platform')" icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />
            <FontAwesomeIcon v-else v-tooltip="trans('Exist in platform')" icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
            <FontAwesomeIcon v-if="portfolio.platform_status" v-tooltip="trans('Platform status')" icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />
            <FontAwesomeIcon v-else v-tooltip="trans('Platform status')" icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
        </template>

        <template #cell(created_at)="{ item: portfolio }">
            <div class="text-gray-500">{{ useFormatTime(portfolio["created_at"], {
                localeCode: locale.language.code,
                formatTime: "hm"
            }) }}
            </div>
        </template>

        <template #cell(action)="{ item: portfolio }">
            <Button @click="() => onDeletePortfolio(portfolio.routes.delete_route, portfolio.item_id)" :key="portfolio.item_id"
                    icon="fal fa-trash-alt" type="negative" :disabled="isDeleteLoading === portfolio.item_id"
                    :loading="isDeleteLoading === portfolio.item_id" />
        </template>

        <template #cell(matches)="{ item: portfolio }">
            <div v-if=" portfolio.customer_sales_channel_platform_status &&  !portfolio.platform_status  && portfolio.platform_possible_matches?.number_matches"  class="flex gap-x-2 items-center">
                <div class="min-h-5 h-auto max-h-9 min-w-9 w-auto max-w-9 shadow">
                    <img :src="portfolio.platform_possible_matches?.raw_data?.[0]?.images?.[0]?.src" />
                </div>

                <div>
                    <span class="mr-1">{{ portfolio.platform_possible_matches?.matches_labels[0]}}</span>
                    <ButtonWithLink
                        v-if="portfolio.platform_possible_matches?.number_matches"
                        v-tooltip="trans('Match to existing Shopify product')"
                        :routeTarget="{
                            method: 'post',
                            name: 'grp.models.portfolio.match_to_existing_shopify_product',
                            parameters: {
                                portfolio: portfolio.id,
                                shopify_product_id: portfolio.platform_possible_matches.raw_data?.[0]?.id
                            }
                        }"
                        :bindToLink="{
                            preserveScroll: true,
                        }"
                        type="secondary"
                        :label="trans('Quick match')"
                        size="xxs"
                        icon="fal fa-tools"
                    />
                    
                    <Button
                        v-if="portfolio.platform_possible_matches?.number_matches"
                        @click="() => (isOpenModalVariant = true, selectedPortfolio = portfolio)"
                        :label="trans('Open match list')"
                        size="xxs"
                        type="tertiary"
                    />
                </div>
            </div>

            <!-- <pre>{{ portfolio.platform_possible_matches.number_matches }}</pre> -->
            <!-- <br />
            <br />

            <pre>{{ portfolio.id }}</pre> -->
        </template>

        
        <!-- Column: actions -->
        <template #cell(actions)="{ item: portfolio }">
            <div v-if="portfolio.customer_sales_channel_platform_status"  class="flex gap-x-2 items-center">
                <ButtonWithLink
                    v-tooltip="trans('Will create new product in Shopify')"
                    :routeTarget="{
                    method: 'post',
                        name: 'grp.models.portfolio.store_new_shopify_product',
                        parameters: {
                            portfolio: portfolio.id
                        },
                    }"
                    isWithError
                    icon=""
                    :label="trans('Create new product')"
                    size="xxs"
                    type="tertiary"
                    :bindToLink="{
                        preserveScroll: true,
                    }"
                />
            </div>
        </template>
    </Table>

    <Modal :isOpen="isOpenModalVariant" width="w-full max-w-2xl h-full max-h-[570px]" @close="isOpenModalVariant = false">
        <div class="relative isolate">

            <div v-if="isLoadingSubmit"
                 class="flex justify-center items-center text-7xl text-white absolute z-10 inset-0 bg-black/40">
                <LoadingIcon />
            </div>

            <div class="mb-2">
                <PureInput
                    v-model="querySearchPortfolios"
                    @update:modelValue="() => debFetchShopifyProduct()"
                    :placeholder="trans('Search in :platform', { platform: 'Shopify' })"
                />
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
                         <!-- {{ selectedVariant }} -->
                           
                        <div v-if="querySearchPortfolios" class="min-h-24 relative mb-4 pb-4  p-2 border-b border-indigo-300 grid grid-cols-2 gap-3 pr-2">
                            <template v-if="resultOfFetchShopifyProduct?.length">
                                <div
                                    v-for="(item, index) in resultOfFetchShopifyProduct"
                                    :key="index"
                                    @click="() => selectedVariant = item"
                                    class="relative h-fit rounded cursor-pointer p-2 flex flex-col md:flex-row gap-x-2 border"
                                    :class="[
                                        selectedVariant?.id === item.id ? 'bg-green-100 border-green-400' : ''
                                    ]"
                                >
                                    <Transition name="slide-to-right">
                                        <FontAwesomeIcon v-if="selectedVariant?.id === item.id"
                                            icon="fas fa-check-circle"
                                            class="-top-2 -right-2 absolute text-green-500" fixed-width
                                            aria-hidden="true"
                                        />
                                    </Transition>
                                    <slot name="product" :item="item">
                                        <!-- <Image v-if="item.image" :src="item.image?.[0]?.src"
                                            class="w-16 h-16 overflow-hidden mx-auto md:mx-0 mb-4 md:mb-0" imageCover
                                            :alt="item.name"/> -->
                                        <div class="min-h-3 h-auto max-h-9 min-w-9 w-auto max-w-9">
                                            <img :src="item.images?.[0]?.src" class="shadow" />
                                        </div>
                                        <div class="flex flex-col justify-between">
                                            <div class="w-fit" xclick="() => selectProduct(item)">
                                                <div v-if="item.title" v-tooltip="trans('Name')" class="w-fit text-sm font-semibold leading-none mb-1">
                                                    {{ item.title || 'no title' }}
                                                </div>
                                                <div v-if="item.name" v-tooltip="trans('Name')" class="w-fit font-semibold leading-none mb-1">
                                                    {{ item.name || 'no name' }}
                                                </div>
                                                <div v-if="item.no_code" v-tooltip="trans('Code')"
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
                                            <!-- <div v-if="!item.no_price" xclick="() => selectProduct(item)"
                                                    v-tooltip="trans('Price')" class="w-fit text-xs text-gray-x500">
                                                {{
                                                    locale?.currencyFormat(item.currency_code || 'usd', item.price || 0)
                                                }}
                                            </div> -->
                                        </div>
                                    </slot>
                                </div>
                            </template>

                            <div v-if="isLoadingFetchShopifyProduct" class="bg-black/50 text-2xl text-white inset-0 absolute flex items-center justify-center">
                                <LoadingIcon />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 pb-2 pr-2">
                            <template v-if="selectedPortfolio?.platform_possible_matches?.number_matches">
                                <div
                                    v-for="(item, index) in selectedPortfolio?.platform_possible_matches?.raw_data"
                                    :key="index"
                                    @click="() => selectedVariant = item"
                                    class="relative h-fit rounded cursor-pointer p-2 flex flex-col md:flex-row gap-x-2 border"
                                    :class="[
										selectedVariant?.id === item.id ? 'bg-green-100 border-green-400' : ''
									]"
                                >
                                    <Transition name="slide-to-right">
                                        <FontAwesomeIcon v-if="selectedVariant?.id === item.id"
                                            icon="fas fa-check-circle"
                                            class="-top-2 -right-2 absolute text-green-500" fixed-width
                                            aria-hidden="true"
                                        />
                                    </Transition>
                                    <slot name="product" :item="item">
                                        <!-- <Image v-if="item.image" :src="item.image?.[0]?.src"
                                            class="w-16 h-16 overflow-hidden mx-auto md:mx-0 mb-4 md:mb-0" imageCover
                                            :alt="item.name"/> -->

                                        <div class="min-h-3 h-auto max-h-9 min-w-9 w-auto max-w-9">
                                            <img :src="item.images?.[0]?.src" class="shadow" />
                                        </div>

                                        <div class="flex flex-col justify-between">
                                            <div class="w-fit" xclick="() => selectProduct(item)">
                                                <div v-if="item.title" v-tooltip="trans('Name')" class="w-fit text-sm font-semibold leading-none mb-1">
                                                    {{ item.title || 'no title' }}
                                                </div>
                                                <div v-if="item.name" v-tooltip="trans('Name')" class="w-fit font-semibold leading-none mb-1">
                                                    {{ item.name || 'no name' }}
                                                </div>
                                                <div v-if="item.no_code" v-tooltip="trans('Code')"
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
                                            <!-- <div v-if="!item.no_price" xclick="() => selectProduct(item)"
                                                 v-tooltip="trans('Price')" class="w-fit text-xs text-gray-x500">
                                                {{
                                                    locale?.currencyFormat(item.currency_code || 'usd', item.price || 0)
                                                }}
                                            </div> -->
                                        </div>
                                    </slot>
                                </div>
                            </template>
                            <div v-else class="text-center text-gray-500 col-span-3">
                                {{ trans("No products found") }}
                            </div>
                        </div>

                        
                    </div>
                    <!-- Pagination -->
                    <!-- <Pagination
                        v-if="portfoliosMeta"
                        :on-click="getPortfoliosList"
                        :has-data="true"
                        :meta="portfoliosMeta"
                        xexportLinks="queryBuilderProps.exportLinks"
                        :per-page-options="[]"
                        xon-per-page-change="onPerPageChange"
                    /> -->

                    <div class="mt-4">
                        <Button
                            @click="() => onSubmitVariant()"
                            xdisabled="selectedProduct.length < 1"
                            xv-tooltip="selectedProduct.length < 1 ? trans('Select at least one product') : ''"
                            xlabel="submitLabel ?? `${trans('Add')} ${selectedProduct.length}`"
                            :label="trans('Match the product')"
                            type="primary"
                            full
                            xicon="fas fa-plus"
                            :loading="isLoadingSubmit"
                        />
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>
