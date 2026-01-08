<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faFilePdf, faFileDownload } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Image as ImageTS } from "@/types/Image"
import { getProductRenderB2bComponent } from "@/Composables/getWorkshopComponents"
import { ref, inject, onMounted, computed, watch, onUnmounted } from "vue"
import { set } from "lodash-es"
import { resolveProductImages, resolveProductVideo } from "@/Composables/useProductPage"
import axios from "axios"

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
    modelValue: any
    webpageData?: any
    blockData?: object
    code: string
    screenType: "mobile" | "tablet" | "desktop"
}>(), {})

const layout = inject("layout", {})
const product = ref(props.modelValue.product)
const isLoadingRemindBackInStock = ref(false)
const customerData = ref(null)
const isLoadingFavourite = ref(false)
const variant = ref<any>(props.modelValue?.variant ?? null)
const productsList = ref<ProductResource[]>([])


const onAddFavourite = (product: ProductResource) => {}

const onUnselectFavourite = (product: ProductResource) => {}

const onAddBackInStock = (productData: ProductResource) => {}

const onUnselectBackInStock = (productData: ProductResource) => {}

const getOrderingProduct = async () => {}

const getAllProductFromVariant = async () => {
  if (!variant.value?.id) return

  try {
    const response = await axios.get(
      route("grp.json.variant.products", {
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

const changeSelectedProduct = (item: ProductResource) => {
  product.value = { ...item }
  getOrderingProduct(item.id)
}




watch(() => layout?.iris?.is_logged_in, (newVal) => {
    if (newVal) {
        getOrderingProduct()
    }
}, {
    immediate: true
})

watch(
  () => props.modelValue.product,
  newVal => {
    product.value = { ...newVal }
  },
  { deep: true }
)

onMounted(() => {
    set(layout, "temp.fetchIrisProductCustomerData", getOrderingProduct)
    if (props.modelValue?.product?.luigi_identity) {
        window?.dataLayer?.push({
            event: "view_item",
            ecommerce: {
                items: [
                    {
                        item_id: props.modelValue?.product?.luigi_identity
                    }
                ]
            }
        })
    }
    getAllProductFromVariant()
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
        :modelValue 
        :webpageData 
        :blockData 
        :isLoadingFavourite
        :isLoadingRemindBackInStock
        :product
        :customerData
        :validImages="resolveProductImages(product)"
        :videoSetup="resolveProductVideo(product)"
        :screenType
        :listProducts="listProducts"
        @setFavorite="onAddFavourite"
        @unsetFavorite="onUnselectFavourite"
        @setBackInStock="onAddBackInStock"
        @unsetBackInStock="onUnselectBackInStock"
        @selectProduct="changeSelectedProduct"
    />
</template>