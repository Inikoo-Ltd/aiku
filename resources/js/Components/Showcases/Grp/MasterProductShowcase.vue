<script setup lang="ts">

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ref, computed, inject } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
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
	faVideo,
} from "@fal"
import { faCircle, faPlay, faTrash, faPlus, faBarcode } from "@fas"
import ImageProducts from "@/Components/Product/ImageProducts.vue"
import { faImage } from "@far"
import ProductSummary from "@/Components/Product/ProductSummary.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { trans } from "laravel-vue-i18n"


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

// Interfaces
interface TradeUnit {
  id: number;
  name: string;
  code?: string;
  image?: {
    thumbnail: string;
  };
}

interface ProductItem {
  product_id: number;
  name: string;
  code?: string;
  shop_id: number;
  shop_name: string;
  shop_currency: string;
  price: number | string;
  update_route: {
    name: string;
    parameters: Record<string, any>;
  };
}

interface ProductData {
  id: number;
  name: string;
  image?: {
    source: string;
  };
  
  trade_units: TradeUnit[];
  products: ProductItem[];
}

const props = defineProps<{
  currency : string
  data: {
    data: ProductData;
  };
}>();
console.log(props)

const imagesSetup = ref(
	props.data.images
		.filter(item => item.type === "image")
		.map(item => ({
			label: item.label,
			column: item.column_in_db,
			images: item.images,
		}))
)

const videoSetup = ref(
	props.data.images.find(item => item.type === "video") || null
)

const images = computed(() => props.data?.images ?? [])


const validImages = computed(() =>
  imagesSetup.value
    .filter(item => item.images) // only keep if images exist
    .flatMap(item => {
      const images = Array.isArray(item.images) ? item.images : [item.images] // normalize to array
      return images.map(img => ({
        source: img,
        thumbnail: img
      }))
    })
)


const locale = inject("locale", aikuLocaleStructure)

</script>


<template>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mx-3 lg:mx-0 mt-2">
		<!-- Sidebar -->
		<div class="space-y-4 lg:space-y-6">
			<!-- Image Preview & Thumbnails -->
			<div class="bg-white rounded-xl  p-4 lg:p-5">
				<ImageProducts v-if="validImages.length" :images="validImages" :breakpoints="{
					0: { slidesPerView: 3 },
					480: { slidesPerView: 4 },
					640: { slidesPerView: 5 },
					1024: { slidesPerView: 6 },
				}" class="overflow-x-auto" />

				<div v-else
					class="flex flex-col items-center justify-center gap-2 py-8 border-2 border-dashed border-gray-200 rounded-lg h-80">
					<FontAwesomeIcon :icon="faImage" class="text-4xl text-gray-400" />
					<p class="text-sm text-gray-500 text-center">No images uploaded yet</p>
				</div>
			</div>
		</div>
		<!-- tradeUnit Summary -->
		<ProductSummary :data="data.masterProduct" :video="videoSetup?.url" :hide="['price', 'rrp', 'stock']" />


		<div class="bg-white h-fit mx-4 rounded-xl px-4 py-2 lg:p-6 lg:py-4 shadow-sm border border-gray-100">
			<dl class="space-y-2 text-sm">

				<!-- Stock -->
				<div class="flex justify-between items-center flex-wrap gap-2">
					<dt class="text-gray-500">{{ trans("Stock") }}</dt>
					<dd class="flex items-center gap-2 font-medium">
						<FontAwesomeIcon :icon="['fas', 'circle']" :class="[
							data.masterProduct.data?.stock > 20
								? 'text-green-500'
								: data.masterProduct.data?.stock > 0
									? 'text-orange-500'
									: 'text-red-500'
						]" />
						<span :class="[
							data.masterProduct.data?.stock > 20
								? 'text-green-600'
								: data.masterProduct.data?.stock > 0
									? 'text-orange-600'
									: 'text-red-600 font-semibold'
						]">
							{{ data.masterProduct.data?.stock }} {{ data.masterProduct.data?.unit }}
						</span>
					</dd>
				</div>

				<hr class="border-gray-200" />

				<!-- Cost -->
				<div class="flex justify-between items-center flex-wrap gap-2">
					<dt class="text-gray-500">{{ trans("Cost") }}</dt>
					<dd class="font-medium text-blue-600">
						{{ locale.currencyFormat(data.masterProduct.data?.currency_code, data.masterProduct.data?.cost) || '-' }}
					</dd>
				</div>

				<!-- Price -->
				<div class="flex justify-between items-center flex-wrap gap-2">
					<dt class="text-gray-500">{{ trans("Price") }}</dt>
					<dd class="font-semibold text-green-600 text-lg">
						{{ locale.currencyFormat(data.masterProduct.data?.currency_code, data.masterProduct.data?.price) }}
					</dd>
				</div>

				<!-- RRP -->
				<div class="flex justify-between items-center flex-wrap gap-2">
					<dt class="text-gray-500">RRP</dt>
					<dd class="flex items-center gap-2 font-semibold text-gray-700">
						<span>
							{{ locale.currencyFormat(data.masterProduct.data?.currency_code, data.masterProduct.data?.rrp) }}
						</span>
						<span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600">
							({{ ((data.masterProduct.data?.rrp - data.masterProduct.data?.price) / data.masterProduct.data?.price *
								100).toFixed(2) }}%)
						</span>
					</dd>
				</div>
			</dl>
		</div>
	</div>
</template>
