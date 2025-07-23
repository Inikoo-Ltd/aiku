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
import ConditionIcon from "@/Components/Utils/ConditionIcon.vue"
import { faConciergeBell, faGarage, faExclamationTriangle, faSyncAlt, faPencil, faSearch, faThLarge, faListUl, faStar as falStar, faTrashAlt, faExclamationCircle, faClone, faLink} from "@fal"
import { faStar, faFilter } from "@fas"
import { faExclamationTriangle as fadExclamationTriangle } from "@fad"
import { faCheck } from "@far"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
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
	<Table
		:resource="data"
		:name="tab"
		class="mt-5"
		xxisCheckBox
		xxdisabledCheckbox="(xxx) => !!xxx.platform_product_id || xxx.platform == 'manual'"
		@onChecked="(item) => {
			console.log('onChecked', item)
			props.selectedData.products.push(item.id)
		}"
		@onUnchecked="(item) => {
			onUnchecked(item.id)
		}"
		:isChecked="(item) => props.selectedData.products.includes(item.id)"
		:rowColorFunction="(item) => {
			if (!isPlatformManual && is_platform_connected && !item.platform_product_id && get(progressToUploadToShopify, [item.id], undefined) != 'success') {
				return 'bg-yellow-50'
			} else {
				return ''
			}
		}"
		:isParentLoading="!!isLoadingTable"
	>
		<template #add-on-button>
			<Button
				@click="onClickFilterOutOfStock('out-of-stock')"
				v-tooltip="trans('Filter the product that out of stock')"
				label="Out of stock"
				size="xs"
				:key="compTableFilterStatus"
				:type="compTableFilterStatus === 'out-of-stock' ? 'secondary' : 'tertiary'"
				:icon="compTableFilterStatus === 'out-of-stock' ? 'fas fa-filter' : 'fal fa-filter'"
				iconRight="fal fa-exclamation-triangle"
				:loading="isLoadingTable == 'out-of-stock'"
			/>
			<Button
				@click="onClickFilterOutOfStock('discontinued')"
				v-tooltip="trans('Filter the product that discontinued')"
				label="Discontinued"
				size="xs"
				:key="compTableFilterStatus"
				:type="compTableFilterStatus === 'discontinued' ? 'secondary' : 'tertiary'"
				:icon="compTableFilterStatus === 'discontinued' ? 'fas fa-filter' : 'fal fa-filter'"
				iconRight="fal fa-times"
				:loading="isLoadingTable == 'discontinued'"
			/>
		</template>

        <template #cell(image)="{ item: product }">
            <div class="overflow-hidden w-10 h-10">
				<Image :src="product.image" :alt="product.name" />
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
				<Button v-tooltip="trans('Will create new product in :platform', {platform: props.platform_data.name})" label="Create new" icon="fal fa-plus" type="tertiary" size="xxs" />
				<span class="px-2 text-gray-500">or</span>
				<Button v-tooltip="trans('Will sync the product and prioritize our product', {platform: props.platform_data.name})" label="Use Existing" icon="fal fa-sync-alt" :disabled="data?.product_availability?.options === 'use_existing'" :type="data?.product_availability?.options === 'use_existing' ? 'primary' : 'tertiary'" size="xxs" />
			</div>
        </template>

		<!-- Column: Stock -->
		<!-- <template #cell(quantity_left)="{ item }">
			<div>
				{{ locale.number(item.quantity_left) }}
			</div>
		</template> -->

		<!-- Column: Weight -->
		<!-- <template #cell(weight)="{ item }">
			<div>
				{{ locale.number(item.weight/1000) }} kg
			</div>
		</template> -->

		<!-- Column: Price -->
		<template #cell(price)="{ item }">
			<div class="text-gray-700">
				{{ locale.currencyFormat(item.currency_code, item.price) }}
			</div>
			<div class="whitespace-nowrap text-gray-500">
				RRP: {{ locale.currencyFormat(item.currency_code, item.customer_price) }}
			</div>
		</template>

		<!-- Column: RPP -->
		<!-- <template #cell(customer_price)="{ item }">
			<div>
				{{ locale.currencyFormat(item.currency_code, item.customer_price) }}
			</div>
		</template> -->

		<!-- Column: Status -->
		<template #cell(status)="{ item: product }">
			<div class="flex justify-center">
				<template v-if="is_platform_connected">
					<FontAwesomeIcon v-if="(product.platform_product_id)" v-tooltip="trans('Uploaded to platform')" icon="far fa-check" class="text-green-500" fixed-width aria-hidden="true" />
					<ConditionIcon v-else-if="get(progressToUploadToShopify, [product.id], null)" :state="get(progressToUploadToShopify, [product.id], undefined)" class="text-xl mx-auto" />
					<span v-if="(product.upload_warning)" class="text-red-500 text-xs text-center italic">
						{{ product.upload_warning }}
					</span>
					<span v-else-if="!product.platform_product_id" class="text-gray-500 text-xs text-center italic">
						{{ trans("Pending upload") }}
					</span>
				</template>

				<div v-else-if="isPlatformManual" v-tooltip="trans('Your channel is not connected to the platform yet.')" class="text-center text-lg">
                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-red-500" fixed-width aria-hidden="true" />
                </div>
			</div>
        </template>

		<!-- Column: Actions -->
		<template #cell(actions)="{ item }">
			<div class="mx-auto flex flex-wrap justify-center gap-2">
				<!-- {{ item.platform_product_id }} -->
				<ButtonWithLink
					v-if="
						!isPlatformManual
						&& is_platform_connected
						&& !item.platform_product_id
						&& get(progressToUploadToShopify, [item.id], undefined) != 'success'
					"
					:routeTarget="item.platform_upload_portfolio"
					label="Upload"
					icon="fal fa-upload"
					type="positive"
					size="xs"
					:bindToLink="{
						preserveScroll: true,
					}"
					@success="() => set(progressToUploadToShopify, [item.id], 'loading')"
					:disabled="get(progressToUploadToShopify, [item.id], null)"
				/>

				
				<ButtonWithLink
					v-tooltip="trans('Unselect product')"
					type="negative"
					icon="fal fa-skull"
					:routeTarget="item.update_portfolio"
					:body="{
						'status': false,
					}"
					size="xs"
					:bindToLink="{
						preserveScroll: true,
					}"
				/>
			</div>
		</template>
	</Table>
</template>

