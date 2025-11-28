<script setup lang="ts">
import { faCube, faLink, faHeart } from "@fal"
import { faCircle, faHeart as fasHeart, faDotCircle, faPlus, faMinus } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ref, inject, onMounted, computed, watch, onUnmounted } from "vue"
import ImageProducts from "@/Components/Product/ImageProducts.vue"
import ProductContentsIris from "./ProductContentIris.vue"
import InformationSideProduct from "./InformationSideProduct.vue"
import Image from "@/Components/Image.vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import { Image as ImageTS } from "@/types/Image"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { set, isArray } from "lodash-es"
import { getStyles } from "@/Composables/styles"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { urlLoginWithRedirect } from "@/Composables/urlLoginWithRedirect"
import { faEnvelope } from "@far"
import { faEnvelopeCircleCheck } from "@fortawesome/free-solid-svg-icons"
import EcomAddToBasketv2 from "@/Components/Iris/Products/EcomAddToBasketv2.vue"
import LinkIris from "@/Components/Iris/LinkIris.vue"
import axios from "axios"
import { ulid } from "ulid"
import ProductPrices from "./ProductPrices.vue"

library.add(faCube, faLink, faPlus, faMinus)

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
    screenType: "mobile" | "tablet" | "desktop"
}>(), {})

const layout = inject("layout", {})
const isFavorite = ref(false)
const product = ref(props.fieldValue.product)
const contentRef = ref<Element | null>(null)
const expanded = ref(false)
const isLoadingRemindBackInStock = ref(false)
const customerData = ref(null)
const keyCustomer = ref(ulid())


// Section: Add to Favourites
const isLoadingFavourite = ref(false)
const onAddFavourite = (product: ProductResource) => {
    router.post(
        route("iris.models.favourites.store", {
            product: product.id
        }),
        {
            // item_id: [product.id]
        },
        {
            preserveScroll: true,
            preserveState: true,
            only: ["iris"],
            onStart: () => {
                isLoadingFavourite.value = true
            },
            onSuccess: () => {
                set(customerData.value, "is_favourite", true)
                layout.reload_handle()
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
            }
        }
    )
}

const onUnselectFavourite = (product: ProductResource) => {
    router.delete(
        route("iris.models.favourites.delete", {
            product: product.id
        }),
        {
            preserveScroll: true,
            preserveState: true,
            only: ["iris"],
            onStart: () => {
                isLoadingFavourite.value = true
            },
            onSuccess: () => {
                set(customerData.value, "is_favourite", false)

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
            }
        }
    )
}

const onAddBackInStock = (productData: ProductResource) => {
    router.post(
        route("iris.models.remind_back_in_stock.store", {
            product: productData.id
        }),
        {
            // item_id: [product.id]
        },
        {
            preserveScroll: true,
            preserveState: true,
            only: ["iris"],
            onStart: () => {
                isLoadingRemindBackInStock.value = true
            },
            onSuccess: () => {
                set(product.value, "is_back_in_stock", true)
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to add the product to remind back in stock"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingRemindBackInStock.value = false
            }
        }
    )
}

const onUnselectBackInStock = (productData: ProductResource) => {
    router.delete(
        route("iris.models.remind_back_in_stock.delete", {
            product: productData.id
        }),
        {
            preserveScroll: true,
            preserveState: true,
            only: ["iris"],
            onStart: () => {
                isLoadingRemindBackInStock.value = true
            },
            onSuccess: () => {
                set(product.value, "is_back_in_stock", false)
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to remove the product from remind back in stock"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingRemindBackInStock.value = false
            }
        }
    )
}

const getOrderingProduct = async () => {
    isLoadingRemindBackInStock.value = true

    try {
        const url = route("iris.json.product.ecom_ordering_data", { product: product.value.id })
        const response = await axios.get(url, {
            params: {},
        })

        // Update the local state
        keyCustomer.value = ulid()
        customerData.value = response.data
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to remove the product from remind back in stock"),
            type: "error",
        })
        console.error(error)
        return null
    } finally {
        isLoadingRemindBackInStock.value = false
    }
}


watch(() => layout?.iris?.is_logged_in, (newVal) => {
    if (newVal) {
        getOrderingProduct()
    }
}, {
    immediate: true
})



