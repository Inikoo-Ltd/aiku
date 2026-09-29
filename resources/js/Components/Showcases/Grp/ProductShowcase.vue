<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@common/Components/Image.vue"
import ImagePrime from "primevue/image"
import ProductCategoryCard from "@/Components/ProductCategoryCard.vue"
import { ref, computed, inject } from "vue"
import { faTrash as falTrash, faEdit, faExternalLink, faPuzzlePiece, faShieldAlt, faInfoCircle, faChevronDown, faChevronUp, faBox, faVideo} from "@fal"
import { faCircle, faPlay, faTrash, faPlus, faBarcode, faCheckCircle, faTimesCircle } from "@fas"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { Images } from "@/types/Images"
import ImageProducts from "@/Components/Product/ImageProducts.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import ProductSummary from "@/Components/Product/ProductSummary.vue"
import SummaryCard from "@/Components/Goods/SummaryCard.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import ReviewContent from "@/Components/ReviewContent.vue"
import AttachmentCard from "@/Components/AttachmentCard.vue"
import ProductPriceGrp from "@/Components/Product/ProductPriceGrp.vue"
import { ProductResource } from "@/types/Iris/Products"
import { Image as ImageTS } from "@/types/Image"
import ProductUnitLabel from "@/Components/Utils/Label/ProductUnitLabel.vue"
import { router } from "@inertiajs/vue3"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"
import Modal from "@/Components/Utils/Modal.vue"
import LabelSKU from '@/Components/Utils/Product/LabelSKU.vue'
import SalesAnalyticsCompact from '@/Components/Product/SalesAnalyticsCompact.vue'
import SalesAnalysisTeaser from '@/Components/SalesAnalysis/SalesAnalysisTeaser.vue'
import SearchInWebsiteAvailabilityChecklist from '@/Components/Utils/SearchInWebsiteAvailabilityChecklist.vue'
import { useFormatTime } from '@/Composables/useFormatTime'

const locale = inject('locale', aikuLocaleStructure)


library.add(faCircle, faTrash, falTrash, faEdit, faExternalLink, faPlay, faPlus, faBarcode, faPuzzlePiece, faShieldAlt, faInfoCircle, faChevronDown, faChevronUp, faBox, faVideo)

const props = defineProps<{
	data: {
		stockImagesRoute: routeType
		uploadImageRoute: routeType
		attachImageRoute: routeType
		deleteImageRoute: routeType
		imagesUploadedRoutes: routeType
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
        is_external: boolean
        is_dropship?: boolean
		stats: {
			amount: number | null
			amount_ly: number | null
			name: string
			percentage: number | null
		}[] | null
		org_stocks: {
			id: number
			code: string
			quantity: string | null
			quantity_available: string | null
		}[]
		stock_locations?: {
			location_code: string
			warehouse_code: string
			org_stock_code: string
			quantity: number
		}[]
		incoming_stock?: {
			type: string
			reference: string
			org_stock_code: string
			state_label: string
			quantity: number
			eta: string | null
			is_estimate: boolean
		}[]
		brands: {}[]
		tags: {}[]
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
		availability_status?: {
			from_master: boolean
			from_trade_unit: boolean
			is_for_sale: boolean
			product_state: string
			product_state_icon: []
			parentLink?: []
		}
		images: any
		main_image: ImageTS
	}
	handleTabUpdate?: Function
	salesData?: object
	salesAnalysisTeaser?: object
	showSalesAnalysis?: boolean
}>()


// const tradeUnitTags = computed(() => {
// 	const list = props.data?.trade_units ?? []
// 	const tags = list.flatMap(item => item.tags ?? [])
// 	const unique = new Map(tags.map(tag => [tag.id, tag]))
// 	return [...unique.values()]
// })


// const tradeUnitBrands = computed(() => {
//   return (props.data?.trade_units ?? [])
//     .flatMap(unit => unit?.brand ?? [])
// })


const showLocations = ref(false)

const partsOutOfStock = computed(() =>
	(props.data.org_stocks ?? []).filter((part) => Number(part.quantity_available ?? 0) < Number(part.quantity ?? 1))
)

