<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faFilePdf, faFileDownload } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, inject, onMounted, computed, watch } from "vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import { ulid } from "ulid"
import { usePage } from '@inertiajs/vue3'

import { Image as ImageTS } from "@/types/Image"
import { getProductRenderB2bComponent } from "@/Composables/getIrisComponents"
import { resolveProductImages, resolveProductVideo } from "@/Composables/useProductPage"

library.add(faCube, faLink, faFilePdf, faFileDownload)


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
const page = usePage()

const variant = ref<any>(props.fieldValue?.variant ?? null)
const selected_product = ref<ProductResource>(props.fieldValue.product)
const appliedVariantFromUrl = ref(false)

const productsList = ref<ProductResource[]>([])


const customerData = ref<Record<number, any>>({})
const isLoadingOrdering = ref<Record<number, boolean>>({})
const isLoadingFavourite = ref(false)
const isLoadingRemindBackInStock = ref(false)

const keyCustomer = ref(ulid())


const getOrderingProduct = async (productId: number) => {
  if (!layout?.iris?.is_logged_in) return
  if (isLoadingOrdering.value[productId]) return

  isLoadingOrdering.value = {
    ...isLoadingOrdering.value,
    [productId]: true,
  }

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

    keyCustomer.value = ulid()
  } catch (error) {
    notify({
      title: trans("Something went wrong"),
      text: trans("Failed to load product ordering data"),
      type: "error",
    })
    console.error(error)
  } finally {
    isLoadingOrdering.value = {
      ...isLoadingOrdering.value,
      [productId]: false,
    }
  }
}

const getAllOrderingProducts = async () => {
  if (!layout?.iris?.is_logged_in) return

  const ids = [
    selected_product.value?.id,
    ...listProducts.value.map(p => p.id),
  ].filter(Boolean)

  const uniqueIds = [...new Set(ids)]
  await Promise.all(uniqueIds.map(id => getOrderingProduct(id)))
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
      const baseProduct = productsList.value.find(
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



const onAddFavourite = (product: ProductResource) => {
  router.post(
    route("iris.models.favourites.store", { product: product.id }),
    {},
    {
      preserveState: true,
      onSuccess: () => {
        customerData.value = {
          ...customerData.value,
          [product.id]: {
            ...customerData.value[product.id],
            is_favourite: true,
          },
        }
        layout.reload_handle()
      },
      onStart : () => { isLoadingFavourite.value = true }, 
      onFinish: () => { isLoadingFavourite.value = false },
    }
  )
}

const onUnselectFavourite = (product: ProductResource) => {
  router.delete(
    route("iris.models.favourites.delete", { product: product.id }),
    {
      preserveState: true,
      onSuccess: () => {
        customerData.value = {
          ...customerData.value,
          [product.id]: {
            ...customerData.value[product.id],
            is_favourite: false,
          },
        }
        layout.reload_handle()
      },
      onStart : () => { isLoadingFavourite.value = true }, 
      onFinish: () => { isLoadingFavourite.value = false },
    }
  )
}

const onAddBackInStock = (product: ProductResource) => {
  router.post(
    route("iris.models.remind_back_in_stock.store", { product: product.id }),
    {},
    {
      onSuccess: () => {
        customerData.value = {
          ...customerData.value,
          [product.id]: {
            ...customerData.value[product.id],
            back_in_stock: true,
          },
        }
      	layout.reload_handle()
      },
      onStart : () => { isLoadingRemindBackInStock.value = true }, 
      onFinish: () => { isLoadingRemindBackInStock.value = false },
    }
  )
}

const onUnselectBackInStock = (product: ProductResource) => {
  router.delete(
    route("iris.models.remind_back_in_stock.delete", { product: product.id }),
    {
      onSuccess: () => {
        customerData.value = {
          ...customerData.value,
          [product.id]: {
            ...customerData.value[product.id],
            back_in_stock: false,
          },
        }
        layout.reload_handle()
      },
      onStart : () => { isLoadingRemindBackInStock.value = true }, 
      onFinish: () => { isLoadingRemindBackInStock.value = false },
    }
  )
}

const changeSelectedProduct = (product: ProductResource) => {
  selected_product.value = { ...product }
  getOrderingProduct(product.id)
  const url = new URL(window.location.href)
  url.searchParams.set('variant', product.code)
  window.history.replaceState({}, '', url.toString())
}

// Method: to get product data without cache
const fetchData = async () => {
  try {
    const response = await axios.get(
      route("iris.catalogue.product.resource", {
        product: selected_product.value.slug
      })
    )
    selected_product.value = {...selected_product.value, ...response.data}
  } catch (error: any) {
    console.error("cannot break cached cuz", error)
  }
}


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
      selected_product.value = { ...matchedProduct }
      getOrderingProduct(selected_product.value.id)
      appliedVariantFromUrl.value = true
    }
  },
  { immediate: true }
)

watch(
  () => layout?.iris?.is_logged_in,
  loggedIn => {
    if (loggedIn) {
      getAllOrderingProducts()
    }
  },
  { immediate: true }
)

watch(
  () => listProducts.value,
  products => {
    if (products.length) {
      getAllOrderingProducts()
    }
  }
)

watch(
  () => props.fieldValue.product,
  newVal => {
    selected_product.value = { ...newVal }
    getOrderingProduct(newVal.id)
  },
  { deep: true }
)

onMounted(() => {
  if (props.fieldValue?.product?.luigi_identity) {
    window?.dataLayer?.push({
      event: 'view_item',
      ecommerce: {
        items: [{ item_id: props.fieldValue.product.luigi_identity }],
      },
    })
  }

  getAllProductFromVariant()
})

watch(
  () => layout?.iris?.is_logged_in,
  (isLoggedIn) => {
    if (isLoggedIn) {
      fetchData()
    }
  },
  { immediate: true }
)


</script>

<template>
    <component
        :key="selected_product.code"
        :is="getProductRenderB2bComponent(code)"
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
        @setFavorite="onAddFavourite"
        @unsetFavorite="onUnselectFavourite"
        @setBackInStock="onAddBackInStock"
        @unsetBackInStock="onUnselectBackInStock"
        @selectProduct="changeSelectedProduct"
    />
</template>