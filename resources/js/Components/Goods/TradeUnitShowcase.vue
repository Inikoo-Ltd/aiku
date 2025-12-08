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
import TradeUnitMasterProductSummary from "@/Components/Goods/TradeUnitMasterProductSummary.vue"
import AttachmentCard from "@/Components/AttachmentCard.vue"
import ImagePrime from "primevue/image"

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
	<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mx-3 lg:mx-0 mt-2">

		<!-- Sidebar -->
		<div class="space-y-3 lg:space-y-6">
			<!-- Image Preview -->
			<div class="bg-white rounded-xl px-4 lg:p-5">
				<dd class="font-medium flex flex-wrap gap-1 mb-4">
					<span v-for="tag in data.tradeUnit?.tags ?? []" :key="tag.id" v-tooltip="'tag'"
						class="px-2 py-0.5 rounded-full text-xs bg-green-50 border border-blue-100">
						{{ tag.name }}
					</span>
				</dd>
				<div class="bg-white   p-4 lg:p-5">
				<div v-if="props.data?.main_image?.webp" class="max-w-[550px] w-full">
					<ImagePrime :src="props.data.main_image.webp" :alt="props?.data?.tradeUnit?.data?.name" preview />
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
		</div>

		<!-- Trade Unit Summary -->
		<TradeUnitMasterProductSummary 
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
