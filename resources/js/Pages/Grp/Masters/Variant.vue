<script setup lang="ts">
import { Head, useForm, } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { capitalize } from "@/Composables/capitalize"
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
    faLock,
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
import { trans } from "laravel-vue-i18n"
import Modal from "@/Components/Utils/Modal.vue"
import { Link, router } from "@inertiajs/vue3"
import { useLayoutStore } from "@/Stores/layout"
import { provide } from "vue"
import FractionDisplay from '@/Components/DataDisplay/FractionDisplay.vue'
import SalesAnalyticsCompact from '@/Components/Product/SalesAnalyticsCompact.vue'
import LabelSKU from '@/Components/Utils/Product/LabelSKU.vue'


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
    faVideo
)

provide("layout", useLayoutStore())
const layout = useLayoutStore()

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {}
    master_products: Array<any>
}>()

console.log(props)

</script>


<template>
    <Head :title="capitalize(props.title)" />
    <PageHeading :data="props.pageHead" />
    <div class="w-full  px-4 py-3 mb-3 shadow-sm grid grid-cols-2">
        <div class="text-xl font-semibold text-gray-800 whitespace-pre-wrap justify-self-start">
            <ProductUnitLabel v-if="data?.leader_product?.units" :units="data?.leader_product?.units"
                :unit="data?.leader_product?.unit" class="mr-2" />
            <span class="align-middle">
                {{ data?.leader_product?.name }}
            </span>
        </div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mx-3 lg:mx-0 mt-2">
        <div class="space-y-4 lg:space-y-6">
            <div class="bg-white   p-4 lg:p-5">
                <div v-if="props.data?.main_image?.webp" class="max-w-[550px] w-full">
                    <ImagePrime :src="props.data?.main_image.webp" :alt="props?.data?.product?.data?.name" preview />
                </div>
                <div v-else>
                    <div
                        class="flex flex-col items-center justify-center gap-2 py-8 border-2 border-dashed border-gray-200 rounded-lg">
                        <FontAwesomeIcon :icon="faImage" class="text-4xl text-gray-400" />
                        <p class="text-sm text-gray-500 text-center">No images uploaded yet</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2">
            sdfsfd
            <!-- <TradeUnitMasterProductSummary
				:data="{...data.masterProduct, tags : tradeUnitTags, brands : tradeUnitBrands}"
				:gpsr="data.gpsr"
				:properties="data.properties"
				:attachments="data.attachment_box"
			/> -->
        </div>

        <div>
            sdfsdf
            <!--   <SalesAnalyticsCompact :salesData="salesData" class="mr-2" /> -->
        </div>
    </div>
</template>

<style lang="scss"></style>