const toggleExpanded = () => {
    expanded.value = !expanded.value
}




const imagesSetup = ref(isArray(product.value.images) ? product.value.images :
    product.value.images
        .filter(item => item.type == "image")
        .map(item => ({
            label: item.label,
            column: item.column_in_db,
            images: item.images
        }))
)

const videoSetup = ref(
    product.value.images.find(item => item.type === "video") || null
)


const validImages = computed(() => {
    if (!imagesSetup.value) return []

    const hasType = imagesSetup.value.some(item => "type" in item)

    if (hasType) {
        return imagesSetup.value
            .filter(item => item.images)
            .flatMap(item => {
                const images = Array.isArray(item.images) ? item.images : [item.images]
                return images.map(img => ({
                    source: img,
                    thumbnail: img
                }))
            })
    }

    return imagesSetup.value
})

const fetchData = async () => {
  try {
    const response = await axios.get(
      route("iris.catalogue.product.resource", {
        product: product.value.slug
      })
    )
    product.value = {...product.value, ...response.data}
  } catch (error: any) {
    console.error("cannot break cached cuz", error)
  }
}



watch(
  () => props.fieldValue.product,
  newVal => {
    product.value = { ...newVal }
  },
  { deep: true }
)


onMounted(() => {
    set(layout, "temp.fetchIrisProductCustomerData", getOrderingProduct)
    if (props.fieldValue?.product?.luigi_identity) {
        window?.dataLayer?.push({
            event: "view_item",
            ecommerce: {
                items: [
                    {
                        item_id: props.fieldValue?.product?.luigi_identity
                    }
                ]
            }
        })
    }
    if (layout?.iris?.is_logged_in) {
        fetchData()
    }
})

onUnmounted(() => {
    if (layout?.temp?.fetchIrisProductCustomerData) {
        delete layout.temp.fetchIrisProductCustomerData
    }
})


</script>

