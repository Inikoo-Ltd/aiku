<script setup lang="ts">
import { faCube, faLink, faHeart } from "@fal"
import { faCircle, faHeart as fasHeart, faDotCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ref, inject, onMounted} from "vue"
import ImageProducts from "@/Components/Product/ImageProducts.vue"
import { useLocaleStore } from '@/Stores/locale'
import ProductContentsIris from "./ProductContentIris.vue"
import InformationSideProduct from "./InformationSideProduct.vue"
import Image from "@/Components/Image.vue"
import { notify } from "@kyvg/vue3-notification"
import ButtonAddPortfolio from "@/Components/Iris/Products/ButtonAddPortfolio.vue"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import { Image as ImageTS } from '@/types/Image'
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { set } from "lodash-es"
import { getStyles } from "@/Composables/styles"
import axios from "axios"

library.add(faCube, faLink)

interface ProductResource {
    id: number
    name: string
    code: string
    image?: {
        source: ImageTS
    }
    currency_code: string
    rpp?: number
    unit: string
    stock: number
    rating: number
    price: number
    url: string | null
    units: number
    bestseller?: boolean
    is_favourite?: boolean
    exist_in_portfolios_channel: number[]
    is_exist_in_all_channel: boolean
}

const props = withDefaults(defineProps<{
    fieldValue: any
    webpageData?: any
    blockData?: object
	screenType: 'mobile' | 'tablet' | 'desktop'
}>(), {
})
const layout = inject('layout',{})
const currency = layout?.iris?.currency
const locale = useLocaleStore()
const isFavorite = ref(false)
const contentRef = ref(null)
const expanded = ref(false)
const showButton = ref(false)


function formatNumber(value: Number) {
    return Number.parseFloat(value).toString()
}


// Section: Add to Favourites
const isLoadingFavourite = ref(false)
const onAddFavourite = (product: ProductResource) => {
    router.post(
        route('iris.models.favourites.store', {
            product: product.id
        }),
        {
            // item_id: [product.id]
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingFavourite.value = true
            },
            onSuccess: () => {
                set(props.fieldValue.product, 'is_favourite', true)
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to add the product to favourites"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingFavourite.value = false
            },
        }
    )
}
const onUnselectFavourite = (product: ProductResource) => {
    router.delete(
        route('iris.models.favourites.delete', {
            product: product.id
        }),
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingFavourite.value = true
            },
            onSuccess: () => {
                set(props.fieldValue.product, 'is_favourite', false)
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to remove the product from favourites"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingFavourite.value = false
            },
        }
    )
}

// Method: to fetch the product existence in channels
const isLoadingFetchExistenceChannels = ref(false)
const productExistenceInChannels = ref<number[]>([])
const fetchProductExistInChannel = async () => {
    isLoadingFetchExistenceChannels.value = true
    try {
        const response = await axios.get(
            route(
                'iris.json.customer.product.channel_ids.index',
                {
                    customer: layout.iris?.customer?.id,
                    product: props.fieldValue.product.id,
                }
            )
        )

        if (response.status !== 200) {
            throw new Error('Failed to fetch product existence in channel')
        }

        // console.log('Product exist in channel response:', response.data)
        productExistenceInChannels.value = response.data || []
    } catch (error: any) {
        notify({
            title: trans('Something went wrong'),
            text: error.message,
            type: 'error'
        })
    } finally {
        isLoadingFetchExistenceChannels.value = false
    }
}


onMounted(() => {
    if (layout.iris?.customer && layout?.iris?.is_logged_in) {
        fetchProductExistInChannel()
    }
    requestAnimationFrame(() => {
        if (contentRef.value.scrollHeight > 100) {
            showButton.value = true
        }
    })
})

const toggleExpanded = () => {
  expanded.value = !expanded.value
}

</script>

