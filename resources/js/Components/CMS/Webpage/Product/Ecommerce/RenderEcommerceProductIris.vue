<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faFilePdf, faFileDownload } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, inject, onMounted, onBeforeUnmount, computed, watch, type Ref } from "vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import axios from "axios"

import { Image as ImageTS } from "@/types/Image"
import ProductIris1Ecom from "@/Components/CMS/Webpage/Product1/Ecommerce/ProductIris1Ecom.vue"
import ProductIris2Ecom from "@/Components/CMS/Webpage/Product2/ProductIris2Ecom.vue"
import { resolveProductImages, resolveProductVideo } from "@/Composables/useProductPage"
import { ProductViewCollector } from "@/Composables/Unique/LuigiDataCollector"
import { useProductStructuredData } from "@/Iris/Composables/useProductStructuredData"

library.add(faCube, faLink, faFilePdf, faFileDownload)

const productPageComponents: Record<string, any> = {
    "product-1": ProductIris1Ecom,
    "product-2": ProductIris2Ecom,
}


interface ProductResource {
  id: number
  name: string
  code: string
  slug: string
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

interface CustomerFlagRequest {
  product: ProductResource
  routeName: string
  method: "post" | "delete"
  customerDataPatch: Record<string, boolean>
  loadingState: Ref<boolean>
  preserveState?: boolean
}


const props = withDefaults(
  defineProps<{
    fieldValue: any
    webpageData?: any
    blockData?: object
    code: string
    screenType: "mobile" | "tablet" | "desktop"
  }>(),
  {}
)


const layout = inject("layout", {})
const injectedWebpageData = inject<any>("webpage_data", null)

const variant = ref<any>(props.fieldValue?.variant ?? null)
const selected_product = ref<ProductResource>(props.fieldValue.product)
const appliedVariantFromUrl = ref(false)

const variantProductsData = ref<ProductResource[]>([])

const customerData = ref<Record<number, any>>({})
const pendingOrderingRequests = new Set<number>()
const isLoadingFavourite = ref(false)
const isLoadingRemindBackInStock = ref(false)


const variantProducts = computed<any[]>(() =>
  Object.values(variant.value?.data?.products ?? {})
)

const variants = computed<any[]>(() =>
  variant.value?.data?.variants ?? []
)

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
      const baseProduct = variantProductsData.value.find(
        p => p.id === v.product.id
      )

      if (!baseProduct) return null

      return {
        ...baseProduct,
        is_leader: v.is_leader,
        variant_label: getVariantLabel(index),
      }
    })
    .filter(Boolean)
    .sort((a, b) => {
      if (!a.variant_label) return 1
      if (!b.variant_label) return -1

      return a.variant_label.localeCompare(b.variant_label, undefined, {
        numeric: true,
        sensitivity: "base",
      })
    })
})


const fetchVariantProducts = async () => {
  if (!variant.value?.id) return

  try {
    const response = await axios.get(
      route("iris.json.products.variant", {
        variant: variant.value.id,
      })
    )
    variantProductsData.value = response.data.products ?? []
  } catch (error) {
    console.error("Failed to load variant products", error)
  }
}

const fetchOrderingData = async (productId: number) => {
  if (!layout?.iris?.is_logged_in) return
  if (!productId || pendingOrderingRequests.has(productId)) return

  pendingOrderingRequests.add(productId)

  try {
    const response = await axios.get(
      route("iris.json.product.ecom_ordering_data", {
        product: productId,
      })
    )

    customerData.value = {
      ...customerData.value,
      [productId]: response.data,
    }
  } catch (error) {
    notify({
      title: trans("Something went wrong"),
      text: trans("Failed to load product ordering data"),
      type: "error",
    })
    console.error(error)
  } finally {
    pendingOrderingRequests.delete(productId)
  }
}

const fetchAllOrderingData = async () => {
  if (!layout?.iris?.is_logged_in) return

  const productIds = new Set(
    [selected_product.value?.id, ...listProducts.value.map(p => p.id)].filter(Boolean)
  )

  await Promise.all([...productIds].map(id => fetchOrderingData(id)))
}

const fetchUncachedProduct = async (product: ProductResource = selected_product.value) => {
  if (!product?.slug) return

  try {
    const response = await axios.get(
      route("iris.catalogue.product.resource", {
        product: product.slug,
      })
    )

    if (selected_product.value?.slug !== product.slug) return

    selected_product.value = { ...selected_product.value, ...response.data }
  } catch (error) {
    console.error("Failed to load uncached product data", error)
  }
}


const patchCustomerData = (productId: number, patch: Record<string, boolean>) => {
  customerData.value = {
    ...customerData.value,
    [productId]: {
      ...customerData.value[productId],
      ...patch,
    },
  }
}