<template>
    <div v-if="screenType !== 'mobile'" id="product-1" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        marginLeft: 'auto', marginRight: 'auto'
    }" class="mx-auto max-w-7xl py-8 text-gray-800 overflow-hidden px-6 hidden sm:block">
        <div class="grid grid-cols-12 gap-x-10 mb-2">
            <div class="col-span-7">
                <div class="py-1 w-full">
                    <ImageProducts :images="validImages" :video="videoSetup?.url" />
                </div>
                <div class="flex gap-x-10 text-gray-400 mb-6 mt-4">
                    <div class="flex items-center gap-1 text-xs" v-for="(tag, index) in product.tags"
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
                <!-- Header: Title, product code, stocks -->
                <div class="relative flex justify-between mb-4 items-start">
                    <div class="w-full">
                        <h1 class="!text-3xl font-bold">
                            <span v-if="Number(product.units) > 1">{{ Number(product.units)
                                }}x</span> {{ product.name }}
                        </h1>

                        <div class="flex flex-wrap gap-x-10 text-sm font-medium text-gray-600 mt-1 mb-1">
                            <div>{{ trans("Product code") }}: {{ product.code }}</div>
                            <!-- <div class="flex items-center gap-[1px]">
                            </div> -->
                        </div>

                        <div v-if="layout?.iris?.is_logged_in" class="flex items-center justify-between">
                            <!-- Stock info -->
                            <div class="flex items-center gap-2 text-sm">
                                <FontAwesomeIcon :icon="faCircle" class="text-[10px]"
                                    :class="product.stock > 0 ? 'text-green-600' : 'text-red-600'" />
                                <span>
                                    {{
                                    customerData?.stock > 0
                                    ? trans("In stock") +
                                    ` (${customerData?.stock} ` +
                                    trans("available") +
                                    `)`
                                    : trans("Out Of Stock")
                                    }}
                                </span>
                            </div>

                            <!-- Remind me button absolute -->
                            <button v-if="product.stock <= 0 && layout?.app?.environment === 'local'"
                                @click="() => product.is_back_in_stock ? onUnselectBackInStock(product) : onAddBackInStock(product)"
                                class="absolute right-0 bottom-2 inline-flex items-center gap-2 rounded-full border border-gray-300 bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-200 hover:border-gray-400">
                                <LoadingIcon v-if="isLoadingRemindBackInStock" />
                                <FontAwesomeIcon v-else
                                    :icon="product.is_back_in_stock ? faEnvelopeCircleCheck : faEnvelope"
                                    :class="[product.is_back_in_stock ? 'text-green-600' : 'text-gray-600']" />
                                <span>{{ product.is_back_in_stock ? trans("will be notified when in Stock") :
                                    trans("Remind me") }}</span>
                            </button>
                        </div>

                    </div>

                    <div class="h-full flex items-start">
                        <!-- Favorite Icon -->
                        <template v-if="layout?.retina?.type != 'dropshipping' && layout.iris?.is_logged_in">
                            <div v-if="isLoadingFavourite" class="top-2 right-2 text-gray-500 text-2xl">
                                <LoadingIcon />
                            </div>
                            <div v-else
                                @click="() => customerData?.is_favourite ? onUnselectFavourite(product) : onAddFavourite(product)"
                                class="cursor-pointer top-2 right-2 group text-2xl ">
                                <FontAwesomeIcon v-if="customerData?.is_favourite" :icon="fasHeart" fixed-width
                                    class="text-pink-500" />
                                <span v-else class="">
                                    <FontAwesomeIcon :icon="fasHeart" fixed-width
                                        class="hidden group-hover:inline text-pink-300" />
                                    <FontAwesomeIcon :icon="faHeart" fixed-width
                                        class="inline group-hover:hidden text-pink-300" />
                                </span>
                            </div>
                        </template>
                    </div>
                </div>


                <ProductPrices :field-value="fieldValue" />

                <!-- Section: Button add to cart -->
                <div class="relative flex gap-2 mb-6">
                    <div v-if="layout?.iris?.is_logged_in && customerData" class="w-full">
                        <!-- <ButtonAddToBasket v-if="fieldValue.product.stock > 0" :product="fieldValue.product" /> -->
                        <EcomAddToBasketv2
                            v-if="product.stock > 0"
                            :product="product"
                            :customerData="customerData"
                            :key="keyCustomer"
                            :buttonStyle="getStyles(fieldValue?.button?.properties, screenType)"
                        />

                        <div v-else>
                            <Button :label="trans('Out of stock')" type="tertiary" disabled full />
                        </div>
                    </div>

                    <LinkIris v-else :href="urlLoginWithRedirect()" :style="getStyles(fieldValue?.buttonLogin?.properties, screenType)"
                        class="block text-center border border-gray-200 text-sm px-3 py-2 rounded text-gray-600 w-full">
                        {{ trans("Login or Register for Wholesale Prices") }}
                    </LinkIris>
                </div>

                <!-- <pre>customerData: {{ customerData }}</pre>
                <pre>layout?.temp_irisProduct: {{ layout?.temp_irisProduct }}</pre> -->

                <!-- Section: Product Description -->
                <div class="text-xs font-medium text-gray-800"
                    :style="getStyles(fieldValue?.description?.description_content, screenType)">
                    <div v-html="product.description" />

                    <div v-if="expanded" class="text-xs font-normal text-gray-700 my-1"
                        :style="getStyles(fieldValue?.description?.description_extra, screenType)">
                        <div ref="contentRef"
                            class="prose prose-sm text-gray-700 max-w-none transition-all duration-300 overflow-hidden"
                            v-html="product.description_extra"
                        />
                    </div>

                    <button v-if="product.description_extra" @click="toggleExpanded"
                        class="mt-1 text-xs underline focus:outline-none">
                        {{ expanded ? trans("Show Less") : trans("Read More") }}
                    </button>
                </div>

                <!-- Section: Product Specifications & Documentations -->
                <ProductContentsIris
                    class="mt-6"
                    :product="product"
                    :setting="fieldValue.setting"
                    :styleData="fieldValue?.information_style"
                    :fullWidth="true"
                />
                
                <div v-if="fieldValue.setting?.information" class="mt-2">
                    <InformationSideProduct
                        v-if="fieldValue?.information?.length > 0"
                        :informations="fieldValue?.information"
                        :styleData="fieldValue?.information_style"
                    />

                    <!-- Section: Secure Payments -->
                    <h2 v-if="fieldValue?.paymentData?.length > 0"
                        class="!text-base !font-semibold items-center gap-3 text-gray-800"
                        :style="getStyles(fieldValue?.information_style?.title)">
                        {{ trans("Secure Payments") }}:
                    </h2>
                    <div class="flex flex-wrap items-center gap-6 font-bold text-gray-800 py-2">
                        <img
                            v-for="logo in fieldValue?.paymentData"
                            :key="logo.code"
                            v-tooltip="logo.code"
                            :src="logo.image"
                            :alt="logo.code"
                            class="h-4 px-1"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Layout -->
    <div v-else class="block sm:hidden px-4 py-6 text-gray-800">
        <h1 class="text-xl font-bold mb-2">
            <span v-if="Number(product.units) > 1">{{ Number(product.units) }}x</span> {{ product.name }}
        </h1>
        <ImageProducts :images="validImages" :video="videoSetup?.url" />
        <div class="flex justify-between items-start gap-4 mt-4">
            <!-- Price + Unit Info -->
            <div v-if="layout?.iris?.is_logged_in">
                <ProductPrices :field-value="fieldValue" />
            </div>

            <!-- Favorite Icon -->
            <div v-if="layout?.retina?.type != 'dropshipping' && layout.iris?.is_logged_in" class="mt-1">
                <FontAwesomeIcon :icon="faHeart" class="text-xl cursor-pointer transition-colors duration-300"
                    :class="{ 'text-red-500': isFavorite, 'text-gray-400 hover:text-red-500': !isFavorite }"
                    @click="() => customerData?.is_favourite ? onUnselectFavourite(product) : onAddFavourite(product)" />
            </div>
        </div>


        <div class="flex flex-wrap gap-2 mt-4">
            <div class="text-xs flex items-center gap-1 text-gray-500" v-for="(tag, index) in product.tags"
                :key="index">
                <FontAwesomeIcon v-if="!tag.image" :icon="faDotCircle" class="text-sm" />
                <div v-else class="aspect-square w-full h-[15px]">

                    <Image :src="tag?.image" :alt="`Thumbnail tag ${index}`" class="w-full h-full object-cover" />
                </div>
                <span>{{ tag.name }}</span>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-2">
            <!-- <ButtonAddToBasket :product="fieldValue.product" /> -->
            <div v-if="layout?.iris?.is_logged_in" class="w-full">
                <!-- <ButtonAddToBasket v-if="fieldValue.product.stock > 0" :product="fieldValue.product" /> -->
                <EcomAddToBasketv2 v-if="product.stock > 0" :customerData="customerData" :product="product"  :buttonStyle="getStyles(fieldValue?.button?.properties, screenType)" />

                <div v-else>
                    <Button :label="trans('Out of stock')" type="tertiary" disabled full  :inject-style="getStyles(fieldValue?.buttonLogin?.properties, screenType)"/>
                </div>
            </div>

            <LinkIris v-else :href="urlLoginWithRedirect()" :style="getStyles(fieldValue?.button?.properties, screenType)"
                class="block text-center border border-gray-200 text-sm px-3 py-2 rounded text-gray-600 w-full">
                {{ trans("Login or Register for Wholesale Prices") }}
            </LinkIris>
        </div>

        <div class="mt-4 text-xs font-medium py-3">
            <div v-html="product.description"></div>
            <div class="text-xs font-normal text-gray-700 my-1">
                <div class="prose prose-sm text-gray-700 max-w-none" v-html="product.description_extra">
                </div>
            </div>
        </div>


        <div class="mt-4">
            <ProductContentsIris :product="product" :setting="fieldValue.setting"
                :styleData="fieldValue?.information_style" />
            <InformationSideProduct v-if="fieldValue?.information?.length > 0" :informations="fieldValue?.information"
                :styleData="fieldValue?.information_style" />
            <h2 class="!text-base !font-semibold !mb-2">{{ trans("Secure Payments") }}:</h2>
            <div class="flex flex-wrap gap-4">
                <img v-for="logo in fieldValue?.paymentData" :key="logo.code" v-tooltip="logo.code" :src="logo.image"
                    :alt="logo.code" class="h-4 px-1" />
            </div>
        </div>
    </div>

</template>
