<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Link, router} from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import {Product} from "@/types/product"
import {library} from "@fortawesome/fontawesome-svg-core"
import {inject, onMounted, ref, computed} from "vue"
import {trans} from "laravel-vue-i18n"
import {aikuLocaleStructure} from "@/Composables/useLocaleStructure"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome"
import Image from "@/Components/Image.vue"
import {debounce, get, set} from "lodash-es"
import ConditionIcon from "@/Components/Utils/ConditionIcon.vue"
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
    faLink, faScrewdriver, faTools
} from "@fal"
import {faStar, faFilter} from "@fas"
import {faExclamationTriangle as fadExclamationTriangle} from "@fad"
import {faCheck} from "@far"
import Button from "@/Components/Elements/Buttons/Button.vue"
import {retinaLayoutStructure} from "@/Composables/useRetinaLayoutStructure"
import {notify} from "@kyvg/vue3-notification"
import Modal from "@/Components/Utils/Modal.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import Tag from '@/Components/Tag.vue'
import axios from "axios"

library.add(fadExclamationTriangle, faSyncAlt, faConciergeBell, faGarage, faExclamationTriangle, faPencil, faSearch, faThLarge, faListUl, faStar, faFilter, falStar, faTrashAlt, faCheck, faExclamationCircle, faClone, faLink, faScrewdriver, faTools)

interface PlatformData {
    id: number
    code: string
    name: string
    type: string
}

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
    selectedData: {
        products: number[]
    }

    platform_data: PlatformData
    platform_user_id: number
    is_platform_connected: boolean
    progressToUploadToShopify: {}
    isPlatformManual?: boolean
    customerSalesChannel: {}
}>()

function portfolioRoute(product: Product) {
    if (product.type == "StoredItem") {
        return route("retina.fulfilment.itemised_storage.stored_items.show", [product.slug])
    }

    return route("retina.dropshipping.customer_sales_channels.portfolios.show",
        [
            route().params['customerSalesChannel'], product.id])

}

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', retinaLayoutStructure)

// const selectedProducts = ref<Product[]>([])
const onUnchecked = (itemId: number) => {
    props.selectedData.products = props.selectedData.products.filter(product => product !== itemId)
}

const selectSocketiBasedPlatform = (porto: { id: number }) => {
    if (props.platform_data.type === 'shopify') {
        return {
            event: `shopify.${props.platform_user_id}.upload-product.${porto.id}`,
            action: '.shopify-upload-progress'
        }
    } else if (props.platform_data.type === 'woocommerce') {
        return {
            event: `woo.${props.platform_user_id}.upload-product.${porto.id}`,
            action: '.woo-upload-progress'
        }
    } else if (props.platform_data.type === 'ebay') {
        return {
            event: `ebay.${props.platform_user_id}.upload-product.${porto.id}`,
            action: '.ebay-upload-progress'
        }
    } else if (props.platform_data.type === 'amazon') {
        return {
            event: `amazon.${props.platform_user_id}.upload-product.${porto.id}`,
            action: '.amazon-upload-progress'
        }
    } else if (props.platform_data.type === 'magento') {
        return {
            event: `magento.${props.platform_user_id}.upload-product.${porto.id}`,
            action: '.magento-upload-progress'
        }
    }
}

const debReloadPage = debounce(() => {
    router.reload({
        except: ['auth', 'breadcrumbs', 'flash', 'layout', 'localeData', 'pageHead', 'ziggy']
    })
}, 1200)

onMounted(() => {
    props.data?.data?.forEach(porto => {
        if (selectSocketiBasedPlatform(porto)) {
            const xxx = window.Echo.private(selectSocketiBasedPlatform(porto)?.event).listen(
                selectSocketiBasedPlatform(porto)?.action,
                (eventData) => {
                    console.log('socket in: ', porto.id, eventData)
                    if (eventData.errors_response) {
                        set(props.progressToUploadToShopify, [porto.id], 'error')
                        setTimeout(() => {
                            set(props.progressToUploadToShopify, [porto.id], null)
                        }, 3000);

                    } else {
                        set(props.progressToUploadToShopify, [porto.id], 'success')
                        debReloadPage()
                    }
                }
            );

            console.log(`Subscription porto id: ${porto.id}`, xxx)

        }
    });
})

