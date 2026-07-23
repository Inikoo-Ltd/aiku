<script setup lang="ts">

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { computed, inject, ref, useTemplateRef } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
	faTrash as falTrash,
	faEdit,
	faExternalLink,
	faPuzzlePiece,
	faShieldAlt,
	faInfoCircle,
	faChevronDown,
	faLock,
	faChevronUp,
	faBox,
	faVideo,
	faTimesCircle,
	faCheckCircle
} from "@fal"
import { faCircle, faPlay, faTrash, faPlus, faBarcode, faThumbtack } from "@fas"
import { faImage, faStarfighter, faStarshipFreighter } from "@far"
import ImagePrime from "primevue/image"
import { routeType } from "@/types/route"
import { ProductResource } from "@/types/Iris/Products"
import { Image as ImageTS } from "@/types/Image"
import ProductUnitLabel from "@/Components/Utils/Label/ProductUnitLabel.vue"
import TradeUnitMasterProductSummary from "@/Components/Goods/TradeUnitMasterProductSummary.vue"
import AttachmentCard from "@/Components/AttachmentCard.vue"
import { trans } from "laravel-vue-i18n"
import Modal from "@/Components/Utils/Modal.vue"
import Popover from "primevue/popover"
import { Link, router } from "@inertiajs/vue3"
import { useLayoutStore } from "@/Stores/layout"
import { provide } from "vue"
import FractionDisplay from '@/Components/DataDisplay/FractionDisplay.vue'
import SalesAnalyticsCompact from '@/Components/Product/SalesAnalyticsCompact.vue'
import LabelSKU from '@/Components/Utils/Product/LabelSKU.vue'
import { faWarning } from "@fortawesome/free-solid-svg-icons"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { createReusableTemplate } from "@vueuse/core"
import { Disclosure, DisclosureButton, DisclosurePanel } from "@headlessui/vue"


library.add(
	faCircle,
	faTrash,
	falTrash,
	faEdit,
	faExternalLink,
	faPlay,
	faPlus,
	faBarcode,
	faPuzzlePiece,
	faShieldAlt,
	faInfoCircle,
	faChevronDown,
	faLock,
	faChevronUp,
	faBox,
	faVideo,
    faThumbtack
)

provide("layout", useLayoutStore())
const layout = useLayoutStore()
console.log(layout.app.theme);

const props = defineProps<{
	currency: string,
	handleTabUpdate: Function
	salesData?: any
	data: {
		rebel_prices  : {},
        rebel_rrp : {}
		availability_status: {
			is_for_sale: boolean
			product: {}[]
			total_product_for_sale: number
			from_trade_unit: boolean
		}
		masterProduct: {
			stockImagesRoute: routeType
			uploadImageRoute: routeType
			attachImageRoute: routeType
			deleteImageRoute: routeType
			imagesUploadedRoutes: routeType
			master_prices : any
			master_rrp : any
			webpage_url: string
			attachment_box?: {}
			translation_box: {
				title: string
				languages: Record<string, string>
				save_route: routeType
			}
			product: {
				data: ProductResource
			}
			stats: {
				amount: number | null
				amount_ly: number | null
				name: string
				percentage: number | null
			}[] | null
			trade_units: {
				brand: {}
				brand_routes: {
					index_brand: routeType
					store_brand: routeType
					update_brand: routeType
					delete_brand: routeType
					attach_brand: routeType
					detach_brand: routeType
				}
				tag_routes: {
					index_tag: routeType
					store_tag: routeType
					update_tag: routeType
					delete_tag: routeType
					attach_tag: routeType
					detach_tag: routeType
				}
				tags: {}[]
				tags_selected_id: number[]
			}[],
			gpsr: {
				acute_toxicity: boolean
				corrosive: boolean
				eu_responsible: string | null
				explosive: boolean
				flammable: boolean
				gas_under_pressure: boolean
				gpsr_class_category_danger: string | null
				hazard_environment: boolean
				health_hazard: boolean | null
				how_to_use: string
				manufacturer: null | string
				oxidising: boolean
				product_languages: string | null
				warnings: string | null
			}
			images: any
			main_image: ImageTS

		}

	}
}>();