<template>
    <div id="product-1"  :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            marginLeft : 'auto', marginRight : 'auto'
		}" class="mx-auto max-w-7xl py-8 text-gray-800 overflow-hidden px-6 hidden sm:block">
        <div class="grid grid-cols-12 gap-x-10 mb-2"> 
            <div class="col-span-7">
                <div class="py-1 w-full">
                    <ImageProducts :images="fieldValue.product.images" />
                </div>
                <div class="flex gap-x-10 text-gray-400 mb-6 mt-4">
                    <div class="flex items-center gap-1 text-xs" v-for="(tag, index) in fieldValue.product.tags"
                        :key="index">
                        <FontAwesomeIcon v-if="!tag.image" :icon="faDotCircle" class="text-sm" />
                        <div v-else class="aspect-square w-full h-[15px]">
                            <Image :src="tag?.image" :alt="`Thumbnail tag ${index}`"
                                class="w-full h-full object-cover" />
                        </div>
                        <span>{{ tag.name }}</span>
                    </div>
                </div>
            </div>
            <div class="col-span-5 self-start">
                <div class="flex justify-between mb-4 items-start">
                    <div class="w-full">
                        <h1 class="text-2xl font-bold text-gray-900">{{ fieldValue.product.name }}</h1>
                        <div class="flex flex-wrap justify-between gap-x-10 text-sm font-medium text-gray-600 mt-1 mb-1">
                            <div>Product code: {{ fieldValue.product.code }}</div>
                            <div class="flex items-center gap-[1px]">
                                <a :href="route('iris.catalogue.feeds.product.download', {product: fieldValue.product.slug})" target="_blank" class="hidden">
                                    download
                                </a>
                            </div>
                        </div>
                        <div v-if="layout?.iris?.is_logged_in" class="flex items-center gap-2 text-sm text-gray-600 mb-4">
                            <FontAwesomeIcon :icon="faCircle" class="text-[10px]"
                                :class="fieldValue.product.stock > 0 ? 'text-green-600' : 'text-red-600'" />
                            <span>
                                {{
                                fieldValue.product.stock > 0
                                ? trans('In stock')+` (${fieldValue.product.stock} `+trans('available')+`)`
                                : trans('Out Of Stock')
                                }}
                            </span>
                        </div>
                    </div>
                    <div class="h-full flex items-start">
                        <!-- Favorite Icon -->
                        <template v-if="layout?.retina?.type != 'dropshipping' && layout.iris?.is_logged_in">
                            <div v-if="isLoadingFavourite" class="top-2 right-2 text-gray-500 text-2xl">
                                <LoadingIcon />
                            </div>
                            <div v-else
                                @click="() => fieldValue.product.is_favourite ? onUnselectFavourite(fieldValue.product) : onAddFavourite(fieldValue.product)"
                                class="cursor-pointer top-2 right-2 group text-2xl ">
                                <FontAwesomeIcon v-if="fieldValue.product.is_favourite" :icon="fasHeart" fixed-width
                                    class="text-pink-500" />
                                <span v-else class="">
                                    <FontAwesomeIcon
                                        :icon="fasHeart"
                                        fixed-width
                                        class="hidden group-hover:inline text-pink-400"
                                    />
                                    <FontAwesomeIcon
                                        :icon="faHeart"
                                        fixed-width
                                        class="inline group-hover:hidden text-pink-300"
                                    />
                                </span>
                            </div>
                        </template>
                    </div>
                </div>
                <div v-if="layout?.iris?.is_logged_in" class="flex items-end pb-3 mb-3">
                    <div class="text-gray-900 font-semibold text-3xl capitalize leading-none flex-grow min-w-0">
                        {{ locale.currencyFormat(currency?.code, fieldValue.product.price || 0) }}

                    </div>
                    <div v-if="fieldValue.product.rrp"
                        class="text-sm text-gray-800 font-semibold text-right whitespace-nowrap pl-4">
                        <span>RRP: {{ locale.currencyFormat(currency?.code, fieldValue.product.rrp || 0) }}</span>
                    </div>
                </div>

                <!-- Section: Button existence on all channels -->
                <div class="relative flex gap-2 mb-6">
                    <ButtonAddPortfolio
                        :product="fieldValue.product"
                        :productHasPortfolio="productExistenceInChannels"
                    />

                    <!-- Skeleton loading -->
                    <div v-if="isLoadingFetchExistenceChannels" class="absolute h-full w-full z-10">
                        <div class="h-full w-full skeleton rounded" />
                    </div>

                </div>



                <div class="text-sm font-medium text-gray-800" :style="getStyles(fieldValue?.description?.description_title, screenType)">
                    <div>{{ fieldValue.product.description_title }}</div>
                </div>
            
                <div class="text-xs font-medium text-gray-800" :style="getStyles(fieldValue?.description?.description_content, screenType)">
                    <div v-html="fieldValue.product.description"></div>
                </div>
                <div v-if="fieldValue.setting?.information" class="my-4 space-y-2">
                    <InformationSideProduct v-if="fieldValue?.information?.length > 0"
                        :informations="fieldValue?.information" :styleData="fieldValue?.information_style"/>
                    <div v-if="fieldValue?.paymentData?.length > 0"
                        class="items-center gap-3  border-gray-400 font-bold text-gray-800 py-2" :style="getStyles(fieldValue?.information_style?.title)">
                        Secure Payments:
                        <div class="flex flex-wrap items-center gap-6 border-gray-400 font-bold text-gray-800 py-2">
                            <img v-for="logo in fieldValue?.paymentData" :key="logo.code" v-tooltip="logo.code"
                                :src="logo.image" :alt="logo.code" class="h-4 px-1" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-xs font-normal text-gray-700 my-6" :style="getStyles(fieldValue?.description?.description_extra, screenType)">
            <div ref="contentRef" 
                class="prose prose-sm text-gray-700 max-w-none transition-all duration-300 overflow-hidden"
                :style="{ maxHeight: expanded ? 'none' : '100px' }" v-html="fieldValue.product.description_extra"></div>

            <button v-if="showButton" @click="toggleExpanded"
                class="mt-1 text-gray-900 text-xs underline focus:outline-none">
                {{ expanded ? 'Show Less' : 'Read More' }}
            </button>
        </div>
        <ProductContentsIris :product="props.fieldValue.product" :setting="fieldValue.setting" :styleData="fieldValue?.information_style"/>
    </div>

    <!-- Mobile Layout -->
    <div class="block sm:hidden px-4 py-6 text-gray-800">
        <h2 class="text-xl font-bold mb-2">{{ fieldValue.product.name }}</h2>
        <ImageProducts :images="fieldValue.product.images" />
        <div class="flex justify-between items-start gap-4 mt-4">
            <!-- Price + Unit Info -->
            <div v-if="layout?.iris?.is_logged_in">
                <div class="text-lg font-semibold">
                    {{ locale.currencyFormat(currency?.code, fieldValue.product.price || 0) }}
                    <span class="text-xs text-gray-500 ml-1">
                        ({{ formatNumber(fieldValue.product.units) }}/{{ fieldValue.product.unit }})
                    </span>
                </div>
                <div v-if="fieldValue.product.rrp" class="text-xs text-gray-400 font-semibold mt-1">
                    RRP: {{ locale.currencyFormat(currency?.code, fieldValue.product.rrp || 0) }}
                </div>
            </div>

            <!-- Favorite Icon -->
            <div v-if="layout?.retina?.type != 'dropshipping' && layout.iris?.is_logged_in" class="mt-1">
                <FontAwesomeIcon :icon="faHeart" class="text-xl cursor-pointer transition-colors duration-300"
                    :class="{ 'text-red-500': isFavorite, 'text-gray-400 hover:text-red-500': !isFavorite }"
                    @click="() => fieldValue.product.is_favourite ? onUnselectFavourite(fieldValue.product) : onAddFavourite(fieldValue.product)" />
            </div>
        </div>


        <div class="flex flex-wrap gap-2 mt-4">
            <div class="text-xs flex items-center gap-1 text-gray-500" v-for="(tag, index) in fieldValue.product.tags"
                :key="index">
                <FontAwesomeIcon v-if="!tag.image" :icon="faDotCircle" class="text-sm" />
                <div v-else class="aspect-square w-full h-[15px]">
                    
                    <Image :src="tag?.image" :alt="`Thumbnail tag ${index}`" class="w-full h-full object-cover" />
                </div>
                <span>{{ tag.name }}</span>
            </div>
        </div>
        <div class="mt-6 flex flex-col gap-2">
            <ButtonAddPortfolio :product="fieldValue.product" :productHasPortfolio="fieldValue.productChannels" />
        </div>
        <div class="text-xs font-medium py-3">
            <div v-html="fieldValue.product.description"></div>
        </div>


        <div class="mt-4">
            <InformationSideProduct v-if="fieldValue?.information?.length > 0"
                :informations="fieldValue?.information" :styleData="fieldValue?.information_style"/>
            <div class="text-sm font-semibold mb-2">Secure Payments:</div>
            <div class="flex flex-wrap gap-4">
                <img v-for="logo in fieldValue?.paymentData" :key="logo.code" v-tooltip="logo.code" :src="logo.image"
                    :alt="logo.code" class="h-4 px-1" />
            </div>

        </div>

        <div class="text-xs font-normal text-gray-700 my-6">
            <div class="prose prose-sm text-gray-700 max-w-none" v-html="fieldValue.product.description_extra"></div>
        </div>

        <ProductContentsIris :product="props.fieldValue.product" :setting="fieldValue.setting"  :styleData="fieldValue?.information_style"/>
    </div>

</template>