// Table: Filter out-of-stock and discontinued
const compTableFilterStatus = computed(() => {
    return layout.currentQuery?.[`${props.tab}_filter`]?.status
})
const isLoadingTable = ref<null | string>(null)
const onClickFilterOutOfStock = (query: string) => {
    let xx: string | null = ''
    if (compTableFilterStatus.value === query) {
        xx = null
    } else {
        xx = query
    }

    router.reload(
        {
            data: {[`${props.tab}_filter[status]`]: xx},  // Sent to url parameter (?tab=showcase, ?tab=menu)
            // only: [tabSlug],  // Only reload the props with dynamic name tabSlug (i.e props.showcase, props.menu)
            onStart: () => {
                isLoadingTable.value = query || null
            },
            onSuccess: () => {
            },
            onFinish: (e) => {
                isLoadingTable.value = null
            },
            onError: (e) => {
            }
        }
    )
}

// Section: Modal Shopify select variant
const isOpenModal = ref(false)
const selectedPortfolio = ref(null)
const isLoadingSubmit = ref(false)
const querySearchPortfolios = ref('')
const filteredPortfolios = computed(() => {
    if (!querySearchPortfolios.value) {
        return selectedPortfolio.value?.platform_possible_matches
    }
    return selectedPortfolio.value?.platform_possible_matches.filter(portfolio => {
        return portfolio.name.toLowerCase().includes(querySearchPortfolios.value.toLowerCase())
            || portfolio.code.toLowerCase().includes(querySearchPortfolios.value.toLowerCase())
    })
})
const selectedVariant = ref<Product | null>(null)
const onSubmitVariant = () => {
    console.log(selectedVariant.value)

    isOpenModal.value = false
    selectedVariant.value = null
    selectedPortfolio.value = null

    // Section: Submit
    // router.post(
    // 	'xxxx',
    // 	{
    // 		data: 'qqq'
    // 	},
    // 	{
    // 		preserveScroll: true,
    // 		preserveState: true,
    // 		onStart: () => {
    // 			isLoadingSubmit.value = true
    // 		},
    // 		onSuccess: () => {
    // 			notify({
    // 				title: trans("Success"),
    // 				text: trans("Successfully submit the data"),
    // 				type: "success"
    // 			})
    // 		},
    // 		onError: errors => {
    // 			notify({
    // 				title: trans("Something went wrong"),
    // 				text: trans("Failed to set location"),
    // 				type: "error"
    // 			})
    // 		},
    // 		onFinish: () => {
    // 			isLoadingSubmit.value = false
    // 		},
    // 	}
    // )
}