/* const tradeUnitTags = computed(() => {
	const list = props.data?.masterProduct.trade_units ?? []
	const tags = list.flatMap(item => item.tags ?? [])
	const unique = new Map(tags.map(tag => [tag.id, tag]))
	return [...unique.values()]
}) */
const locale = inject('locale', aikuLocaleStructure)

const rebelPricePopover = useTemplateRef('rebelPricePopover')
type RebelPrice = {
	id: number
	shop_id: number
	shop_code: string
	currency_code: string
	value: number | null
	currency_symbol: string
}
const rebelPriceList = computed<RebelPrice[]>(() => Object.values(props.data?.rebel_prices ?? {}))

const toggleRebelPrice = (event: Event) => {
	rebelPricePopover.value?.toggle(event)
}

const rebelRrpPopover = useTemplateRef('rebelRrpPopover')
const rebelRrpList = computed<RebelPrice[]>(() => Object.values(props.data?.rebel_rrp ?? {}))

const toggleRebelRrp = (event: Event) => {
	rebelRrpPopover.value?.toggle(event)
}

type MasterPrices = Record<string, { value: string | number | null; independent?: boolean }>

const TOP_CURRENCIES = ['EUR', 'GBP']

const priceEntries = (prices?: MasterPrices) =>
	Object.entries(prices ?? {})
		.filter(([, entry]) => entry?.value != null && Number(entry.value) !== 0)
		.map(([code, entry]) => ({ code, value: entry.value }))

const topPrices = (prices?: MasterPrices) => {
	const entries = priceEntries(prices)
	return TOP_CURRENCIES
		.map(code => entries.find(entry => entry.code === code))
		.filter((entry): entry is { code: string; value: string | number | null } => Boolean(entry))
}

const restPrices = (prices?: MasterPrices) =>
	priceEntries(prices).filter(entry => !TOP_CURRENCIES.includes(entry.code))

const hasPrices = (prices?: MasterPrices) => priceEntries(prices).length > 0

const [DefineMasterPriceBlock, ReuseMasterPriceBlock] = createReusableTemplate<{
	title: string
	prices?: MasterPrices
	rebelList: RebelPrice[]
	toggleRebel: (event: Event) => void
	emptyTooltip: string
}>()

const tradeUnitTags = computed(() => {
	const list = props.data?.trade_units ?? []
	const tags = list.flatMap(item => item.tags ?? [])
	const unique = new Map(tags.map(tag => [tag.id, tag]))
	return [...unique.values()]
})

const tradeUnitBrands = computed(() => {
  return (props.data?.trade_units ?? [])
    .flatMap(unit => unit?.brand ?? [])
})

function productRoute(product: any, openEdit = false) {
    if (!product?.slug) return "";

    const base = "grp.org.shops.show.catalogue.products.current_products.";
    const action = openEdit ? "edit" : "show";

    return route(`${base}${action}`, {
        organisation: product.organisation.slug,
        shop: product.shop.slug,
        product: product.slug,
        ...(openEdit && { section: 4 })
    });
}

const getTooltips = () => {
	let tooltipText = props.data.availability_status.is_for_sale ? trans('Master product is currently for sale and available to be purchased') : trans('Master product is currently not for sale and unavailable to be purchased');
	if (props.data.availability_status.from_trade_unit) {
		tooltipText = trans('This master product For Sale status has been modified from the Trade Unit level')
	}

	return tooltipText;
}

const editRoute = () => {
	let url = route(route().current().replace(/show$/, 'edit'), {
			...route().params,
			section: 4
	});
	if(props.data.availability_status?.from_trade_unit && props.data.availability_status?.parentLink){
		url = route(props.data.availability_status?.parentLink['url'], {
			...props.data.availability_status?.parentLink['params'],
			section: 8
		});
	}

	router.visit(url);
}

const tradeUnitRoute = (tradeUnit: TradeUnit) => {
    return route(
        "grp.trade_units.units.show",
        [tradeUnit.slug])
}

