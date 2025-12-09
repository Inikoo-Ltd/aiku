<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCircle, faPlay, faTrash, faPlus, faBarcode } from "@fas"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { faTrash as falTrash, faEdit, faExternalLink, faPuzzlePiece, faShieldAlt, faInfoCircle, faChevronDown, faChevronUp, faBox, faVideo } from "@fal"
import ProductResource from "./ProductResource.vue"

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
		data: PropsData
		gpsr?: Gpsr
		hide?: string[]
		// publicAttachment: array<any>
		// privateAttachment: {}[]
		attachments: {
			public: {}[]
			private: {}[]
		}
		properties?: {
			country_of_origin?: { code: string; name: string }
			tariff_code?: string
			duty_rate?: string
		}
	}>(),
	{
	}
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
					<div class="flex justify-between flex-wrap gap-1" v-if="data.unit && data.unit">
						<dt class="text-gray-500">{{ trans("Units") }}</dt>
						<dd class="font-medium max-w-[236px] text-right">{{ data?.units }} ({{ data.unit }}) </dd>
					</div>


					<div class="flex justify-between flex-wrap gap-1" v-else>
						<dt class="text-gray-500">{{ trans("Unit label") }}</dt>
						<dd class="font-medium max-w-[236px] text-right">{{ data?.units }} </dd>
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
				</div>


				<ProductResource 
					:attachments
					:data
					:gpsr
					:properties
				/>
			</dl>
		</div>
	</div>
</template>

<style scoped>
</style>