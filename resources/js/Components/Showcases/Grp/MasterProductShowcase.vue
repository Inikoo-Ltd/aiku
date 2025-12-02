<script setup lang="ts">

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { computed, ref } from "vue"
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
	faTimesCircle,
	faCheckCircle
} from "@fal"
import { faCircle, faPlay, faTrash, faPlus, faBarcode } from "@fas"
import { faImage } from "@far"
import ImagePrime from "primevue/image"
import { routeType } from "@/types/route"
import { ProductResource } from "@/types/Iris/Products"
import { Image as ImageTS } from "@/types/Image"
import ProductUnitLabel from "@/Components/Utils/Label/ProductUnitLabel.vue"
import TradeUnitMasterProductSummary from "@/Components/Goods/TradeUnitMasterProductSummary.vue"
import AttachmentCard from "@/Components/AttachmentCard.vue"
import { trans } from "laravel-vue-i18n"
import Modal from "@/Components/Utils/Modal.vue"
import { Link, router } from "@inertiajs/vue3"
import { useLayoutStore } from "@/Stores/layout"
import { provide } from "vue"


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

provide("layout", useLayoutStore())
const layout = useLayoutStore()
console.log(layout.app.theme);

const props = defineProps<{
	currency: string,
	handleTabUpdate: Function
	data: {
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

const isModalProductForSale = ref(false);

</script>


<template>
	<div class="w-full  px-4 py-3 mb-3 shadow-sm">
		<span class="text-xl font-semibold whitespace-pre-wrap">
			<ProductUnitLabel v-if="data.masterProduct?.units" :units="data.masterProduct?.units"
				:unit="data.masterProduct?.unit" class="mr-2" />
			<span class="align-middle">
				{{ data.masterProduct.name }}
			</span>
		</span>
		<div v-if="data.availability_status" class="text-md text-gray-800 whitespace-pre-wrap justify-self-end self-center">
			<span 
			v-on:click="isModalProductForSale = true"
			v-tooltip="getTooltips()"
			class="border border-solid hover:opacity-80 py-1 px-3 rounded-md hover:cursor-pointer"
			:class="data.availability_status.is_for_sale ? 'border-green-500' : 'border-red-500'">
				{{ data.availability_status.is_for_sale ? trans('For Sale') : trans('Not For Sale') }} 
				(<span class="font-semibold" :class='data.availability_status.total_product_for_sale != data.availability_status.product.length ? "opacity-80" : ""'>
					{{ `${data.availability_status.total_product_for_sale}/${data.availability_status.product.length}` }}
				</span>)
				<FontAwesomeIcon :icon="data.availability_status.is_for_sale ? faCheckCircle : faTimesCircle" :class="data.availability_status.is_for_sale ? 'text-green-500' : 'text-red-500'"/>
			</span>
		</div>
	</div>

	<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mx-3 lg:mx-0 mt-2">
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
			<div class="bg-white   p-4 lg:p-5">
				<div v-if="props.data?.main_image?.webp" class="max-w-[550px] w-full">
					<ImagePrime :src="props.data?.main_image.webp" :alt="props?.data?.product?.data?.name" preview />
					<div class="text-sm italic text-gray-500">
						See all the images of this product in the tab <span @click="() => handleTabUpdate('images')"
							class="underline text-indigo-500 hover:text-indigo-700 cursor-pointer">images</span>
					</div>
				</div>
				<div v-else>
					<div
						class="flex flex-col items-center justify-center gap-2 py-8 border-2 border-dashed border-gray-200 rounded-lg">
						<FontAwesomeIcon :icon="faImage" class="text-4xl text-gray-400" />
						<p class="text-sm text-gray-500 text-center">No images uploaded yet</p>
					</div>
					<div class="mt-2 text-sm italic text-gray-500">
						Manage images in tab <span @click="() => handleTabUpdate('images')"
							class="underline text-indigo-500 hover:text-indigo-700 cursor-pointer">Media</span>
					</div>
				</div>
			</div>
		</div>

		<TradeUnitMasterProductSummary
			:data="{...data.masterProduct, tags : tradeUnitTags, brands : tradeUnitBrands}" 
			:gpsr="data.gpsr" 
			:properties="data.properties"
			:public-attachment="[]" 
		/>

		<div>
			<AttachmentCard :private="data.attachment_box?.private" />
		</div>
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

        &:hover, &:focus {
            color: v-bind('`${layout.app.theme[7]}`');
        }

        @apply focus:ring-0 focus:outline-none focus:border-none
        bg-no-repeat [background-position:0%_100%]
        transition-all
        [background-size:100%_0.2em]
        motion-safe:transition-all motion-safe:duration-200
        hover:[background-size:100%_100%]
        focus:[background-size:100%_100%] px-1 py-1 lg:py-0.5
    }
</style>
