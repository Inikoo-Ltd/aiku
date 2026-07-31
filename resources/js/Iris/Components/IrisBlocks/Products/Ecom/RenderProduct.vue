<script setup lang="ts">
import { useLocaleStore } from "@/Stores/locale"
import { inject, ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import Popover from 'primevue/popover'

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
import VariantDialogContent from "@/Iris/Components/IrisBlocks/Products/Ecom/VariantDialogContent.vue"
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
const _render_components = ref(null)
const popoverRef = ref<any>(null)
const isLoadingFavourite = ref(false)
const loadingGetVariants = ref(false)

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

const popoverTarget = ref<HTMLElement | null>(null)

const POPOVER_ARROW_GAP = 22
const POPOVER_VIEWPORT_PADDING = 12

const alignPopoverAboveTarget = () => {
  const panel = popoverRef.value?.container as HTMLElement | undefined
  const target = popoverTarget.value

  if (!panel || !target) return

  const targetRect = target.getBoundingClientRect()
  const panelRect = panel.getBoundingClientRect()

  const availableWidth = document.documentElement.clientWidth
  const maxLeft = Math.max(POPOVER_VIEWPORT_PADDING, availableWidth - panelRect.width - POPOVER_VIEWPORT_PADDING)
  const centeredLeft = targetRect.left + targetRect.width / 2 - panelRect.width / 2

  const left = Math.min(Math.max(POPOVER_VIEWPORT_PADDING, centeredLeft), maxLeft)
  const top = Math.max(POPOVER_VIEWPORT_PADDING, targetRect.top - panelRect.height - POPOVER_ARROW_GAP)

  panel.style.position = 'absolute'
  panel.style.left = `${left + window.scrollX}px`
  panel.style.top = `${top + window.scrollY}px`
  panel.style.bottom = 'auto'
  panel.style.transformOrigin = 'bottom center'
}

const closePopover = () => {
  popoverRef.value?.hide()
}

const bindPopoverViewportListeners = () => {
  window.addEventListener('scroll', closePopover, true)
  window.addEventListener('resize', closePopover)
}

const unbindPopoverViewportListeners = () => {
  window.removeEventListener('scroll', closePopover, true)
  window.removeEventListener('resize', closePopover)
}

const getAllProductFromVariant = async (
  variant_id: string,
  event: MouseEvent
) => {
  if (!variant_id || !event) return

  const target = event.currentTarget as HTMLElement
  if (!target) return

  popoverTarget.value = target
  loadingGetVariants.value = true

  popoverRef.value?.show({ currentTarget: target } as any)

  await nextTick()
  alignPopoverAboveTarget()

  bindPopoverViewportListeners()

  try {
    const response = await axios.get(
      route('iris.json.variant', { variant: variant_id })
    )
    variant.value = response.data
  } catch (e) {
    console.error(e)
    popoverRef.value?.hide()
  } finally {
    loadingGetVariants.value = false
    await nextTick()
    alignPopoverAboveTarget()
  }
}

const getVariantLabel = (entry: number) => {
  if (!entry) return null

  return variant.value.variant_data.variants
    .map(v => entry[v.label])
    .filter(Boolean)
    .join(" – ")
}

const listProducts = computed(() => {
  if (!variant.value?.variant_data?.products) return []

  return Object.values(variant.value.variant_data.products)
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
    .filter(Boolean)
    .sort((a: any, b: any) => {
      if (a.is_leader && !b.is_leader) return -1
      if (!a.is_leader && b.is_leader) return 1

      if (!a.variant_label) return 1
      if (!b.variant_label) return -1

      return a.variant_label.localeCompare(b.variant_label, undefined, {
        numeric: true,
        sensitivity: "base",
      })
    })
})

const onClickOutside = (e: MouseEvent) => {
  const popoverEl = popoverRef.value?.container
  if (!popoverEl) return

  if (!popoverEl.contains(e.target as Node) && !popoverTarget.value?.contains(e.target as Node)) {
    popoverRef.value.hide()
  }
}

const onPopoverHide = () => {
  unbindPopoverViewportListeners()
  popoverTarget.value = null
}

onMounted(() => {
  document.addEventListener('click', onClickOutside, true)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onClickOutside, true)
  unbindPopoverViewportListeners()
})

</script>

<template>
    <div class="relative w-full popover" >
    <component 
        :is="productCardComponents[code] ?? ProductCardEcom3" 
        :product="product"
        :buttonStyle="buttonStyle"
        :buttonStyleLogin="buttonStyleLogin"
        :hasInBasket="hasInBasketList?.[product.id]"
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
       <Popover ref="popoverRef" appendTo="body" dismissable
    @hide="onPopoverHide"
    class="popover-custom w-max max-w-[180px] md:max-w-[200px] lg:max-w-[260px]">
            <div class="p-4 text-sm break-words">
                <loading-icon v-if="loadingGetVariants" />
                <variant-dialog-content 
                    v-else :variants="listProducts" 
                    :hasInBasketList="hasInBasketList"
                    @setBackInStock="onAddBackInStock" 
                    @unsetBackInStock="onUnselectBackInStock"
                    :isLoadingRemindBackInStock="isLoadingRemindBackInStock" />
            </div>
        </Popover>
    </div>
</template>

<style>
.popover-custom.p-popover {
    margin-block-start: 0;
    margin-block-end: 0;
}

.popover-custom.p-popover::before,
.popover-custom.p-popover::after {
    top: 100%;
    bottom: auto;
    left: 50%;
    margin-left: 0;
    transform: translateX(-50%);
    border-style: solid;
    border-color: transparent;
    border-bottom-color: transparent;
}

.popover-custom.p-popover::before {
    border-width: 7px;
    border-top-color: var(--p-popover-border-color, #e5e7eb);
}

.popover-custom.p-popover::after {
    border-width: 6px;
    border-top-color: var(--p-popover-background, #fff);
    z-index: 1;
}
</style>