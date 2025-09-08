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
    faRecycle, faHandPointer, faHandshakeSlash, faHandshake
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
import axios from "axios"
import {routeType} from "@/types/route";

library.add(faHandshake, faHandshakeSlash, faHandPointer, fadExclamationTriangle, faSyncAlt, faConciergeBell, faGarage, faExclamationTriangle, faPencil, faSearch, faThLarge, faListUl, faStar, faFilter, falStar, faTrashAlt, faCheck, faExclamationCircle, faClone, faLink, faScrewdriver, faTools)

interface PlatformData {
    id: number
    code: string
    name: string
    type: string
}

interface PlatformProduct {
    id: string // "gid://shopify/Product/12148498727252"
    name: string // "Aarhus Atomiser - Classic Pod - USB - Colour Change - Timer"
    slug: string // "aarhus-atomiser-classic-pod-usb-colour-change-timer"
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
    routes: {
        batch_upload: routeType
        batch_match: routeType
        fetch_products: routeType
        single_create_new: routeType
        single_match: routeType
    }
    platform_data: PlatformData
    platform_user_id: number
    is_platform_connected: boolean
    route_match: routeType
    route_create_new: routeType
    progressToUploadToShopify: {}
    isPlatformManual?: boolean
    customerSalesChannel: {}
    useCheckBox?: boolean
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

    /* selectedVariant.value = null
    selectedPortfolio.value = null */

