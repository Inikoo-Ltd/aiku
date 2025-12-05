<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faFilePdf, faFileDownload } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, inject, onMounted, computed, watch } from "vue"
import { Image as ImageTS } from "@/types/Image"
import { isArray } from "lodash-es"
import axios from "axios"
import { getProductRenderDropshippingComponent } from "@/Composables/getIrisComponents"

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
    code:string
    screenType: "mobile" | "tablet" | "desktop"
}>(), {})

const layout = inject("layout", {})
const product = ref(props.fieldValue.product)
const isLoadingFetchExistenceChannels = ref(false)
const productExistenceInChannels = ref<number[]>([])

const fetchProductExistInChannel = async () => {
    if(layout.iris?.customer?.id){
        try {
            isLoadingFetchExistenceChannels.value = true
            const response = await axios.get(
                route(
                    "iris.json.customer.product.channel_ids.index",
                    {
                        customer: layout.iris?.customer?.id,
                        product: product.value.id
                    }
                )
            )

            if (response.status !== 200) {
                throw new Error("Failed to fetch product existence in channel")
            }

            // console.log('Product exist in channel response:', response.data)
            productExistenceInChannels.value = response.data || []
        } catch (error: any) {
            console.error('Error fetching product existence in channel:', error.message)
        } finally {
            isLoadingFetchExistenceChannels.value = false
        }
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

onMounted(() => {
    if (layout.iris?.customer && layout?.iris?.is_logged_in) {
        fetchProductExistInChannel()
        fetchData() // break chaced
    }
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
})

watch(
  () => props.fieldValue.product,
  newVal => {
    product.value = { ...newVal }
  },
  { deep: true }
)

watch(
  () => layout.iris.customer,
  newVal => {
    fetchProductExistInChannel()
    fetchData()
  },
  { deep: true }
)

</script>

<template>
        <component 
            :is="getProductRenderDropshippingComponent(code)" 
            :fieldValue 
            :webpageData 
            :blockData 
            :validImages
            :videoSetup
            :product
            :isLoadingFetchExistenceChannels
            :productExistenceInChannels
        />
</template>