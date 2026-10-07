<script setup lang="ts">
import { useLocaleStore } from "@/Stores/locale"
import { inject, ref, computed, watch, defineAsyncComponent } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { hasSavedVariantOrder, sortVariantOptions } from '@/Composables/useVariantOrder'
import { router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { ctrans } from '@/Composables/useTrans'
import Dialog from 'primevue/dialog'

import { faQuestionCircle } from "@fal"
import { faStarHalfAlt } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ProductResource } from '@/types/Iris/Products'
import { routeType } from '@/types/route'
import ProductCardEcom3 from "@/Iris/Components/IrisBlocks/Products/Ecom/ProductCard/ProductCardEcom3.vue"
import ProductCardEcom2 from "@/Iris/Components/IrisBlocks/Products/Ecom/ProductCard/ProductCardEcom2.vue"

const productCardComponents: Record<string, any> = {
    "products-1": ProductCardEcom3,
    "products-2": ProductCardEcom2,
}
import axios from "axios"
const VariantDialogContent = defineAsyncComponent(() => import("@/Iris/Components/IrisBlocks/Products/Ecom/VariantDialogContent.vue"))
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(faStarHalfAlt, faQuestionCircle)

const layout = inject('layout', retinaLayoutStructure)

const locale = useLocaleStore()


const props = withDefaults(defineProps<{
    product: ProductResource
    hasInBasketList?: any
    basketButton?: boolean
    attachToFavouriteRoute?: routeType
    detachToFavouriteRoute?: routeType
    attachBackInStockRoute?: routeType
    detachBackInStockRoute?: routeType
    addToBasketRoute?: routeType
    updateBasketQuantityRoute?: routeType
    bestSeller?: any
    buttonStyleHover?: any
    buttonStyle?: object | undefined
    buttonStyleLogin?: object | undefined
    code : string
    button?: any
    screenType:string
    hideLogin?: boolean
}>(), {
    basketButton: true,
    hasInBasketList: () => ({}),
    addToBasketRoute: {
        name: 'iris.models.transaction.store',
    },
    updateBasketQuantityRoute: {
        name: 'iris.models.transaction.update',
    },
    attachToFavouriteRoute: {
        name: 'iris.models.favourites.store',
    },
    detachToFavouriteRoute: {
        name: 'iris.models.favourites.delete',
    },
    attachBackInStockRoute: {
        name: 'iris.models.remind_back_in_stock.store',
    },
    detachBackInStockRoute: {
        name: 'iris.models.remind_back_in_stock.delete',
    },
})

const emits = defineEmits<{
    (e: 'afterOnAddFavourite', value: any[]): void
    (e: 'afterOnUnselectFavourite', value: any[]): void
    (e: 'afterOnAddBackInStock', value: any[]): void
    (e: 'afterOnUnselectBackInStock', value: any[]): void
}>()


const isLoadingRemindBackInStock = ref(false)
const variant = ref<any>(null)
const selectedVariantProduct = ref<any>(null)
const _render_components = ref(null)
const isVariantDialogOpen = ref(false)
const isLoadingFavourite = ref(false)
const loadingGetVariants = ref(false)

watch(() => props.product, () => {
    selectedVariantProduct.value = null
    variant.value = null
})

const displayedProduct = computed<ProductResource>(() => {
    if (!selectedVariantProduct.value) return props.product

    return { ...props.product, ...selectedVariantProduct.value }
})

const onSelectVariant = (product: ProductResource) => {
    selectedVariantProduct.value = product
}

