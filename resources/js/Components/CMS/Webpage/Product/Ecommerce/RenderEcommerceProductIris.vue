<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faFilePdf, faFileDownload } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Image as ImageTS } from "@/types/Image"
import { getProductRenderB2bComponent } from "@/Composables/getIrisComponents"
import { ref, inject, onMounted, computed, watch, onUnmounted } from "vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import { set, isArray } from "lodash-es"
import axios from "axios"
import { ulid } from "ulid"

library.add(faCube, faLink, faFilePdf, faFileDownload)

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
    code: string
    screenType: "mobile" | "tablet" | "desktop"
}>(), {})

const layout = inject("layout", {})
const product = ref(props.fieldValue.product)
const isLoadingRemindBackInStock = ref(false)
const customerData = ref(null)
const keyCustomer = ref(ulid())
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


watch(() => layout?.iris?.is_logged_in, (newVal) => {
    if (newVal) {
        getOrderingProduct()
    }
}, {
    immediate: true
})

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
    <component 
        :is="getProductRenderB2bComponent(code)" 
        :fieldValue 
        :webpageData 
        :blockData 
        :isLoadingFavourite
        :isLoadingRemindBackInStock
        :product
        :customerData
        :validImages
        :videoSetup
        :screenType
        @setFavorite="onAddFavourite"
        @unsetFavorite="onUnselectFavourite"
        @setBackInStock="onAddBackInStock"
        @unsetBackInStock="onUnselectBackInStock"
    />
</template>