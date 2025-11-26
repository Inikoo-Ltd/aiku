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
import { faImage } from "@far"
import ImageProducts from "@/Components/Product/ImageProducts.vue"
import TradeUnitSummary from "@/Components/Goods/TradeUnitSummary.vue"
import AttachmentCard from "@/Components/AttachmentCard.vue"

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
	data: {
		tradeUnit: TradeUnit
		brand: {}
		brand_routes: Record<string, routeType>
		tag_routes: Record<string, routeType>
		tags: {}[]
		tags_selected_id: number[]
		gpsr: any
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
</script>


<template>
	<div class="w-full  px-4 py-3 mb-3 shadow-sm">
		<span class="text-xl font-semibold text-gray-800 whitespace-pre-wrap">
			<!-- Units box -->
			<ProductUnitLabel
				v-if="data.tradeUnit?.units"
				:units="data.tradeUnit?.units"
				:unit="data.tradeUnit?.unit"
				class="mr-2"
			/>
			
			<!-- Product name -->
			<span class="align-middle">
				{{ data.tradeUnit.name }}
			</span>
		</span>
	</div>
	<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mx-3 lg:mx-0 mt-2">

		<!-- Sidebar -->
		<div class="space-y-4 lg:space-y-6">
			<!-- Image Preview -->
			<div class="bg-white rounded-xl p-4 lg:p-5">
				<dd class="font-medium flex flex-wrap gap-1 mb-4">
					<span v-for="tag in data.tradeUnit?.tags ?? []" :key="tag.id" v-tooltip="'tag'"
						class="px-2 py-0.5 rounded-full text-xs bg-green-50 border border-blue-100">
						{{ tag.name }}
					</span>
				</dd>
				<ImageProducts v-if="validImages.length" :images="validImages" :breakpoints="{
					0: { slidesPerView: 3 },
					480: { slidesPerView: 4 },
					640: { slidesPerView: 5 },
					1024: { slidesPerView: 6 }
				}" class="overflow-x-auto" />

				<!-- No Images Fallback -->
				<div v-else
					class="flex flex-col items-center justify-center gap-2 py-8 border-2 border-dashed border-gray-200 rounded-lg h-80">
					<FontAwesomeIcon :icon="faImage" class="text-4xl text-gray-400" />
					<p class="text-sm text-gray-500 text-center">No images uploaded yet</p>
				</div>
			</div>
		</div>

		<!-- Trade Unit Summary -->
		<TradeUnitSummary 
			:publicAttachment="data.attachment_box?.public" 
			:data="data.tradeUnit" 
			:gpsr="data.gpsr"
			:properties="data.properties" 
		/>

		<!-- Attachments -->
		<div>
			<AttachmentCard :private="data.attachment_box?.private" />
		</div>
	</div>
</template>