const submitCustomerFlag = ({
  product,
  routeName,
  method,
  customerDataPatch,
  loadingState,
  preserveState,
}: CustomerFlagRequest) => {
  const url = route(routeName, { product: product.id })
  const options = {
    ...(preserveState ? { preserveState } : {}),
    onSuccess: () => {
      patchCustomerData(product.id, customerDataPatch)
      layout.reload_handle()
    },
    onStart: () => { loadingState.value = true },
    onFinish: () => { loadingState.value = false },
  }

  if (method === "post") {
    router.post(url, {}, options)
  } else {
    router.delete(url, options)
  }
}

const toggleFavourite = (product: ProductResource, isFavourite: boolean) => {
  submitCustomerFlag({
    product,
    routeName: isFavourite
      ? "iris.models.favourites.store"
      : "iris.models.favourites.delete",
    method: isFavourite ? "post" : "delete",
    customerDataPatch: { is_favourite: isFavourite },
    loadingState: isLoadingFavourite,
    preserveState: true,
  })
}

const toggleBackInStockReminder = (product: ProductResource, isReminderWanted: boolean) => {
  submitCustomerFlag({
    product,
    routeName: isReminderWanted
      ? "iris.models.remind_back_in_stock.store"
      : "iris.models.remind_back_in_stock.delete",
    method: isReminderWanted ? "post" : "delete",
    customerDataPatch: { back_in_stock: isReminderWanted },
    loadingState: isLoadingRemindBackInStock,
  })
}

const changeSelectedProduct = (product: ProductResource) => {
  selected_product.value = { ...product }
  fetchOrderingData(product.id)

  const url = new URL(window.location.href)
  url.searchParams.set("variant", product.code)
  window.history.replaceState({}, "", url.toString())
}

const applyVariantFromUrl = () => {
  if (appliedVariantFromUrl.value) return

  const variantCode = new URLSearchParams(window.location.search).get("variant")
  if (!variantCode) return

  const matchedProduct = listProducts.value.find(p => p.code === variantCode)
  if (!matchedProduct) return

  selected_product.value = { ...matchedProduct }
  fetchOrderingData(matchedProduct.id)
  appliedVariantFromUrl.value = true
}


watch(
  () => listProducts.value,
  products => {
    if (!products.length) return

    applyVariantFromUrl()
    fetchAllOrderingData()
  }
)

watch(
  () => props.fieldValue.product,
  product => {
    selected_product.value = { ...product }
    fetchOrderingData(product.id)
  },
  { deep: true }
)

watch(
  () => selected_product.value?.slug,
  (slug, previousSlug) => {
    if (!slug || slug === previousSlug) return

    fetchUncachedProduct(selected_product.value)
  }
)

watch(
  () => layout?.iris?.is_logged_in,
  isLoggedIn => {
    if (!isLoggedIn) return

    fetchAllOrderingData()
    fetchUncachedProduct()
  },
  { immediate: true }
)

// Section: Product structured data (SEO)
// Mounted independently here instead of inside the page structured data (useStructuredData),
// so the product schema lives in its own <script> and is easier to maintain.
const { mountProductStructuredData, removeStructuredDataScript } = useProductStructuredData()
const productStructuredDataScript = ref<HTMLScriptElement | null>(null)

onMounted(() => {
  if (props.fieldValue?.product?.luigi_identity) {
    ProductViewCollector(props.fieldValue.product.luigi_identity)
  }

  productStructuredDataScript.value = mountProductStructuredData({
    product: props.fieldValue?.product,
    variant: props.fieldValue?.variant,
    webpageData: props.webpageData ?? injectedWebpageData,
    currencyCode: layout?.iris?.currency?.code,
    websiteName: layout?.iris?.website?.name,
  })

  fetchVariantProducts()
})

onBeforeUnmount(() => {
  removeStructuredDataScript(productStructuredDataScript.value)
})

</script>

<template>
    <component
        :key="selected_product.code"
        :is="productPageComponents[code]"
        :fieldValue
        :webpageData
        :blockData
        :isLoadingFavourite
        :isLoadingRemindBackInStock
        :product="selected_product"
        :customerData="customerData[selected_product.id]"
        :screenType
        :listProducts="listProducts"
        :validImages="resolveProductImages(selected_product)"
        :videoSetup="resolveProductVideo(selected_product)"
        @setFavorite="product => toggleFavourite(product, true)"
        @unsetFavorite="product => toggleFavourite(product, false)"
        @setBackInStock="product => toggleBackInStockReminder(product, true)"
        @unsetBackInStock="product => toggleBackInStockReminder(product, false)"
        @selectProduct="changeSelectedProduct"
    />
</template>
