<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTrash as falTrash, faEdit, faExternalLink, faPuzzlePiece, faShieldAlt, faInfoCircle, faChevronDown, faChevronUp, faBox, faVideo } from "@fal"
import { faCircle, faPlay, faTrash, faPlus, faBarcode } from "@fas"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { ProductShowcase } from "@/types/product-showcase"
import FractionDisplay from "../DataDisplay/FractionDisplay.vue"
import { Link } from "@inertiajs/vue3"
import {
	faLock,
	faGlobe,
	faFile,
	faCheckCircle,
	faFileCheck,
	faFilePdf,
	faFileWord,
} from "@fal"
import ProductResource from "../Goods/ProductResource.vue"

library.add(faLock, faGlobe, faFile, faCheckCircle, faFileCheck, faFilePdf, faFileWord)

interface Stats {
	amount: number | null
	amount_ly: number | null
	name: string
	percentage: number | null
}

interface TradeUnit {
	brand: Record<string, unknown>
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
	tags: Record<string, unknown>[]
	tags_selected_id: number[]
}

interface Gpsr {
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
	manufacturer: string | null
	oxidising: boolean
	product_languages: string | null
	warnings: string | null
}

interface PropsData {
	stockImagesRoute: routeType
	uploadImageRoute: routeType
	attachImageRoute: routeType
	deleteImageRoute: routeType
	imagesUploadedRoutes: routeType
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
	manufacturer: string | null
	oxidising: boolean
	product_languages: string | null
	warnings: string | null
	stats: Stats[] | null
	trade_units: TradeUnit[]
}

const props = withDefaults(
	defineProps<{
		data: ProductShowcase
		video?: string
		hide?: string[]
		gpsr?: object
		attachments: {

		}
		publicAttachment: array<any>
		properties?: {
			country_of_origin?: { code: string; name: string }
			tariff_code?: string
			duty_rate?: string
		}
	}>(), {}
)

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
	faChevronUp,
	faBox,
	faVideo
)


</script>


<template>
	<div>
		<div class="bg-white rounded-xl px-4 lg:px-5">
			<dl class="mt-4 space-y-6 text-sm">
				<div class="space-y-3">
					<!-- Section: Since -->
					<div class="flex justify-between flex-wrap gap-1">
						<dt class="text-gray-500">{{ trans("Since") }}</dt>
						<dd class="font-medium">{{ useFormatTime(data?.created_at) }}</dd>
					</div>

					<!-- Section: Units -->
					<div class="flex justify-between flex-wrap gap-1">
						<dt class="text-gray-500">{{ trans("Units") }}</dt>
						<dd class="font-medium max-w-[236px] text-right">{{ data?.units }} ({{ data.unit }}) </dd>
					</div>

					<!-- Section: Weight marketing -->
					<div class="flex justify-between flex-wrap gap-1">
						<dt class="text-gray-500">{{ trans("Weight") }} <span
								class="text-xs font-light text-gray-500">({{ trans('Marketing') }})</span></dt>
						<dd class="font-medium">
							{{ data?.marketing_weight }}
						</dd>
					</div>

					<!-- Section: Weight shipping -->
					<div class="flex justify-between flex-wrap gap-1">
						<dt class="text-gray-500">{{ trans("Weight") }} <span
								class="text-xs font-light text-gray-500">({{ trans('Shipping') }})</span></dt>
						<dd class="font-medium">
							{{ data?.gross_weight }}
						</dd>
					</div>

					<!-- Section: Marketing Dimensions -->
					<div class="flex justify-between flex-wrap gap-1">
						<dt class="text-gray-500">{{ trans("Dimensions") }}</dt>
						<dd class="font-medium">
							{{ data?.marketing_dimensions }}
						</dd>
					</div>

					<!-- Section: Barcode -->
					<div class="flex justify-between flex-wrap gap-1">
						<dt class="text-gray-500">{{ trans("Barcode") }}
							<FontAwesomeIcon :icon="faBarcode" />
						</dt>
						<dd class="font-medium">
							{{ data?.barcode }}
						</dd>
					</div>

					<!-- Section: Picking -->
					<div class="flex justify-between flex-wrap gap-0.5" v-if="!hide?.includes('picking')">
						<dt class="text-gray-500">{{ trans("Picking") }}</dt>
						<dd class="w-full border border-gray-200 px-2.5 py-1.5 rounded">
							<template v-if="data?.picking_factor?.length">
								<div v-for="pick in data.picking_factor" :key="pick.org_stock_id"
									class="grid grid-cols-4 gap-2 py-1 text-sm">
									<!-- Left -->
									<div class="col-span-3">
										<div class="flex items-center flex-wrap gap-1 leading-tight">
											<Link :href="route('grp.helpers.redirect_org_stock', pick.org_stock_id)"
												class="primaryLink font-medium">
												{{ pick.org_stock_code }}
											</Link>

											<span class="text-[11px] italic text-gray-400">
												({{ pick.org_stock_id }})
											</span>

											<span v-if="pick?.is_on_demand"
												class="text-[10px] px-1.5 rounded bg-amber-100 text-amber-700">
												On Demand
											</span>
										</div>

										<div v-tooltip="trans('Note')"
											class="text-[11px] text-gray-400 truncate max-w-[90%]">
											{{ pick.note || '-' }}
										</div>
									</div>

									<!-- Right -->
									<div class="flex items-center justify-end text-xs">
										<FractionDisplay v-tooltip="trans('Number of picking')"
											:fractionData="pick.picking_factor" />
									</div>
								</div>
							</template>


							<div v-else class="text-center text-gray-400 italic text-xs">
								{{ trans("No data available") }}
							</div>
						</dd>
					</div>
				</div>
				<ProductResource :attachments="attachments" :publicAttachment :data :gpsr :properties />
			</dl>
		</div>
	</div>
</template>

<style scoped></style>