    /* Section: Submit */
    router.post(
        route(props.routes.single_match.name, {
            portfolio: selectedPortfolio.value?.id,
            platform_product_id: selectedVariant.value?.id
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

                isOpenModal.value = false
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

const resultOfFetchPlatformProduct = ref<PlatformProduct[]>([])
const isLoadingFetchPlatformProduct = ref(false)
const fetchRoute = async () => {
    isLoadingFetchPlatformProduct.value = true


    try {
        const www = await axios.get(route(props.routes.fetch_products.name, {
            customerSalesChannel: props.customerSalesChannel?.id,
            query: querySearchPortfolios.value
        }))

        resultOfFetchPlatformProduct.value = www.data
        // console.log('qweqw', www)
    } catch (e) {
        console.error("Error processing products", e)
    }
    isLoadingFetchPlatformProduct.value = false

}
const debFetchShopifyProduct = debounce(() => fetchRoute(), 700)


const selectedProducts = defineModel<number[]>('selectedProducts')

const onChangeCheked = (checked: boolean, item: DeliveryNote) => {
    if (!selectedProducts.value) return

    if (checked) {
        if (!selectedProducts.value.includes(item.id)) {
            selectedProducts.value.push(item.id)
        }
    } else {
        selectedProducts.value = selectedProducts.value.filter(id => id != item.id)
    }
}

const onCheckedAll = ({data, allChecked}) => {
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
           @onChecked="(item) => onChangeCheked(true, item)"
           @onUnchecked="(item) => onChangeCheked(false, item)" @onCheckedAll="(data) => onCheckedAll(data)"
           checkboxKey='id' :isChecked="(item) => selectedProducts.includes(item.id)"
           :disabledCheckbox="(item)=>onDisableCheckbox(item)" :rowColorFunction="(item) => {
			if (!isPlatformManual && is_platform_connected && !item.platform_product_id && get(progressToUploadToShopify, [item.id], undefined) != 'success') {
				return 'bg-yellow-50'
			} else {
				return ''
			}
		}" :isParentLoading="!!isLoadingTable">

        <template #header-checkbox="data">
            <div></div>
        </template>

        <template #disable-checkbox>
            <div></div>
        </template>

        <template #add-on-button>
            <Button @click="onClickFilterOutOfStock('out-of-stock')"
                    v-tooltip="trans('Filter the product that out of stock')" label="Out of stock" size="xs"
                    :key="compTableFilterStatus"
                    :type="compTableFilterStatus === 'out-of-stock' ? 'secondary' : 'tertiary'"
                    :icon="compTableFilterStatus === 'out-of-stock' ? 'fas fa-filter' : 'fal fa-filter'"
                    iconRight="fal fa-exclamation-triangle" :loading="isLoadingTable == 'out-of-stock'"/>
            <Button @click="onClickFilterOutOfStock('discontinued')"
                    v-tooltip="trans('Filter the product that discontinued')" label="Discontinued" size="xs"
                    :key="compTableFilterStatus"
                    :type="compTableFilterStatus === 'discontinued' ? 'secondary' : 'tertiary'"
                    :icon="compTableFilterStatus === 'discontinued' ? 'fas fa-filter' : 'fal fa-filter'"
                    iconRight="fal fa-times" :loading="isLoadingTable == 'discontinued'"/>
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
                <Button v-tooltip="trans('Will create new product in :platform', {platform: props.platform_data.name})"
                        label="Create new" icon="fal fa-plus" type="tertiary" size="xxs"/>
                <span class="px-2 text-gray-500">or</span>
                <Button
                    v-tooltip="trans('Will sync the product and prioritize our product', {platform: props.platform_data.name})"
                    label="Use Existing" icon="fal fa-sync-alt"
                    :disabled="data?.product_availability?.options === 'use_existing'"
                    :type="data?.product_availability?.options === 'use_existing' ? 'primary' : 'tertiary'"
                    size="xxs"/>
            </div>
        </template>

        <!-- Column: Status (repair) -->
        <template #cell(status)="{ item }">
            <div class="whitespace-nowrap">
                <FontAwesomeIcon v-if="item.has_valid_platform_product_id"
                                 v-tooltip="trans('Has valid platform product id')" icon="fal fa-check"
                                 class="text-green-500"
                                 fixed-width aria-hidden="true"/>
                <FontAwesomeIcon v-else v-tooltip="trans('Has valid platform product id')" icon="fal fa-times"
                                 class="text-red-500" fixed-width aria-hidden="true"/>
                <FontAwesomeIcon v-if="item.exist_in_platform" v-tooltip="trans('Exist in platform')"
                                 icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true"/>
                <FontAwesomeIcon v-else v-tooltip="trans('Exist in platform')" icon="fal fa-times" class="text-red-500"
                                 fixed-width aria-hidden="true"/>
                <FontAwesomeIcon v-if="item.platform_status" v-tooltip="trans('Platform status')" icon="fal fa-check"
                                 class="text-green-500" fixed-width aria-hidden="true"/>
                <FontAwesomeIcon v-else v-tooltip="trans('Platform status')" icon="fal fa-times" class="text-red-500"
                                 fixed-width aria-hidden="true"/>
            </div>


        </template>

        <!-- Column: Actions (connect) -->
        <template #cell(matches)="{ item }">
            <template v-if="item.customer_sales_channel_platform_status">
                <template v-if="!item.platform_status">

                    <div v-if="item.platform_possible_matches?.number_matches" class="border  rounded p-1"
                         :class="selectedProducts?.includes(item.id) ? 'bg-green-200 border-green-400' : 'border-gray-300'">
                        <div class="flex gap-x-2 items-center border border-gray-300 rounded p-1">
                            <div v-if="item.platform_possible_matches?.raw_data?.[0].images?.[0]?.src"
                                 class="min-h-5 h-auto max-h-9 min-w-9 w-auto max-w-9 shadow border border-gray-300 rounded">
                                <img :src="item.platform_possible_matches?.raw_data?.[0]?.images?.[0]?.src"/>
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
                                        icon="fal fa-hand-pointer"/>

                    </div>

                    <Button v-if="item.platform_possible_matches?.number_matches"
                            @click="() => (fetchRoute(), isOpenModal = true, selectedPortfolio = item)"
                            :label="trans('Choose another product from your shop')" :capitalize="false" size="xxs"
                            type="tertiary"/>
                    <Button v-else @click="() => (fetchRoute(), isOpenModal = true, selectedPortfolio = item)"
                            :label="trans('Match it with an existing product in your shop')" :capitalize="false"
                            size="xxs"
                            type="tertiary"/>
                </template>

                <template v-else>

                    <template v-if="item.platform_product_data?.name">
                        <div class="flex gap-x-2 items-center">
                            <div v-if="item.platform_product_data?.images?.[0]?.src"
                                 class="min-h-5 h-auto max-h-9 min-w-9 w-auto max-w-9 shadow border border-gray-300 rounded">
                                <img :src="item.platform_product_data?.images?.[0]?.src"/>
                            </div>

                            <div>
                                <span class="mr-1">{{ item.platform_product_data?.name }}</span>
                            </div>
                        </div>
                    </template>


                    <Button class="mt-2" @click="() => (fetchRoute(), isOpenModal = true, selectedPortfolio = item)"
                            :label="trans('Connect with other product')" :capitalize="false" :icon="faRecycle"
                            size="xxs"
                            type="tertiary"/>

                </template>
            </template>

            <!--  <div class="mx-auto flex flex-wrap justify-center gap-2">

                <ButtonWithLink
					v-if="
						!item.has_valid_platform_product_id &&
						!item.exist_in_platform &&
						!item.platform_status &&
						(get(progressToUploadToShopify, [item.id], undefined) != 'success' && get(progressToUploadToShopify, [item.id], undefined) != 'loading')
					"
                    :routeTarget="item.platform_upload_portfolio"
                    :label="trans('Connect')"
                    icon="fal fa-upload"
                    type="positive"
                    size="xs"
                    :bindToLink="{
						preserveScroll: true,
					}"
                    @success="() => set(progressToUploadToShopify, [item.id], 'loading')"
                    :disabled="get(progressToUploadToShopify, [item.id], null)"
                />

                <template v-else>
                    <div v-if="item.platform_possible_matches?.number_matches && (!item.has_valid_platform_product_id || !item.exist_in_platform || !item.platform_status)" class="w-full flex gap-2 items-center">
                        <div class="min-h-5 h-auto max-h-9 min-w-9 w-auto max-w-9 shadow overflow-hidden">
                            <img :src="item.platform_possible_matches?.raw_data?.[0]?.images?.[0]?.src" :alt="item.platform_possible_matches?.matches_labels[0]" />
                        </div>

                        <div>
                            <span class="mr-1">{{ item.platform_possible_matches?.matches_labels[0] }}</span>
                            <ButtonWithLink
                                v-if="item.platform_possible_matches?.number_matches === 1"
                                v-tooltip="trans('Upload product to :platform (matching)', {platform: props.platform_data.name})"
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
                                type="tertiary"
                                :label="trans('Repair')"
                                size="xxs"
                                icon="fal fa-tools"
                            />

                            <Button
                                v-else
                                @click="() => (isOpenModal = true, selectedPortfolio = item)"
                                :label="trans('Open match list')"
                                size="xxs"
                                type="tertiary"
                            />
                        </div>
                    </div>
				</template>


            </div> -->
        </template>

        <!-- Column: Actions 2 (Modal shopify) -->
        <template #cell(create_new)="{ item }">
            <div v-if="item.customer_sales_channel_platform_status  && !item.platform_status "
                 class="flex gap-x-2 items-center">
                <ButtonWithLink
                    v-tooltip="trans('Will create new product in :platform', {platform: props.platform_data.name})"
                    :routeTarget="{
                    method: 'post',
                        name: props.routes.single_create_new.name,
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
        </template>

        <!-- Column: Actions 3 -->
        <template #cell(delete)="{ item }">
            <ButtonWithLink v-tooltip="trans('Unselect product. This will not remove the product from :platform', {platform: props.platform_data.name})" type="negative" icon="fal fa-skull"
                            :routeTarget="item.update_portfolio" :body="{
						'status': false,
					}" size="xs" :bindToLink="{
						preserveScroll: true,
					}"/>
        </template>
    </Table>

    <!-- <pre>{{ data.data[0] }}</pre> -->


    <Modal :isOpen="isOpenModal" width="w-full max-w-2xl h-full max-h-[570px]" @close="isOpenModal = false">
        <div class="relative isolate">

            <div v-if="isLoadingSubmit"
                 class="flex justify-center items-center text-7xl text-white absolute z-10 inset-0 bg-black/40">
                <LoadingIcon/>
            </div>

            <div class="mb-2">
                <PureInput v-model="querySearchPortfolios" aupdate:modelValue="() => debounceGetPortfoliosList()"
                           :placeholder="trans('Input to search portfolios')"/>
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
                                        <FontAwesomeIcon v-if="selectedVariant?.id === item.id"
                                                         icon="fas fa-check-circle"
                                                         class="bottom-2 right-2 absolute text-green-500"
                                                         fixed-width aria-hidden="true"/>
                                    </Transition>
                                    <slot name="product" :item="item">
                                        <Image v-if="item.images?.src" :src="item.images?.src"
                                               class="w-16 h-16 overflow-hidden mx-auto md:mx-0 mb-4 md:mb-0" imageCover
                                               :alt="item.name"/>
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
                        <Button @click="() => onSubmitVariant()" xdisabled="selectedProduct.length < 1"
                                xv-tooltip="selectedProduct.length < 1 ? trans('Select at least one product') : ''"
                                xlabel="submitLabel ?? `${trans('Add')} ${selectedProduct.length}`"
                                label="Select as variant" type="primary" full xicon="fas fa-plus"
                                :loading="isLoadingSubmit"/>
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>

