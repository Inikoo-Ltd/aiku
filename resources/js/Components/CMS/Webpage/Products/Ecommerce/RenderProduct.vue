<script setup lang="ts">
import { useLocaleStore } from "@/Stores/locale"
import { inject, ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'

import { faQuestionCircle } from "@fal"
import { faStarHalfAlt } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ProductResource } from '@/types/Iris/Products'
import { routeType } from '@/types/route'
import { getProductsRenderB2bComponent } from "@/Composables/getIrisComponents"
import axios from "axios"

library.add(faStarHalfAlt, faQuestionCircle)

const layout = inject('layout', retinaLayoutStructure)

const locale = useLocaleStore()


const props = withDefaults(defineProps<{
    product: ProductResource
    hasInBasket?: any
    basketButton?: boolean
    attachToFavouriteRoute?: routeType
    dettachToFavouriteRoute?: routeType
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
}>(), {
    basketButton: true,
    addToBasketRoute: {
        name: 'iris.models.transaction.store',
    },
    updateBasketQuantityRoute: {
        name: 'iris.models.transaction.update',
    },
    attachToFavouriteRoute: {
        name: 'iris.models.favourites.store',
    },
    dettachToFavouriteRoute: {
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
const showVariantPopover = ref(false)
const variant = ref<any>(null)


// Section: Add to Favourites
const isLoadingFavourite = ref(false)

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
                    title: trans("Something went wrong"),
                    text: trans("Failed to add the product to favourites"),
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
        route(props.dettachToFavouriteRoute.name, {
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
                //     title: trans("Success"),
                //     text: trans("Added to portfolio"),
                //     type: "success"
                // })
                layout.reload_handle()
                product.is_favourite = false
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
			title: trans("Something went wrong"),
			text: trans("Failed to add the product to remind back in stock"),
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
			title: trans("Something went wrong"),
			text: trans("Failed to remove the product from remind back in stock"),
			type: "error"
		})
	} finally {
		isLoadingRemindBackInStock.value = false
	}
}

const popoverRef = ref<HTMLElement | null>(null)

const onClickOutside = (e: MouseEvent) => {
  if (!popoverRef.value) return
  if (!popoverRef.value.contains(e.target as Node)) {
    showVariantPopover.value = false
  }
}

const getAllProductFromVariant = async (variant_id: string) => {
  if (!variant_id) return

  showVariantPopover.value = false

  try {
    const response = await axios.get(
      route("iris.json.variant", {
        variant: variant_id,
      })
    )

    variant.value = response.data
    showVariantPopover.value = true
  } catch (e) {
    console.error("getAllProductFromVariant error", e)
  }
}

const getVariantLabel = (index: number) => {
  const entry = variant.value.data.product[index]
  if (!entry) return null

  return variant.value.data
    .map(v => entry[v.label])
    .filter(Boolean)
    .join(" – ")
}

const listProducts = computed(() => {
  if(!variant.value) return []
  return variant.value.data.product
    .map((v, index) => {
      const baseProduct = variant.value.product.find(
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


onMounted(() => {
  document.addEventListener("click", onClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener("click", onClickOutside)
})




</script>

<template>
    <div class="relative">
        <component 
        :is="getProductsRenderB2bComponent(code)" 
        :product="product"
        :buttonStyle="buttonStyle"
        :buttonStyleLogin="buttonStyleLogin"
        :hasInBasket="hasInBasket"
        :buttonStyleHover="buttonStyleHover" 
        @setFavorite="onAddFavourite"
        @unsetFavorite="onUnselectFavourite"
        @setBackInStock="onAddBackInStock"
        @unsetBackInStock="onUnselectBackInStock"
        @onVariantClick="getAllProductFromVariant"
        basketButton
        :isLoadingFavourite
        :isLoadingRemindBackInStock
        :button
        :bestSeller="bestSeller"
        :screenType
    />

     <transition name="fade">
        <div
            v-if="showVariantPopover"
            class="absolute left-0 top-[-1px] z-50 mt-2 w-full rounded-lg border bg-white shadow-lg"
        >
            <div class="p-4 text-sm">
              {{ listProducts }}
            </div>
        </div>
     </transition>
    </div>
    
</template>

<style scoped></style>