const resultOfFetchShopifyProduct = ref<ShopifyProduct[]>([])
const isLoadingFetchShopifyProduct = ref(false)
const fetchRoute = async () => {
    isLoadingFetchShopifyProduct.value = true
    

    try {
        const www = await axios.get(route('retina.json.dropshipping.customer_sales_channel.shopify_products', {
            customerSalesChannel: props.customerSalesChannel?.id,
            query: querySearchPortfolios.value
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
    <Table
        :resource="data"
        :name="tab"
        class="mt-5"
        xxisCheckBox
        xxdisabledCheckbox="(xxx) => !!xxx.platform_product_id || xxx.platform == 'manual'"
        @onChecked="(item) => {
			console.log('onChecked', item)
			props.selectedData.products.push(item.id)
		}"
        @onUnchecked="(item) => {
			onUnchecked(item.id)
		}"
        :isChecked="(item) => props.selectedData.products.includes(item.id)"
        :rowColorFunction="(item) => {
			if (!isPlatformManual && is_platform_connected && !item.platform_product_id && get(progressToUploadToShopify, [item.id], undefined) != 'success') {
				return 'bg-yellow-50'
			} else {
				return ''
			}
		}"
        :isParentLoading="!!isLoadingTable"
    >
        <template #add-on-button>
            <Button
                @click="onClickFilterOutOfStock('out-of-stock')"
                v-tooltip="trans('Filter the product that out of stock')"
                label="Out of stock"
                size="xs"
                :key="compTableFilterStatus"
                :type="compTableFilterStatus === 'out-of-stock' ? 'secondary' : 'tertiary'"
                :icon="compTableFilterStatus === 'out-of-stock' ? 'fas fa-filter' : 'fal fa-filter'"
                iconRight="fal fa-exclamation-triangle"
                :loading="isLoadingTable == 'out-of-stock'"
            />
            <Button
                @click="onClickFilterOutOfStock('discontinued')"
                v-tooltip="trans('Filter the product that discontinued')"
                label="Discontinued"
                size="xs"
                :key="compTableFilterStatus"
                :type="compTableFilterStatus === 'discontinued' ? 'secondary' : 'tertiary'"
                :icon="compTableFilterStatus === 'discontinued' ? 'fas fa-filter' : 'fal fa-filter'"
                iconRight="fal fa-times"
                :loading="isLoadingTable == 'discontinued'"
            />
        </template>

        <template #cell(image)="{ item: product }">
            <div class="overflow-hidden w-10 h-10">
                <Image :src="product.image" :alt="product.name"/>
            </div>
        </template>

        <template #cell(name)="{ item: product }">
            <Link :href="portfolioRoute(product)" class="primaryLink whitespace-nowrap">
                {{ product["code"] }}
            </Link>
            <div class="text-base font-semibold">
                {{ product["name"] }}
            </div>
            <div class="text-sm text-gray-500 italic flex gap-x-10 gap-y-2">
                <div>
                    {{ trans("Stocks:") }} {{ locale.number(product.quantity_left) }}
                </div>
                <div>
                    {{ trans("Weight:") }} {{ locale.number(product.weight / 1000) }} kg
                </div>
            </div>

            <div class="text-sm text-gray-500 italic flex gap-x-10 gap-y-2">
                <div>
                    {{ trans("Price:") }} {{ locale.currencyFormat(product.currency_code, product.price) }}
                </div>
                <div>
                    {{ trans("RRP:") }} {{ locale.currencyFormat(product.currency_code, product.customer_price) }}
                </div>
            </div>

            <!-- Section: is code exist in platform -->
            <div v-if="product.is_code_exist_in_platform" class="text-xs text-amber-500">
                <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="" fixed-width aria-hidden="true"/>
                <span class="pr-2">{{
                        trans("We found same product in your shop, do you want to create new or use existing?")
                    }}</span>
                <!-- <Button v-tooltip="trans('Will create new product in :platform', {platform: props.platform_data.name})"
                        label="Create new" icon="fal fa-plus" type="tertiary" size="xxs"/> -->
                <span class="px-2 text-gray-500">or</span>
                <Button
                    v-tooltip="trans('Will sync the product and prioritize our product', {platform: props.platform_data.name})"
                    label="Use Existing" icon="fal fa-sync-alt"
                    :disabled="data?.product_availability?.options === 'use_existing'"
                    :type="data?.product_availability?.options === 'use_existing' ? 'primary' : 'tertiary'" size="xxs"/>
            </div>
        </template>

        <!-- Column: Status (repair) -->
        <template #cell(status)="{ item }">
            <div class="whitespace-nowrap">
                <FontAwesomeIcon v-if="item.has_valid_platform_product_id" v-tooltip="trans('Has valid platform product id')" icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-else v-tooltip="trans('Has valid platform product id')" icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-if="item.exist_in_platform" v-tooltip="trans('Exist in platform')" icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-else v-tooltip="trans('Exist in platform')" icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-if="item.platform_status" v-tooltip="trans('Platform status')" icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-else v-tooltip="trans('Platform status')" icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
            </div>

            <!-- <div class="flex justify-center">
				<FontAwesomeIcon
					v-if="(item.has_valid_platform_product_id && item.exist_in_platform && item.platform_status)"
					v-tooltip="trans('Uploaded to platform')" icon="far fa-check" class="text-green-500" fixed-width
					aria-hidden="true"/>
				<ConditionIcon v-else-if="get(progressToUploadToShopify, [item.id], null)"
					:state="get(progressToUploadToShopify, [item.id], undefined)"
					class="text-xl mx-auto"/>
            </div> -->
        </template>

        <!-- Column: Actions (connect) -->
        <template #cell(actions)="{ item }">
            <template v-if="(item.customer_sales_channel_platform_status &&  !item.platform_status)">
                <div v-if="item.platform_possible_matches?.number_matches"  class="flex gap-x-2 items-center">
                    <div class="min-h-5 h-auto max-h-9 min-w-9 w-auto max-w-9 shadow">
                        <img :src="item.platform_possible_matches?.raw_data?.[0]?.images?.[0]?.src" />
                    </div>
                    <div>
                        <span class="mr-1">{{ item.platform_possible_matches?.matches_labels[0]}}</span>
                    </div>
                </div>
                
                <ButtonWithLink
                    v-if="item.platform_possible_matches?.number_matches"
                    v-tooltip="trans('Match to existing Shopify product')"
                    :routeTarget="{
                        method: 'post',
                            name: 'retina.models.portfolio.match_to_existing_shopify_product',
                            parameters: {
                                portfolio: item.id,
                                shopify_product_id: item.platform_possible_matches.raw_data?.[0]?.id
                            }
                        }"
                    :bindToLink="{
                        preserveScroll: true,
                    }"
                    type="secondary"
                    :label="trans('Match with this product')"
                    size="xxs"
                    icon="fal fa-tools"
                />
                
                <Button
                    xv-if="portfolio.platform_possible_matches?.number_matches"
                    @click="() => (fetchRoute(), isOpenModal = true, selectedPortfolio = item)"
                    :label="trans('Select other product from Shopify')"
                    :capitalize="false"
                    size="xxs"
                    type="tertiary"
                />
            </template>

            <div v-else-if="item.matched_product?.label">
                <div v-tooltip="trans('Matched product')" class="flex gap-x-2 items-center border-l-2 border-green-500 bg-green-50 py-1 px-2">
                    <div class="min-h-5 h-auto max-h-9 min-w-9 w-auto max-w-9 shadow">
                        <img :src="item.matched_product?.img" />
                    </div>
                    <div>
                        <span class="mr-1">{{ item.matched_product?.label }}</span>
                    </div>
                </div>
            </div>

        </template>

        <!-- Column: Actions 2 (Modal shopify) -->
        <template #cell(actions2)="{ item }">
            <!-- <template v-if="!(!item.has_valid_platform_product_id && !item.exist_in_platform && !item.platform_status && (get(progressToUploadToShopify, [item.id], undefined) != 'success' && get(progressToUploadToShopify, [item.id], undefined) != 'loading'))">
				<Button
					v-if="(!item.has_valid_platform_product_id || !item.exist_in_platform || !item.platform_status) && item.platform_possible_matches.length"
					@click="isOpenModal = true, selectedPortfolio = item"
					label="Modal Shopify"
					type="tertiary"
				/>
			</template> -->
        </template>

        <!-- Column: Actions 3 -->
        <template #cell(actions3)="{ item }">
            <div v-if="item.customer_sales_channel_platform_status  && !item.platform_status "  class="flex gap-x-2 items-center">
                <ButtonWithLink
                    v-tooltip="trans('Will create new product in Shopify')"
                    :routeTarget="{
                    method: 'post',
                        name: 'retina.models.portfolio.store_new_shopify_product',
                        parameters: {
                            portfolio: item.id
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

            <ButtonWithLink
                v-tooltip="trans('Unselect product')"
                type="negative"
                icon="fal fa-skull"
                :routeTarget="item.update_portfolio"
                :body="{
						'status': false,
					}"
                size="xs"
                :bindToLink="{
						preserveScroll: true,
					}"
            />
        </template>
    </Table>

    <!-- <pre>{{ data.data[0] }}</pre> -->


    <Modal :isOpen="isOpenModal" width="w-full max-w-2xl h-full max-h-[570px]" @close="isOpenModal = false">
        <div class="relative isolate">

            <div v-if="isLoadingSubmit"
                 class="flex justify-center items-center text-7xl text-white absolute z-10 inset-0 bg-black/40">
                <LoadingIcon />
            </div>

            <div class="mb-2 relative">
                <PureInput
                    v-model="querySearchPortfolios"
                    @update:modelValue="() => debFetchShopifyProduct()"
                    :placeholder="trans('Search in :platform', { platform: 'Shopify' })"
                />
                <div v-if="isLoadingFetchShopifyProduct" class="absolute right-2 text-xl top-1/2 -translate-y-1/2">
                    <LoadingIcon />
                </div>
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
                           
                        <div v-if="querySearchPortfolios || resultOfFetchShopifyProduct?.length" class="min-h-24 relative mb-4 pb-4  p-2 xborder-b xborder-indigo-300 grid grid-cols-2 gap-3 pr-2">
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

                            <div v-else class="text-center text-gray-500 col-span-3">
                                {{ trans("No products found") }}
                            </div>
                            <div v-if="isLoadingFetchShopifyProduct" class="bg-black/50 text-2xl text-white inset-0 absolute flex items-center justify-center">
                                <LoadingIcon />
                            </div>
                        </div>

                        <div class="text-center text-gray-500" v-else>
                            Start typing to search for products in Shopify
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