const shelfQuantity = computed(() => {
	const quantities = (props.data.org_stocks ?? [])
		.filter((part) => Number(part.quantity) > 0)
		.map((part) => Math.floor(Number(part.quantity_available ?? 0) / Number(part.quantity)))

	return quantities.length ? Math.max(0, Math.min(...quantities)) : 0
})

const stockStatus = computed(() => {
	const product = props.data?.product?.data

	if (product?.state === 'discontinued') {
		return { isAvailable: false, label: ctrans("Discontinued"), detail: null }
	}

	if (product?.is_on_demand) {
		if (!props.data?.availability_status?.is_for_sale) {
			return { isAvailable: false, label: ctrans("Not for sale"), detail: ctrans("Made on demand") }
		}

		return {
			isAvailable: true,
			label: ctrans("Always available"),
			detail: ctrans(":quantity on the shelf, made on demand", { quantity: locale.number(shelfQuantity.value) }),
		}
	}

	if (product?.stock > 0) {
		return { isAvailable: true, label: ctrans("In stock"), detail: `${locale.number(product.stock)} ${ctrans("available")}` }
	}

	return { isAvailable: false, label: ctrans("Out Of Stock"), detail: null }
})

const editIsForSale = () => {
	let url = route('grp.org.shops.show.catalogue.products.all_products.edit', {
			...route().params,
			section: 4
	});
	if(props.data.availability_status?.from_master && props.data.availability_status?.parentLink){
		url = route(props.data.availability_status?.parentLink['url'], {
			...props.data.availability_status?.parentLink['params'],
			section: 6
		});
	}
	if(props.data.availability_status?.from_trade_unit && props.data.availability_status?.parentLink){
		url = route(props.data.availability_status?.parentLink['url'], {
			...props.data.availability_status?.parentLink['params'],
			section: 8
		});
	}

    router.visit(url)
}

const getTooltips = () => {
	let tooltipText = props.data.availability_status?.is_for_sale ? ctrans('Product is currently for sale and available to be purchased') : ctrans('Product is currently not for sale and unavailable to be purchased')

	if(props.data.availability_status?.from_master || props.data.availability_status?.parentLink){
		tooltipText = props.data.availability_status?.from_master ? ctrans('This product For Sale status has been modified from the Master Product level') : ctrans('This product For Sale status has been modified from the Trade Unit level')
	}

	return tooltipText;
}

</script>