const isModalProductForSale = ref(false)

</script>


<template>
	<DefineMasterPriceBlock v-slot="{ title, prices, rebelList, toggleRebel, emptyTooltip }">
		<Disclosure as="div" v-slot="{ open }" class="w-full">
			<div
				class="border border-solid rounded-md font-semibold w-full"
				:class="hasPrices(prices) ? 'border-gray-400' : 'border-red-700 text-red-500 animate-pulse'"
				v-tooltip="hasPrices(prices) ? '' : emptyTooltip"
			>
				<div class="flex items-center py-1 px-3 text-xs">
					<span class="w-24 block">{{ title }}:</span>
					<div class="flex flex-wrap gap-x-3 gap-y-0.5">
						<span v-for="price in topPrices(prices)" :key="price.code">
							{{ locale.currencyFormat(price.code, price.value) }}
						</span>
					</div>
					<div class="flex gap-2 my-auto ml-auto items-center">
						<FontAwesomeIcon v-if="!hasPrices(prices)" :icon="faWarning" />
						<span
							v-if="rebelList.length > 0"
							class="inline-flex items-center gap-1 text-yellow-500 hover:text-yellow-600"
							v-tooltip="trans('Show rebel prices (products not following master pricing)')"
							@click.stop="toggleRebel"
						>
							<FontAwesomeIcon :icon="faStarfighter" />
							<span class="text-xs font-bold">{{ rebelList.length }}</span>
						</span>
						<DisclosureButton
							v-if="restPrices(prices).length > 0"
							class="flex items-center text-gray-500 hover:text-gray-700"
						>
							<FontAwesomeIcon
								:icon="faChevronDown"
								class="text-xs transition-transform duration-200"
								:class="{ '-rotate-90': !open }"
							/>
						</DisclosureButton>
					</div>
				</div>
				<DisclosurePanel v-if="restPrices(prices).length > 0">
					<div class="overflow-x-auto border-t border-gray-200 px-3 py-2">
						<table class="w-full border-collapse text-xs font-normal">
							<tbody>
								<tr v-for="price in restPrices(prices)" :key="price.code" class="hover:bg-gray-50">
									<td class="py-1 pr-3 font-medium text-gray-600">{{ price.code }}</td>
									<td class="py-1 text-right">{{ locale.currencyFormat(price.code, price.value) }}</td>
								</tr>
							</tbody>
						</table>
					</div>
				</DisclosurePanel>
			</div>
		</Disclosure>
	</DefineMasterPriceBlock>

	<div class="w-full pl-4 pr-3 py-3 mb-3 shadow-sm grid grid-cols-2">
		<div class="text-xl font-semibold text-gray-800 whitespace-pre-wrap justify-self-start">
			<ProductUnitLabel v-if="data.masterProduct?.units" :units="data.masterProduct?.units"
				:unit="data.masterProduct?.unit" class="mr-2" />
			<span class="align-middle">
				{{ data.masterProduct.name }}
			</span>
		</div>

		<div v-if="data.availability_status || data.trade_units.length > 0" class="text-md text-gray-800 whitespace-pre-wrap justify-self-end self-center items-center flex">
			<LabelSKU
				:product="data.masterProduct"
				:trade_units="data.trade_units"
				:routeFunction="tradeUnitRoute"
			/>
			<span v-if="data.availability_status"
				v-on:click="isModalProductForSale = true"
				v-tooltip="getTooltips()"
				class="border border-solid hover:opacity-80 py-1 px-3 rounded-md hover:cursor-pointer "
				:class="data.availability_status.status ? 'border-green-500' : 'border-red-500'"
			>
				{{ data.availability_status.status ? trans('For Sale') : trans('Not For Sale') }}
				(<span class="font-semibold" :class='data.availability_status.total_product_for_sale != data.availability_status.total_products ? "opacity-80" : ""'>
					{{ `${data.availability_status.total_product_for_sale}/${data.availability_status.total_products}` }}
				</span>)
				<FontAwesomeIcon
					v-if="!data.availability_status.is_for_sale"
					icon="fas fa-thumbtack"
					:class="'text-red-500 ms-2 hover:cursor-pointer'"
				/>
			</span>
		</div>
	</div>

	<div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mx-3 lg:mx-0 mt-2">
		<!-- Sidebar -->
		<div class="space-y-4 lg:space-y-6">
			<!-- Master Product Tags -->
			<dd v-if="tradeUnitTags && tradeUnitTags.length > 0" class="font-medium flex flex-wrap gap-1 p-4">
				<span v-for="tag in tradeUnitTags" :key="tag.id" v-tooltip="'tag'"
					class="px-2 py-0.5 rounded-full text-xs bg-green-50 border border-blue-100">
					{{ tag.name }}
				</span>
			</dd>
			<!-- Image Preview & Thumbnails -->
			<div class="bg-white p-4 lg:p-5">
				<div v-if="props.data?.main_image?.webp" class="max-w-[550px] w-full">
					<ImagePrime :src="props.data?.main_image.webp" :alt="props?.data?.product?.data?.name" preview />
					<!-- <div class="text-sm italic text-gray-500">
						See all the images of this product in the tab <span @click="() => handleTabUpdate('images')"
							class="underline text-indigo-500 hover:text-indigo-700 cursor-pointer">images</span>
					</div> -->
				</div>
				<div v-else>
					<div
						class="flex flex-col items-center justify-center gap-2 py-8 border-2 border-dashed border-gray-200 rounded-lg">
						<FontAwesomeIcon :icon="faImage" class="text-4xl text-gray-400" />
						<p class="text-sm text-gray-500 text-center">No images uploaded yet</p>
					</div>
					<!-- <div class="mt-2 text-sm italic text-gray-500">
						Manage images in tab <span @click="() => handleTabUpdate('images')"
							class="underline text-indigo-500 hover:text-indigo-700 cursor-pointer">Media</span>
					</div> -->
				</div>
			</div>
		</div>

		<!-- Product Summary - spans 2 columns -->
		<div class="lg:col-span-2">
			<TradeUnitMasterProductSummary
				:data="{...data.masterProduct, tags : tradeUnitTags, brands : tradeUnitBrands}"
				:gpsr="data.gpsr"
				:properties="data.properties"
				:attachments="data.attachment_box"
			/>
		</div>

        <!-- Sales Analytics - right sidebar -->
        <div>
			<div class="grid justify-items-end pr-3 pb-2 gap-2">
				<ReuseMasterPriceBlock
					:title="trans('Master Price')"
					:prices="data.masterProduct.master_prices"
					:rebelList="rebelPriceList"
					:toggleRebel="toggleRebelPrice"
					:emptyTooltip="trans('Price is not set up for this master product')"
				/>

				<Popover ref="rebelPricePopover">
					<div class="min-w-[20rem]">
						<div class="mb-2 text-xs font-semibold text-gray-700">
							{{ trans('Rebel Prices') }}
							<span class="text-gray-400">({{ trans('not following master pricing') }})</span>
						</div>

						<div class="overflow-x-auto">
							<table class="w-full border-collapse text-xs">
								<thead>
									<tr class="bg-gray-100 text-left text-gray-600">
										<th class="border px-3 py-1.5">{{ trans('Shop') }}</th>
										<th class="border px-3 py-1.5 text-right">{{ trans('Price') }}</th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="rebel in rebelPriceList" :key="rebel.shop_id" class="hover:bg-gray-50">
										<td class="border px-3 py-1.5 font-medium text-gray-700">{{ rebel.shop_code }}</td>
										<td class="border px-3 py-1.5 text-right">
											{{ locale.currencyFormat(rebel.currency_code, rebel.value ?? 0) }}
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</Popover>
				<ReuseMasterPriceBlock
					:title="trans('Master RRP')"
					:prices="data.masterProduct.master_rrp"
					:rebelList="rebelRrpList"
					:toggleRebel="toggleRebelRrp"
					:emptyTooltip="trans('RRP is not set up for this master product')"
				/>

				<Popover ref="rebelRrpPopover">
					<div class="min-w-[20rem]">
						<div class="mb-2 text-xs font-semibold text-gray-700">
							{{ trans('Rebel RRP') }}
							<span class="text-gray-400">({{ trans('not following master pricing') }})</span>
						</div>

						<div class="overflow-x-auto">
							<table class="w-full border-collapse text-xs">
								<thead>
									<tr class="bg-gray-100 text-left text-gray-600">
										<th class="border px-3 py-1.5">{{ trans('Shop') }}</th>
										<th class="border px-3 py-1.5 text-right">{{ trans('RRP') }}</th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="rebel in rebelRrpList" :key="rebel.shop_id" class="hover:bg-gray-50">
										<td class="border px-3 py-1.5 font-medium text-gray-700">{{ rebel.shop_code }}</td>
										<td class="border px-3 py-1.5 text-right">
											{{ locale.currencyFormat(rebel.currency_code, rebel.value ?? 0) }}
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</Popover>
			</div>
			<div class="mr-3">
				<SalesAnalyticsCompact  v-if="salesData" :salesData="salesData" />
			</div>
        </div>

		<!-- <div>
			<pre>{{ data.attachment_box }}</pre>
			<AttachmentCard :private="data.attachment_box?.private" />
		</div> -->
	</div>

	<Modal :isOpen="isModalProductForSale" @onClose="isModalProductForSale = false" width="w-full max-w-lg">
		<div class="grid grid-cols-2 font-bold mb-4">
			<div class="text-left text-lg">
				{{ trans('Product For Sale Statuses') }}
			</div>
			<div class="justify-self-end text-lg">
				<FontAwesomeIcon
					icon="fal fa-edit"
					class="hover:cursor-pointer hover:opacity-80"
					style="color: var(--theme-color-0);"
					v-tooltip="trans('Click to edit For Sale status')"
					v-on:click="editRoute()"
				/>
				<FontAwesomeIcon
					v-if="data.availability_status?.from_trade_unit"
					v-tooltip="getTooltips()"
					icon="fal fa-atom"
					:class="'ms-2 hover:cursor-pointer'"
				/>
			</div>
        </div>
		<div class="grid grid-cols-3 mt-3 text-sm font-bold">
			<div class="text-left">
				Shop
			</div>
			<div class="text-left">
				Code
			</div>
			<div class="text-right">
			</div>
		</div>
		<div v-for="item in data.availability_status.product" :key="item.id" class="grid grid-cols-3 mt-3 text-sm min-h-8">
			<div class="text-left">
				{{ item.shop.code }}
			</div>
			<div class="text-left">
				<Link :href="productRoute(item)" class="primaryLinkxx">
					{{ item.code }}
				</Link>
			</div>
			<div class="text-right min-h-max" :class="item.is_for_sale ? 'text-green-600' : 'text-red-600'">
				<span
				v-on:click="router.visit(productRoute(item, true))"
				v-tooltip="item.is_for_sale ? trans('Product is currently for sale and available to be purchased') : trans('Product is currently not for sale and unavailable to be purchased')"
				class="border border-solid hover:opacity-80 py-1 px-3 rounded-md hover:cursor-pointer"
				:class="item.is_for_sale ? 'border-green-500' : 'border-red-500'">
					{{ item.is_for_sale ? trans('For Sale') : trans('Not For Sale') }}
					<FontAwesomeIcon :icon="item.is_for_sale ? faCheckCircle : faTimesCircle" :class="item.is_for_sale ? 'text-green-500' : 'text-red-500'"/>
				</span>
			</div>
		</div>
	</Modal>
</template>

<style lang="scss">
    .primaryLinkxx {
        background: linear-gradient(to top, var(--theme-color-3), var(--theme-color-3));

        &:hover {
            color: v-bind('`${layout.app.theme[7]}`');
        }

        @apply focus:ring-0 focus:outline-none focus:border-none bg-no-repeat [background-position:0%_100%] transition-all [background-size:100%_0.2em] motion-safe:transition-all motion-safe:duration-200 hover:[background-size:100%_100%] focus:[background-size:100%_100%] px-1 py-1 lg:py-0.5
    }
</style>
