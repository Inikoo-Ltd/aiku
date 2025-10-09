<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Product } from "@/types/product"
import { library } from "@fortawesome/fontawesome-svg-core"
import { inject, onMounted, ref, computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Components/Image.vue"
import { debounce, get, set } from "lodash-es"
import { faConciergeBell, faGarage, faExclamationTriangle, faSyncAlt, faPencil, faSearch, faThLarge, faListUl, faStar as falStar, faTrashAlt, faExclamationCircle, faClone, faLink} from "@fal"
import { faStar, faFilter } from "@fas"
import { faExclamationTriangle as fadExclamationTriangle } from "@fad"
import { faCheck } from "@far"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { notify } from "@kyvg/vue3-notification"
library.add( fadExclamationTriangle, faSyncAlt, faConciergeBell, faGarage, faExclamationTriangle, faPencil, faSearch, faThLarge, faListUl, faStar, faFilter, falStar, faTrashAlt, faCheck, faExclamationCircle, faClone, faLink )

interface PlatformData {
	id: number
	code: string
	name: string
	type: string
}

const props = defineProps<{
	data: {}
	tab?: string
	selectedData: {
		products: number[]
	}

    platform_data: PlatformData
	platform_user_id: number
	is_platform_connected: boolean
	progressToUploadToShopify: {}
	isPlatformManual?: boolean
}>()

function portfolioRoute(product: Product) {
    if (product.type == "StoredItem") {
        return route("retina.fulfilment.itemised_storage.stored_items.show", [product.slug])
    }

    return route("retina.dropshipping.customer_sales_channels.portfolios.show",
        [
        route().params['customerSalesChannel'], product.id])

}

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', retinaLayoutStructure)

// const selectedProducts = ref<Product[]>([])
const onUnchecked = (itemId: number) => {
	props.selectedData.products = props.selectedData.products.filter(product => product !== itemId)
}

const selectSocketiBasedPlatform = (porto: { id: number }) => {
    if (props.platform_data.type === 'shopify') {
        return {
            event: `shopify.${props.platform_user_id}.upload-product.${porto.id}`,
            action: '.shopify-upload-progress'
        }
    } else if (props.platform_data.type === 'woocommerce') {
        return {
            event: `woo.${props.platform_user_id}.upload-product.${porto.id}`,
            action: '.woo-upload-progress'
        }
    } else if (props.platform_data.type === 'ebay') {
        return {
            event: `ebay.${props.platform_user_id}.upload-product.${porto.id}`,
            action: '.ebay-upload-progress'
        }
    } else if (props.platform_data.type === 'amazon') {
        return {
            event: `amazon.${props.platform_user_id}.upload-product.${porto.id}`,
            action: '.amazon-upload-progress'
        }
    } else if (props.platform_data.type === 'magento') {
        return {
            event: `magento.${props.platform_user_id}.upload-product.${porto.id}`,
            action: '.magento-upload-progress'
        }
    }
}

const debReloadPage = debounce(() => {
	router.reload({
		except: ['auth', 'breadcrumbs', 'flash', 'layout', 'localeData', 'pageHead', 'ziggy']
	})
}, 1200)

onMounted(() => {
    props.data?.data?.forEach(porto => {
		if (selectSocketiBasedPlatform(porto)) {
			const xxx = window.Echo.private(selectSocketiBasedPlatform(porto)?.event).listen(
				selectSocketiBasedPlatform(porto)?.action,
				(eventData) => {
					console.log('socket in: ', porto.id, eventData)
					if(eventData.errors_response) {
						set(props.progressToUploadToShopify, [porto.id], 'error')
						setTimeout(() => {
							set(props.progressToUploadToShopify, [porto.id], null)
						}, 3000);

					} else {
						set(props.progressToUploadToShopify, [porto.id], 'success')
						debReloadPage()
					}
				}
			);

			console.log(`Subscription porto id: ${porto.id}`, xxx)

		}
    });

})

// Table: Filter out-of-stock and discontinued
const compTableFilterStatus = computed(() => {
	return layout.currentQuery?.[`${props.tab}_filter`]?.status
})
const isLoadingTable = ref<null | string>(null)
const onClickFilterOutOfStock = (query: string) => {
	let xx: string | null = ''
	if (compTableFilterStatus.value === query) {
		xx = null
	} else {
		xx = query
	}
	
	router.reload(
        {
            data: { [`${props.tab}_filter[status]`]: xx },  // Sent to url parameter (?tab=showcase, ?tab=menu)
            // only: [tabSlug],  // Only reload the props with dynamic name tabSlug (i.e props.showcase, props.menu)
            onStart: () => {
				isLoadingTable.value = query || null
            },
            onSuccess: () => {
            },
            onFinish: (e) => {
				isLoadingTable.value = null
            },
            onError: (e) => {
            }
        }
    )
}
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5" xxisCheckBox
		xxdisabledCheckbox="(xxx) => !!xxx.platform_product_id || xxx.platform == 'manual'" @onChecked="(item) => {
			console.log('onChecked', item)
			props.selectedData.products.push(item.id)
		}" @onUnchecked="(item) => {
			onUnchecked(item.id)
		}" :isChecked="(item) => props.selectedData.products.includes(item.id)" :rowColorFunction="(item) => {
			if (!isPlatformManual && is_platform_connected && !item.platform_product_id && get(progressToUploadToShopify, [item.id], undefined) != 'success') {
				return 'bg-yellow-50'
			} else {
				return ''
			}
		}" :isParentLoading="!!isLoadingTable">
		<template #add-on-button>
			<Button @click="onClickFilterOutOfStock('out-of-stock')"
				v-tooltip="trans('Filter the product that out of stock')" label="Out of stock" size="xs"
				:key="compTableFilterStatus" :type="compTableFilterStatus === 'out-of-stock' ? 'secondary' : 'tertiary'"
				:icon="compTableFilterStatus === 'out-of-stock' ? 'fas fa-filter' : 'fal fa-filter'"
				iconRight="fal fa-exclamation-triangle" :loading="isLoadingTable == 'out-of-stock'" />
			<Button @click="onClickFilterOutOfStock('discontinued')"
				v-tooltip="trans('Filter the product that discontinued')" label="Discontinued" size="xs"
				:key="compTableFilterStatus" :type="compTableFilterStatus === 'discontinued' ? 'secondary' : 'tertiary'"
				:icon="compTableFilterStatus === 'discontinued' ? 'fas fa-filter' : 'fal fa-filter'"
				iconRight="fal fa-times" :loading="isLoadingTable == 'discontinued'" />
		</template>

		<template #cell(image)="{ item: product }">
			<div class="relative group">
				<div class="relative overflow-hidden w-10 h-10">
					<Image :src="product.image" :alt="product.name" />
				</div>
				<!-- Popover with larger image -->
				<div
					class="absolute left-full top-0 ml-2 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 pointer-events-none">
					<div class="bg-white border border-gray-200 rounded-lg shadow-lg p-2">
						<div class="w-64 h-64 overflow-hidden rounded">
							<Image :src="product.full_size_image || product.image" :alt="product.name"
								class="w-full h-full object-cover" />
						</div>
					</div>
				</div>
			</div>

		</template>

		<template #cell(name)="{ item: product }">
			<Link :href="portfolioRoute(product)" class="primaryLink whitespace-nowrap">
			{{ product["code"] }}
			</Link>
			<div class="text-base font-semibold">
				{{ product["name"] }}
			</div>
			<div class="text-sm text-gray-500 italic flex gap-x-6 gap-y-2">
				<div>
					{{ trans("Stocks:") }} {{ locale.number(product.quantity_left) }}
				</div>
				<div>
					{{ trans("Weight:") }} {{ locale.number(product.weight/1000) }} kg
				</div>
			</div>

			<div class="text-sm text-gray-500 italic flex gap-x-10 gap-y-2">
				<div>
					{{ trans("Price:") }} {{ locale.currencyFormat(product.currency_code, product.price) }}
				</div>
				<div>
					{{ trans("RRP:") }} {{ locale.currencyFormat(product.currency_code, product.customer_price) }}
				</div>
			</div>

			<!-- Section: is code exist in platform -->
			<div v-if="product.is_code_exist_in_platform" class="text-xs text-amber-500">
				<FontAwesomeIcon icon="fas fa-exclamation-triangle" class="" fixed-width aria-hidden="true" />
				<span class="pr-2">{{ trans("We found same product in your shop, do you want to create new or use existing?") }}</span>
				<Button v-tooltip="trans('Will create new product in :platform', {platform: props.platform_data.name})"
					label="Create new" icon="fal fa-plus" type="tertiary" size="xxs" />
				<span class="px-2 text-gray-500">or</span>
				<Button
					v-tooltip="trans('Will sync the product and prioritize our product', {platform: props.platform_data.name})"
					label="Use Existing" icon="fal fa-sync-alt"
					:disabled="data?.product_availability?.options === 'use_existing'"
					:type="data?.product_availability?.options === 'use_existing' ? 'primary' : 'tertiary'"
					size="xxs" />
			</div>
		</template>



		<!-- Column: Actions -->
		<template #cell(actions)="{ item }">
			<div class="mx-auto flex flex-wrap justify-center gap-2">
				<ButtonWithLink v-tooltip="trans('Remove product from list')" type="negative" icon="fal fa-times"
					:routeTarget="item.update_portfolio" :body="{
						'status': false,
					}" size="xs" :bindToLink="{
						preserveScroll: true,
					}" />
			</div>
		</template>
	</Table>
</template>

