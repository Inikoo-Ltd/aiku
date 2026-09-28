<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ref, computed } from "vue"
import {
	faTrash as falTrash,
	faEdit,
	faExternalLink,
	faPuzzlePiece,
	faShieldAlt,
	faInfoCircle,
	faChevronDown,
	faChevronUp,
	faBox,
	faVideo
} from "@fal"
import { faCircle, faPlay, faTrash, faPlus, faBarcode } from "@fas"
import TradeUnitMasterProductSummary from "@/Components/Goods/TradeUnitMasterProductSummary.vue"
import AttachmentCard from "@/Components/AttachmentCard.vue"
import ImagePrime from "primevue/image"
import Image from "@common/Components/Image.vue"
import { ctrans } from "@/Composables/useTrans"
import ProductCategoryCard from "@/Components/ProductCategoryCard.vue"
import SummaryCard from "@/Components/Goods/SummaryCard.vue"
import SalesAnalysisTeaser from "@/Components/SalesAnalysis/SalesAnalysisTeaser.vue"
import SalesAnalysisMovers from "@/Components/SalesAnalysis/SalesAnalysisMovers.vue"

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

const props = defineProps<{
	handleTabUpdate : Function
	data: {
		tradeUnit: TradeUnit
		brand: {}
		brand_routes: Record<string, routeType>
		tag_routes: Record<string, routeType>
		tags: {}[]
		tags_selected_id: number[]
		gpsr: any
		main_image : any
		images: any[]
		translation_box: {
			title: string
			save_route: routeType
		}
		attachment_box?: {
			public?: string
			private?: string
		}
		properties?: any
	}
	salesAnalysisTeaser?: object
}>()

/* ---------------------------------------
 * Images
 * --------------------------------------- */

const rawImages = props.data?.images ?? []

const imagesSetup = ref(
	rawImages
		.filter(item => item?.type === "image")
		.map(item => ({
			label: item.label,
			column: item.column_in_db,
			images: item.images ?? []
		}))
)

const videoSetup = ref(
	rawImages.find(item => item?.type === "video") || null
)

const validImages = computed(() =>
	imagesSetup.value.flatMap(item => {
		const list = Array.isArray(item.images) ? item.images : [item.images]
		return list
			.filter(v => !!v)
			.map(img => ({
				source: img,
				thumbnail: img
			}))
	})
)

console.log
</script>


<template>
	<div class="w-full  px-4 py-3 mb-3 shadow-sm">
		<span class="text-xl font-semibold text-gray-800 whitespace-pre-wrap">
			<!-- Product name -->
			<span class="align-middle">
				{{ data.tradeUnit.name }}
			</span>
		</span>
	</div>
	<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mx-3 lg:ml-5 lg:mr-3 mt-2">

		<!-- Sidebar -->
		<div class="space-y-3 lg:space-y-6">
			<!-- Image Preview -->
			<ProductCategoryCard subtle :data="data.tradeUnit">
				<template v-if="data.tradeUnit?.tags?.length || data.brand_badge" #beforeImage>
					<div class="flex items-start gap-2 mb-4">
						<div class="font-medium flex flex-wrap gap-1">
							<span v-for="tag in data.tradeUnit?.tags ?? []" :key="tag.id" v-tooltip="'tag'"
								class="px-2 py-0.5 rounded-full text-xs bg-green-50 border border-blue-100">
								{{ tag.name }}
							</span>
						</div>
						<span v-if="data.brand_badge" v-tooltip="ctrans('Brand')"
							class="ml-auto shrink-0 inline-flex items-center gap-1.5 rounded-full border border-gray-300 bg-gray-50 px-2 py-0.5 text-xs font-medium text-gray-700">
							<Image v-if="data.brand_badge.image" :src="data.brand_badge.image" imageCover
								class="h-4 w-4 overflow-hidden rounded-full" />
							{{ data.brand_badge.name }}
						</span>
					</div>
				</template>
				<template v-if="props.data?.main_image?.webp" #image>
					<ImagePrime :src="props.data.main_image.webp" :alt="props?.data?.tradeUnit?.name" preview
						class="block w-full" imageClass="w-full aspect-square object-contain" />
				</template>
			</ProductCategoryCard>
		</div>

		<!-- Trade Unit Summary -->
		<SummaryCard class="min-w-0 self-start">
			<TradeUnitMasterProductSummary
				:attachments="data.attachment_box"
				:publicAttachment="data.attachment_box?.public"
				:data="data.tradeUnit"
				:gpsr="data.gpsr"
				:properties="data.properties"
				:labelInfo="data.label_info"
			/>
		</SummaryCard>

		<!-- Sales -->
		<div class="min-w-0">
			<SalesAnalysisTeaser :teaser="salesAnalysisTeaser" class="mb-4" />
			<SalesAnalysisMovers :teaser="salesAnalysisTeaser" class="mb-4" />
		</div>

		<!-- Attachments -->
		<!-- <div>
			<AttachmentCard :private="data.attachment_box?.private" />
		</div> -->
	</div>
</template>
