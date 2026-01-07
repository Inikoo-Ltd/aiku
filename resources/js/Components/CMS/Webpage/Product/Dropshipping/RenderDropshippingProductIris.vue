<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faFilePdf, faFileDownload } from "@fas"
import { faGameConsoleHandheld } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, inject, onMounted, computed, watch } from "vue"
import { isArray } from "lodash-es"
import axios from "axios"

import { Image as ImageTS } from "@/types/Image"
import { getProductRenderDropshippingComponent } from "@/Composables/getIrisComponents"

library.add(
  faCube,
  faLink,
  faFilePdf,
  faFileDownload,
  faGameConsoleHandheld
)


interface ProductResource {
  id: number
  name: string
  code: string
  image?: { source: ImageTS }
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

interface VariantAttribute {
  code: string
  label: string
}

interface VariantProduct {
  product: { id: number }
  is_leader: boolean
  variant?: {
    attributes?: VariantAttribute[]
  }
}



const props = defineProps<{
  fieldValue: any
  webpageData?: any
  blockData?: object
  code: string
  screenType: "mobile" | "tablet" | "desktop"
}>()



const layout: any = inject("layout", {})

const product = ref<any>(props.fieldValue?.product ?? null)
const variant = ref<any>(props.fieldValue?.variant ?? null)

const isLoadingFetchExistenceChannels = ref(false)
const productExistenceInChannels = ref<number[]>([])
const productsList = ref<ProductResource[]>([])



const fetchProductExistInChannel = async () => {
  if (!layout?.iris_variables?.id || !product.value?.id) return

  try {
    isLoadingFetchExistenceChannels.value = true

    const response = await axios.get(
      route("iris.json.customer.product.channel_ids.index", {
        customer: layout.iris_variables.id,
        product: product.value.id,
      })
    )

    productExistenceInChannels.value = response.data ?? []
  } catch (e) {
    console.error("fetchProductExistInChannel error", e)
  } finally {
    isLoadingFetchExistenceChannels.value = false
  }
}

const resolveProductImages = (product: any) => {
  const images = product?.images
  if (!images || !isArray(images)) return []

  return images
    .filter(i => i?.type === "image" && i?.images)
    .flatMap(i =>
      (Array.isArray(i.images) ? i.images : [i.images]).map(
        (img: ImageTS) => ({
          source: img,
          thumbnail: img,
        })
      )
    )
}

const videoSetup = computed(() =>
  product.value?.images?.find((i: any) => i.type === "video") ?? null
)


const fetchData = async () => {
  if (!product.value?.slug) return

  try {
    const response = await axios.get(
      route("iris.catalogue.product.resource", {
        product: product.value.slug,
      })
    )

    product.value = { ...product.value, ...response.data }
  } catch (e) {
    console.error("fetchData error", e)
  }
}



const getAllProductFromVariant = async () => {
  if (!variant.value?.id) return

  try {
    const response = await axios.get(
      route("iris.json.products.variant", {
        variant: variant.value.id,
      })
    )
    productsList.value = response.data.products ?? []
  } catch (e) {
    console.error("getAllProductFromVariant error", e)
  }
}


const variantProducts = computed<VariantProduct[]>(() => {
  return Object.values(variant.value?.data?.products ?? {})
})

const variants = computed(() => {
  return variant.value?.data?.variants ?? []
})

const getVariantLabel = (index: number) => {
  const entry = variantProducts.value[index]
  if (!entry) return null


  return variants.value
    .map(v => entry[v.label])
    .filter(Boolean)
    .join(" – ")
}

const listProducts = computed(() => {
  return variantProducts.value
    .map((v, index) => {
      const baseProduct = productsList.value.find(
        p => p.id === v.product.id
      )

      if (!baseProduct) return null

      return {
        ...baseProduct,
        is_leader: v.is_leader,
        variant_label: getVariantLabel(index),
        validImages : resolveProductImages(baseProduct),
      }
    })
    .filter(Boolean)
})


onMounted(() => {
  if (layout?.iris?.is_logged_in) {
    fetchProductExistInChannel()
    fetchData()
  }

  if (props.fieldValue?.product?.luigi_identity) {
    window?.dataLayer?.push({
      event: "view_item",
      ecommerce: {
        items: [{ item_id: props.fieldValue.product.luigi_identity }],
      },
    })
  }

  getAllProductFromVariant()
})


watch(
  () => props.fieldValue.product,
  val => {
    product.value = val ? { ...val } : null
  },
  { deep: true }
)

watch(
  () => layout?.iris?.customer,
  () => {
    fetchProductExistInChannel()
    fetchData()
  }
)
</script>

<template>
  <component
    :is="getProductRenderDropshippingComponent(code)"
    :fieldValue="fieldValue"
    :webpageData="webpageData"
    :blockData="blockData"
    :validImages="resolveProductImages(product)"
    :videoSetup="videoSetup"
    :product="product"
    :isLoadingFetchExistenceChannels="isLoadingFetchExistenceChannels"
    :productExistenceInChannels="productExistenceInChannels"
    :listproducts="listProducts"
  />
</template>