const onAddFavourite = (product: ProductResource) => {

    // Section: Submit
    router.post(
        route(props.attachToFavouriteRoute.name, {
            product: product.id
        }),
        {
            // item_id: [product.id]
        },
        {
            preserveScroll: true,
            only: ['iris'],
            preserveState: true,
            onStart: () => {
                isLoadingFavourite.value = true
            },
            onSuccess: () => {
                product.is_favourite = true
                layout.reload_handle()
            },
            onError: errors => {
                console.error(errors)
                notify({
                    title: ctrans("Something went wrong"),
                    text: ctrans("Failed to add the product to favourites"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingFavourite.value = false
                emits('afterOnAddFavourite', product)
            },
        }
    )
}

const onUnselectFavourite = (product: ProductResource) => {
    router.delete(
        route(props.detachToFavouriteRoute.name, {
            product: product.id
        }),
        {
            preserveScroll: true,
            preserveState: true,
            only: ['iris'],
            onStart: () => {
                isLoadingFavourite.value = true
            },
            onSuccess: () => {
                // notify({
                //     title: ctrans("Success"),
                //     text: ctrans("Added to portfolio"),
                //     type: "success"
                // })
                layout.reload_handle()
                product.is_favourite = false
            },
            onError: errors => {
                notify({
                    title: ctrans("Something went wrong"),
                    text: ctrans("Failed to remove the product from favourites"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingFavourite.value = false
                emits('afterOnUnselectFavourite', product)
            },
        }
    )
}

const onAddBackInStock = async (product: ProductResource) => {
	isLoadingRemindBackInStock.value = true

	try {
		await axios.post(
			route(props.attachBackInStockRoute.name, {
				product: product.id
			}),
			{
				// item_id: [product.id]
			}
		)

		product.is_back_in_stock = true
		layout.reload_handle()

		emits("afterOnAddBackInStock", product)
	} catch (error) {
		notify({
			title: ctrans("Something went wrong"),
			text: ctrans("Failed to add the product to remind back in stock"),
			type: "error"
		})
	} finally {
		isLoadingRemindBackInStock.value = false
	}
}

const onUnselectBackInStock = async (product: ProductResource) => {
	isLoadingRemindBackInStock.value = true

	try {
		await axios.delete(
			route(props.detachBackInStockRoute.name, {
				product: product.id
			})
		)

		product.is_back_in_stock = false
		layout.reload_handle()

		emits("afterOnUnselectBackInStock", product)
	} catch (error) {
		notify({
			title: ctrans("Something went wrong"),
			text: ctrans("Failed to remove the product from remind back in stock"),
			type: "error"
		})
	} finally {
		isLoadingRemindBackInStock.value = false
	}
}

const getAllProductFromVariant = async (variant_id: string) => {
  if (!variant_id) return

  isVariantDialogOpen.value = true
  loadingGetVariants.value = true

  try {
    const response = await axios.get(
      route('iris.json.variant', { variant: variant_id })
    )
    variant.value = response.data
  } catch (e) {
    console.error(e)
    isVariantDialogOpen.value = false
    notify({
      title: ctrans("Something went wrong"),
      text: ctrans("Failed to load the product variants"),
      type: "error"
    })
  } finally {
    loadingGetVariants.value = false
  }
}

const variantAxisLabel = computed<string>(() =>
  (variant.value?.variant_data?.variants || [])
    .map((v: any) => v?.label)
    .filter(Boolean)
    .join(" / ")
)

const getVariantLabel = (entry: number) => {
  if (!entry) return null

  return variant.value.variant_data.variants
    .map(v => entry[v.label])
    .filter(Boolean)
    .join(" – ")
}

const listProducts = computed(() => {
  if (!variant.value?.variant_data?.products) return []

  return sortVariantOptions(
    Object.values(variant.value.variant_data.products)
      .map((v: any) => {
        const baseProduct = variant.value.products.find(
          p => p.id === v.product.id
        )

        if (!baseProduct) return null

        return {
          ...baseProduct,
          is_leader: v.is_leader,
          variant_label: getVariantLabel(v),
        }
      })
      .filter(Boolean),
    true
  )
})

</script>

<template>
    <div class="relative w-full popover" >
    <component 
        :is="productCardComponents[code] ?? ProductCardEcom3"
        :product="displayedProduct"
        :buttonStyle="buttonStyle"
        :buttonStyleLogin="buttonStyleLogin"
        :hasInBasket="hasInBasketList?.[displayedProduct.id]"
        :buttonStyleHover="buttonStyleHover"
        :hideLogin="hideLogin"
        @setFavorite="onAddFavourite"
        @unsetFavorite="onUnselectFavourite"
        @setBackInStock="onAddBackInStock"
        @unsetBackInStock="onUnselectBackInStock"
        @onVariantClick="getAllProductFromVariant"
        basketButton
        :isLoadingFavourite
        :isLoadingRemindBackInStock
        :bestSeller="bestSeller"
        :screenType
        :ref="(e)=> _render_components = e"
    />
        <Dialog v-model:visible="isVariantDialogOpen" modal dismissableMask :draggable="false"
            :style="{ width: '56rem' }" :breakpoints="{ '960px': '92vw' }"
            :pt="{ header: { class: '!items-start' } }">
            <template #header>
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wider text-primary">
                        {{ ctrans('Choose :axis & quantities', { axis: variantAxisLabel || ctrans('variant') }) }}
                    </div>
                    <div class="mt-1 text-xl font-bold leading-tight text-gray-900 md:text-2xl">
                        {{ displayedProduct.name }}
                    </div>
                </div>
            </template>

            <div v-if="loadingGetVariants" class="flex justify-center py-16">
                <LoadingIcon class="text-2xl" />
            </div>

            <VariantDialogContent v-else-if="listProducts.length"
                :variants="listProducts"
                :variantAxisLabel="variantAxisLabel"
                :hasInBasketList="hasInBasketList"
                :selectedProductId="selectedVariantProduct?.id ?? (hasSavedVariantOrder(listProducts) ? listProducts[0]?.id : product.id)"
                :isLoadingRemindBackInStock="isLoadingRemindBackInStock"
                @setBackInStock="onAddBackInStock"
                @unsetBackInStock="onUnselectBackInStock"
                @selectVariant="onSelectVariant"
                @close="isVariantDialogOpen = false" />
        </Dialog>
    </div>
</template>