<template>
	<div class="w-full px-4 py-3 mb-3 shadow-sm grid grid-cols-2">
		<div class="text-xl font-semibold text-gray-800 whitespace-pre-wrap justify-self-start">
			<!-- Units box -->
			<ProductUnitLabel
				v-if="data.product?.data?.units"
				:units="data.product?.data?.units"
				:unit="data.product?.data?.unit"
				class="mr-2"
			/>

			<!-- Product name -->
			<span class="align-middle">
				{{ data.product.data.name }}
			</span>
		</div>

		<div v-if="data.availability_status" class="text-md text-gray-800 whitespace-pre-wrap justify-self-end self-center flex gap-y-2 flex-wrap justify-end">
			<LabelSKU
				v-if="data.product.data.picking_factor"
				:product="data.product.data"
				:trade_units="data.product.data.picking_factor"
				xrouteFunction="tradeUnitRoute"
				keyPicking="picking_factor"
                :hideUnit="data.is_external"
			>
				<template #col_code="{ data }">
					{{ data.org_stock_code }}
				</template>

				<template #col_name="{ data }">
					<p>{{ data.org_stock_name }} <span class="text-orange-500">{{ ctrans('Units/SKO')}}:{{ data.units_per_sku }}</span></p>
				</template>
			</LabelSKU>

			<span
				class="border border-solid hover:opacity-80 py-1 px-3 rounded-md hover:cursor-help"
				:class="data.availability_status.product_state_icon['class'].replace('text', 'border').replace('500', '300')">
                <span class="opacity-50"> {{ctrans('Procurement')}}:</span>	 {{ data.availability_status.product_state}}
				<FontAwesomeIcon :icon="data.availability_status.product_state_icon['icon']" :class="data.availability_status.product_state_icon['class']" fixed-width/>
			</span>

			<span
                v-if="data.product.data.state!='discontinued' && !data.is_external  "
				v-tooltip="getTooltips()"
				class="border border-solid hover:opacity-80 py-1 px-3 rounded-md hover:cursor-pointer mx-2"
				v-on:click="editIsForSale"
				:class="data.availability_status.is_for_sale ? 'border-green-500' : 'border-red-500'"
			>
			{{ data.availability_status.is_for_sale ? ctrans('For Sale') : ctrans('Not For Sale') }}
				<FontAwesomeIcon :icon="data.availability_status.is_for_sale ? faCheckCircle : faTimesCircle" :class="data.availability_status.is_for_sale ? 'text-green-500' : 'text-red-500'" fixed-width/>
				<FontAwesomeIcon
					v-if="data.availability_status?.from_master"
					icon="fab fa-octopus-deploy"
					:class="'ms-1'"
					color="#4B0082" fixed-width
				/>
				<FontAwesomeIcon
					v-if="data.availability_status?.from_trade_unit"
					icon="fal fa-atom"
					:class="'ms-1'" fixed-width
				/>
			</span>
		</div>
	</div>

	<!-- Content area 8/12, right sidebar (prices, analytics) 4/12 but never under 385px;
	     the image sits beside the summary only when there is room -->
	<div class="grid grid-cols-1 gap-4 mx-3 mt-2 lg:mr-0 lg:ml-5 lg:grid-cols-[minmax(0,8fr)_minmax(385px,4fr)]">
		<!-- Content: image + summary. The image column has its own cap; the summary takes the rest -->
		<div class="flex min-w-0 flex-col gap-4 xl:flex-row xl:gap-8">
			<div class="shrink-0 space-y-4 xl:w-96 2xl:w-[550px]" v-if="data?.product?.data?.picking_factor?.length">
				<!-- Product Tags -->
				<!-- <dd v-if="data.tags && data.tags?.length > 0" class="font-medium flex flex-wrap gap-1 p-4">
					<span v-for="tag in data.tags" :key="tag.id" v-tooltip="'tag'" class="px-2 py-0.5 rounded-full text-xs bg-green-50 border border-blue-100">
						{{ tag.name }}
					</span>
				</dd> -->

				<!-- Image Preview & Thumbnails -->
				<ProductCategoryCard subtle :data="data.product.data">
					<template v-if="props.data?.main_image?.webp" #image>
						<ImagePrime :src="props.data?.main_image.webp" :alt="props?.data?.product?.data?.name" preview
							class="block w-full" imageClass="w-full aspect-square object-contain" />
					</template>
				</ProductCategoryCard>
			</div>

			<!-- Product Summary -->
			<div class="min-w-0 flex-1">
				<SummaryCard>
					<ProductSummary
						:noTradeUnit="!data?.product?.data?.picking_factor?.length"
						:data="{...data.product.data, tags: data.tags, brands: data.brands}"
						:properties="data.properties"
						:parts="data.org_stocks"
						:public-attachment="data.attachment_box.public"
						:gpsr="data.gpsr"
						:attachments="data.attachment_box"
						:labelInfo="data.label_info"
					/>
				</SummaryCard>
			</div>
		</div>

		<div class="min-w-0 h-fit mx-4">
			<div class="mb-4 flex items-center gap-3 px-2">
				<span class="relative flex h-3 w-3 shrink-0">
					<span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-60"
						:class="stockStatus.isAvailable ? 'bg-green-400' : 'bg-red-400'" />
					<span class="relative inline-flex h-3 w-3 rounded-full"
						:class="stockStatus.isAvailable ? 'bg-green-500' : 'bg-red-500'" />
				</span>
				<span class="flex flex-wrap items-baseline gap-x-2">
					<span class="text-xl font-semibold text-gray-800">{{ stockStatus.label }}</span>
					<span v-if="stockStatus.detail" class="text-sm tabular-nums text-gray-500">{{ stockStatus.detail }}</span>
				</span>
			</div>

			<div v-if="!(data?.product?.data?.stock > 0) && partsOutOfStock.length" class="mb-4 mx-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2">
				<div class="text-xs font-semibold uppercase tracking-wide text-red-700 mb-1">{{ ctrans("Not enough stock of") }}</div>
				<table class="w-full text-sm">
					<tr v-for="part in partsOutOfStock" :key="part.id" class="border-b border-red-100 last:border-0">
						<td class="py-1 font-medium text-red-600">{{ part.code }}</td>
						<td class="py-1 text-right tabular-nums text-red-600">{{ locale.number(Number(part.quantity_available ?? 0)) }}</td>
					</tr>
				</table>
			</div>

			<!-- Section: Where the stock sits -->
			<div v-if="data.stock_locations?.length" class="mb-4 px-2">
				<button type="button" @click="showLocations = !showLocations" class="flex w-full items-center gap-1 text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1 hover:text-gray-700">
					{{ ctrans("Locations") }} ({{ data.stock_locations.length }})
					<FontAwesomeIcon :icon="faChevronDown" class="ml-auto transition-transform" :class="{ 'rotate-180': showLocations }" fixed-width />
				</button>
				<table v-if="showLocations" class="w-full text-sm">
					<tr v-for="location in data.stock_locations" :key="location.location_code + location.org_stock_code" class="border-b border-gray-100 last:border-0">
						<td class="py-1 font-medium">{{ location.location_code }}</td>
						<td class="py-1 text-gray-500">{{ location.org_stock_code }}</td>
						<td class="py-1 text-right tabular-nums">{{ locale.number(location.quantity) }}</td>
					</tr>
				</table>
			</div>

			<!-- Section: On its way -->
			<div class="mb-4 px-2">
				<div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">{{ ctrans("On its way") }}</div>
				<table class="w-full text-sm">
					<tr v-for="incoming in data.incoming_stock" :key="incoming.type + incoming.reference + incoming.org_stock_code + incoming.state_label" class="border-b border-gray-100 last:border-0">
						<td class="py-1 font-medium" v-tooltip="{ purchase_order: ctrans('Purchase order'), stock_delivery: ctrans('Stock delivery') }[incoming.type] ?? ctrans('Partner request')">{{ incoming.reference }}</td>
						<td class="py-1 text-gray-500">{{ incoming.state_label }}</td>
						<td class="py-1 text-right tabular-nums">{{ locale.number(incoming.quantity) }}</td>
						<td class="py-1 text-right text-gray-500 whitespace-nowrap" v-tooltip="incoming.is_estimate ? ctrans('Estimated from how far it got and past lead times') : undefined">{{ incoming.eta ? (incoming.is_estimate ? "~ " : "") + useFormatTime(incoming.eta, { formatTime: "mdy" }) : "—" }}</td>
					</tr>
				</table>
				<div v-if="!data.incoming_stock?.length" class="text-sm text-gray-500">{{ ctrans("Nothing on order") }}</div>
			</div>

			<!-- Section: Price -->
			<ProductPriceGrp :product="data?.product?.data" :currency_code="data.product.data?.currency_code" :perOuter="data.is_dropship" />
			<!-- <div>
				<AttachmentCard :public="data.attachment_box.public" :private="data.attachment_box.private" />
			</div> -->

			<SalesAnalysisTeaser v-if="showSalesAnalysis" :teaser="salesAnalysisTeaser" class="mb-4" />

			<!-- Sales Analytics Compact -->
			<div v-if="salesData && !(data?.product?.data?.state == 'in_process')">
				<SalesAnalyticsCompact :salesData="salesData" />
			</div>

			<!-- Internal Search Availability Checklist -->
			<div v-if="data.search_in_website_availability" class="mt-4 border-t border-gray-200 pt-4 px-2">
				<SearchInWebsiteAvailabilityChecklist :availability="data.search_in_website_availability" />
			</div>
		</div>

	</div>
</template>

<style scoped>
/* Add custom styles if needed for better text readability */
.whitespace-pre-wrap {
	white-space: pre-wrap;
	word-wrap: break-word;
}

/* Remove all padding from accordion */
:deep(.p-accordion) {
	padding: 0;
}

:deep(.p-accordion-panel) {
	border: none;
}

:deep(.p-accordionheader) {
	padding: 10px 0;
	background: #f8fafc;
	border-radius: 0.5rem;
	border: none;
	background-color: #ffffff;
}

:deep(.p-accordionheader:hover) {
	background: #e2e8f0;
}

:deep(.p-accordioncontent-content) {
	padding: 0 !important;
	border: none;
}

:deep(.p-accordionheader-text) {
	padding: 0.75rem 1rem;
	width: 100%;
}
</style>
