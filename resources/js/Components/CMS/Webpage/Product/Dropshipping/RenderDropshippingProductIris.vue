<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faFilePdf, faFileDownload } from "@fas"
import { faGameConsoleHandheld } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, inject, onMounted, computed, watch } from "vue"
import axios from "axios"

import { Image as ImageTS } from "@/types/Image"
import { getProductRenderDropshippingComponent } from "@/Composables/getIrisComponents"
import { resolveProductImages, resolveProductVideo } from "@/Composables/useProductPage"
import { set } from "lodash-es"

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
console.log(layout)

const customerData = ref<Record<number, any>>({})
const product = ref<any>(props.fieldValue?.product ?? null)
const variant = ref<any>(props.fieldValue?.variant ?? null)
const appliedVariantFromUrl = ref(false)
const isLoadingRemindBackInStock = ref(false)

const productsList = ref<ProductResource[]>([])


const productExistenceInChannels = ref<Record<number, number[]>>({})
const isLoadingProductChannel = ref<Record<number, boolean>>({})



const fetchProductExistInChannel = async (productId: number) => {
  if (!layout?.iris?.is_logged_in) return
  if (isLoadingProductChannel.value[productId]) return

  isLoadingProductChannel.value = {
    ...isLoadingProductChannel.value,
    [productId]: true,
  }

  try {
    const response = await axios.get(
      route("iris.json.customer.product.channel_ids.index", {
        customer: layout.iris_variables.customer_id,
        product: productId,
      })
    )

    productExistenceInChannels.value = {
      ...productExistenceInChannels.value,
      [productId]: response.data ?? [],
    }
  } catch (e) {
    console.error("fetchProductExistInChannel error", e)
  } finally {
    isLoadingProductChannel.value = {
      ...isLoadingProductChannel.value,
      [productId]: false,
    }
  }
}

const fetchAllProductExistenceChannels = async () => {
  if (!layout?.iris?.is_logged_in) return

  const ids = [
    product.value?.id,
    ...listProducts.value.map(p => p.id),
  ].filter(Boolean)

  const uniqueIds = [...new Set(ids)]
  await Promise.all(uniqueIds.map(id => fetchProductExistInChannel(id)))
}


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



const variantProducts = computed<VariantProduct[]>(() =>
  Object.values(variant.value?.data?.products ?? {})
)

const variants = computed(() => variant.value?.data?.variants ?? [])

const getVariantLabel = (index: number) => {
  const entry = variantProducts.value[index]
  if (!entry) return null

  return variants.value
    .map(v => entry[v.label])
    .filter(Boolean)
    .join(" – ")
}

const listProducts = computed(() =>
  variantProducts.value
    .map((v, index) => {
      const baseProduct = productsList.value.find(
        p => p.id === v.product.id
      )
      if (!baseProduct) return null

      return {
        ...baseProduct,
        is_leader: v.is_leader,
        variant_label: getVariantLabel(index),
        validImages: resolveProductImages(baseProduct),
      }
    })
    .filter(Boolean)
)

const changeSelectedProduct = (item: ProductResource) => {
  product.value = { ...item }
  fetchProductExistInChannel(item.id)
  const url = new URL(window.location.href)
  url.searchParams.set('variant', item.code)
  window.history.replaceState({}, '', url.toString())
}


const onAddBackInStock = async (product: ProductResource) => {
  try {
    isLoadingRemindBackInStock.value = true

    await axios.post(
      route('iris.models.remind_back_in_stock.store', {
        product: product.id,
      })
    )

    set(product, ['back_in_stock'], true)
    layout.reload_handle()
  } catch (error) {
    console.error('Failed to set back in stock reminder', error)
  } finally {
    isLoadingRemindBackInStock.value = false
  }
}

const onUnselectBackInStock = async (product: ProductResource) => {
  try {
    isLoadingRemindBackInStock.value = true

    await axios.delete(
      route('iris.models.remind_back_in_stock.delete', {
        product: product.id,
      })
    )

    set(product, ['back_in_stock'], false)
    layout.reload_handle()
  } catch (error) {
    console.error('Failed to unset back in stock reminder', error)
  } finally {
    isLoadingRemindBackInStock.value = false
  }
}




watch(
  () => layout?.iris?.is_logged_in,
  loggedIn => {
    if (loggedIn) {
      fetchAllProductExistenceChannels()
      fetchData()
    }
  },
  { immediate: true }
)

watch(
  () => listProducts.value,
  products => {
    if (products.length) {
      fetchAllProductExistenceChannels()
    }
  }
)

watch(
  () => props.fieldValue.product,
  val => {
    product.value = { ...val }
    fetchProductExistInChannel(val.id)
  },
  { deep: true }
)


watch(
  () => listProducts.value,
  (products) => {
    if (!products.length || appliedVariantFromUrl.value) return

    const urlParams = new URLSearchParams(window.location.search)
    const variantCode = urlParams.get('variant')
    if (!variantCode) return

    const matchedProduct = listProducts.value.find(
      p => p.code === variantCode
    )

    if (matchedProduct) {
      product.value = { ...matchedProduct }
      fetchProductExistInChannel(product.value.id)
      appliedVariantFromUrl.value = true
    }
  },
  { immediate: true }
)




onMounted(() => {
  if (props.fieldValue?.product?.luigi_identity) {
    window?.dataLayer?.push({
      event: "view_item",
      ecommerce: {
        items: [{ item_id: props.fieldValue.product.luigi_identity }],
      },
    })
  }

  if (layout?.iris?.is_logged_in) {
      fetchData()
    }

  getAllProductFromVariant()
})


</script>

<template>
  <component
    :key="product.code"
    :is="getProductRenderDropshippingComponent(code)"
    :fieldValue="fieldValue"
    :webpageData="webpageData"
    :blockData="blockData"
    :product="product"
    :isLoadingRemindBackInStock
    :productExistenceInChannels="productExistenceInChannels[product.id]"
    :listProducts="listProducts"
    :validImages="resolveProductImages(product)"
    :videoSetup="resolveProductVideo(product)"
    @selectProduct="changeSelectedProduct"
    @setBackInStock="onAddBackInStock"
    @unsetBackInStock="onUnselectBackInStock"

  />
</template>
