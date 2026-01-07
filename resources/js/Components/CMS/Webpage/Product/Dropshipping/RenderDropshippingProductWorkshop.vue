<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faFileDownload } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, computed, onMounted } from "vue"
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { isArray } from "lodash-es"
import { getProductRenderDropshippingComponentWorkshop } from "@/Composables/getWorkshopComponents"
import { resolveProductImages, resolveProductVideo } from "@/Composables/useProductPage"
import axios from "axios"

library.add(faCube, faLink, faFileDownload)

type TemplateType = 'webpage' | 'template'

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

const props = withDefaults(defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: object
    templateEdit?: TemplateType
    indexBlock?: number
    screenType: "mobile" | "tablet" | "desktop"
    code: string
    currency: {
        code: string
        name: string
    }
}>(), {
    templateEdit: 'webpage'
})

const cancelToken = ref<Function | null>(null)
const debounceTimer = ref(null)
const product = ref<any>(props.modelValue?.product ?? null)
const variant = ref<any>(props.modelValue?.variant ?? null)
const productsList = ref<ProductResource[]>([])

const onDescriptionUpdate = (key: string, val: string) => {
    clearTimeout(debounceTimer.value)
    debounceTimer.value = setTimeout(() => {
        saveDescriptions(key, val)
    }, 5000)
}

const saveDescriptions = (key: string, val: string) => {
    if (cancelToken.value) cancelToken.value()
    router.patch(
        route("grp.models.product.update", { product: props.modelValue.product.id }),
        { [key]: val },
        {
            preserveScroll: false,
            onCancelToken: (token) => {
                cancelToken.value = token.cancel
            },
            onFinish: () => {
                cancelToken.value = null
            },
            onSuccess: () => { },
            onError: (error) => {
                notify({
                    title: trans('Something went wrong'),
                    text: error.message,
                    type: 'error',
                })
            }
        }
    )
}


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
}


onMounted(() => {
  getAllProductFromVariant()
})


</script>

<template>
    <component 
        :is="getProductRenderDropshippingComponentWorkshop(code)" 
        :modelValue 
        :webpageData 
        :blockData
        :templateEdit 
        :indexBlock 
        :screenType 
        :code 
        :currency 
        :product="product"
        :listProducts="listProducts"
        :validImages="resolveProductImages(product)"
        :videoSetup="resolveProductVideo(product)"
        @onDescriptionUpdate="onDescriptionUpdate"
        @selectProduct="changeSelectedProduct"
    />
</template>
