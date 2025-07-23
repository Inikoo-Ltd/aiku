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
import { notify } from "@kyvg/vue3-notification"
import Modal from "@/Components/Utils/Modal.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
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

// Section: Modal Shopify select variant
const isOpenModal = ref(false)
const selectedRow = ref(null)
const isLoadingSubmit = ref(false)
const querySearchPortfolios = ref('')
const portfoliosList = ref<Product[]>([
	{
		"id": 124682,
		"slug": "aatom-13-awd",
		"code": "AATOM-13",
		"image": {
			"png": "https://media.aiku.io/48QAqfDxcejWuJ0IQnR6FWPWGcumudWUK52refQp-Vk/rs::0:300::/bG9jYWw6Ly9tZWRpYS9TSC9DQy82MFIzMEMxRzZNVktDQ1NIL2NlYTI3M2EzLmpwZWc.png",
			"avif": "https://media.aiku.io/lK4uLZHjC09slPHtR3qOjvDKzws4TAl7Q5utWoDWGAs/rs::0:300::/bG9jYWw6Ly9tZWRpYS9TSC9DQy82MFIzMEMxRzZNVktDQ1NIL2NlYTI3M2EzLmpwZWc.avif",
			"webp": "https://media.aiku.io/QZAAzKSCssL4-6KBp9IYbUxzbQPO3cazo1d4dJFQJBI/rs::0:300::/bG9jYWw6Ly9tZWRpYS9TSC9DQy82MFIzMEMxRzZNVktDQ1NIL2NlYTI3M2EzLmpwZWc.webp",
			"png_2x": "https://media.aiku.io/XuTRFP1Dy89CH6txbj9hxIrK1EJzbflbnZ5ZEWutvjk/rs::0:600::/bG9jYWw6Ly9tZWRpYS9TSC9DQy82MFIzMEMxRzZNVktDQ1NIL2NlYTI3M2EzLmpwZWc.png",
			"avif_2x": "https://media.aiku.io/nmU_amChUdcEXcnFF47vVX__FSHOFfb3mlju2Mv7NPo/rs::0:600::/bG9jYWw6Ly9tZWRpYS9TSC9DQy82MFIzMEMxRzZNVktDQ1NIL2NlYTI3M2EzLmpwZWc.avif",
			"webp_2x": "https://media.aiku.io/C1ow5wRZtTa9gyQIDmYc9WU4qIXltOW-vJ3yhnPHlHk/rs::0:600::/bG9jYWw6Ly9tZWRpYS9TSC9DQy82MFIzMEMxRzZNVktDQ1NIL2NlYTI3M2EzLmpwZWc.webp",
			"original": "https://media.aiku.io/kD5EfcuDXmcDMfdud6h67Mvt8o-rMxIuNeHuRP3IRT0/rs::0:300::/bG9jYWw6Ly9tZWRpYS9TSC9DQy82MFIzMEMxRzZNVktDQ1NIL2NlYTI3M2EzLmpwZWc",
			"original_2x": "https://media.aiku.io/TIAMo0qQH6aRbXSddGskcEmEZejFsOJXtU9SfQjnDSY/rs::0:600::/bG9jYWw6Ly9tZWRpYS9TSC9DQy82MFIzMEMxRzZNVktDQ1NIL2NlYTI3M2EzLmpwZWc"
		},
		"price": "24.12",
		"name": "Ibiza Atomiser - Infinity Lights - USB - Colour Change",
		"gross_weight": 750,
		"currency_code": "GBP",
		"currency_id": 23
	},
	{
		"id": 1111,
		"slug": "gggg-13-awd",
		"code": "GGGG-13",
		"image": {
			"png": "https://media.aiku.io/48QAqfDxcejWuJ0IQnR6FWPWGcumudWUK52refQp-Vk/rs::0:300::/bG9jYWw6Ly9tZWRpYS9TSC9DQy82MFIzMEMxRzZNVktDQ1NIL2NlYTI3M2EzLmpwZWc.png",
			"avif": "https://media.aiku.io/lK4uLZHjC09slPHtR3qOjvDKzws4TAl7Q5utWoDWGAs/rs::0:300::/bG9jYWw6Ly9tZWRpYS9TSC9DQy82MFIzMEMxRzZNVktDQ1NIL2NlYTI3M2EzLmpwZWc.avif",
			"webp": "https://media.aiku.io/QZAAzKSCssL4-6KBp9IYbUxzbQPO3cazo1d4dJFQJBI/rs::0:300::/bG9jYWw6Ly9tZWRpYS9TSC9DQy82MFIzMEMxRzZNVktDQ1NIL2NlYTI3M2EzLmpwZWc.webp",
			"png_2x": "https://media.aiku.io/XuTRFP1Dy89CH6txbj9hxIrK1EJzbflbnZ5ZEWutvjk/rs::0:600::/bG9jYWw6Ly9tZWRpYS9TSC9DQy82MFIzMEMxRzZNVktDQ1NIL2NlYTI3M2EzLmpwZWc.png",
			"avif_2x": "https://media.aiku.io/nmU_amChUdcEXcnFF47vVX__FSHOFfb3mlju2Mv7NPo/rs::0:600::/bG9jYWw6Ly9tZWRpYS9TSC9DQy82MFIzMEMxRzZNVktDQ1NIL2NlYTI3M2EzLmpwZWc.avif",
			"webp_2x": "https://media.aiku.io/C1ow5wRZtTa9gyQIDmYc9WU4qIXltOW-vJ3yhnPHlHk/rs::0:600::/bG9jYWw6Ly9tZWRpYS9TSC9DQy82MFIzMEMxRzZNVktDQ1NIL2NlYTI3M2EzLmpwZWc.webp",
			"original": "https://media.aiku.io/kD5EfcuDXmcDMfdud6h67Mvt8o-rMxIuNeHuRP3IRT0/rs::0:300::/bG9jYWw6Ly9tZWRpYS9TSC9DQy82MFIzMEMxRzZNVktDQ1NIL2NlYTI3M2EzLmpwZWc",
			"original_2x": "https://media.aiku.io/TIAMo0qQH6aRbXSddGskcEmEZejFsOJXtU9SfQjnDSY/rs::0:600::/bG9jYWw6Ly9tZWRpYS9TSC9DQy82MFIzMEMxRzZNVktDQ1NIL2NlYTI3M2EzLmpwZWc"
		},
		"price": "24.12",
		"name": "Ibiza Atomiser - Infinity Lights - USB - Colour Change",
		"gross_weight": 750,
		"currency_code": "GBP",
		"currency_id": 23
	}
])
const filteredPortfolios = computed(() => {
	if (!querySearchPortfolios.value) {
		return portfoliosList.value
	}
	return portfoliosList.value.filter(portfolio => {
		return portfolio.name.toLowerCase().includes(querySearchPortfolios.value.toLowerCase())
			|| portfolio.code.toLowerCase().includes(querySearchPortfolios.value.toLowerCase())
	})
})
const selectedVariant = ref<Product | null>(null)
const onSubmitVariant = () => {
	console.log(selectedVariant.value)

	isOpenModal.value = false
	selectedVariant.value = null
	selectedRow.value = null

	// Section: Submit
	// router.post(
	// 	'xxxx',
	// 	{
	// 		data: 'qqq'
	// 	},
	// 	{
	// 		preserveScroll: true,
	// 		preserveState: true,
	// 		onStart: () => { 
	// 			isLoadingSubmit.value = true
	// 		},
	// 		onSuccess: () => {
	// 			notify({
	// 				title: trans("Success"),
	// 				text: trans("Successfully submit the data"),
	// 				type: "success"
	// 			})
	// 		},
	// 		onError: errors => {
	// 			notify({
	// 				title: trans("Something went wrong"),
	// 				text: trans("Failed to set location"),
	// 				type: "error"
	// 			})
	// 		},
	// 		onFinish: () => {
	// 			isLoadingSubmit.value = false
	// 		},
	// 	}
	// )
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
			<div class="text-sm text-gray-500 italic flex gap-x-10 gap-y-2">
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
						is_platform_connected
						&& !item.platform_product_id
						&& get(progressToUploadToShopify, [item.id], undefined) != 'success'
					"
					:routeTarget="item.platform_upload_portfolio"
					label="Connect"
					icon="fal fa-upload"
					type="positive"
					size="xs"
					:bindToLink="{
						preserveScroll: true,
					}"
					@success="() => set(progressToUploadToShopify, [item.id], 'loading')"
					:disabled="get(progressToUploadToShopify, [item.id], null)"
				/>

				
			</div>
		</template>

		<!-- Column: Actions 2 -->
		<template #cell(actions2)="{ item }">
			<Button
				@click="isOpenModal = true, selectedRow = item"
				label="Modal Shopify"
				type="tertiary"
			>

			</Button>
		</template>

		<!-- Column: Actions 3 -->
		<template #cell(actions3)="{ item }">
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
		</template>
	</Table>

	<Modal :isOpen="isOpenModal" width="w-full max-w-2xl h-full max-h-[570px]" @close="isOpenModal = false">
		<div class="relative isolate">

            <div v-if="isLoadingSubmit" class="flex justify-center items-center text-7xl text-white absolute z-10 inset-0 bg-black/40">
                <LoadingIcon />
            </div>

            <div class="mb-2">
                <PureInput
                    v-model="querySearchPortfolios"
                    aupdate:modelValue="() => debounceGetPortfoliosList()"
                    :placeholder="trans('Input to search portfolios')"
                />
                <slot name="afterInput">
                </slot>
            </div>
			
            <div class="xh-full xmd:h-[570px] text-base font-normal">
                <div class="col-span-4 pb-8 md:pb-2 h-fit overflow-auto flex flex-col">
                    <div class="flex justify-between items-center">
                        <!-- <div class="font-semibold text-lg py-1">{{ trans("Result") }} ({{ locale?.number(portfoliosMeta?.total || 0) }})</div> -->
                        
                    </div>
                    <div class="border-t border-gray-300 mb-1"></div>
                    <div class="h-full md:h-[400px] overflow-auto py-2 relative">
                        <!-- Products list -->
                        <div class="grid grid-cols-2 gap-3 pb-2">
							<template v-if="portfoliosList.length > 0">
								<div
									v-for="(item, index) in filteredPortfolios"
									:key="index"
									@click="() => selectedVariant = item"
									class="relative h-fit rounded cursor-pointer p-2 flex flex-col md:flex-row gap-x-2 border"
									:class="[
										selectedVariant?.id === item.id ? 'bg-green-100 border-green-400' : ''
									]"
								>
									<Transition name="slide-to-right">
										<FontAwesomeIcon v-if="selectedVariant?.id === item.id" icon="fas fa-check-circle" class="bottom-2 right-2 absolute text-green-500" fixed-width aria-hidden="true" />
									</Transition>
									<slot name="product" :item="item">
										<Image v-if="item.image" :src="item.image" class="w-16 h-16 overflow-hidden mx-auto md:mx-0 mb-4 md:mb-0" imageCover :alt="item.name" />
										<div class="flex flex-col justify-between">
											<div class="w-fit" xclick="() => selectProduct(item)">
												<div v-tooltip="trans('Name')" class="w-fit font-semibold leading-none mb-1">{{ item.name || 'no name' }}</div>
												<div v-if="!item.no_code" v-tooltip="trans('Code')" class="w-fit text-xs text-gray-400 italic">{{ item.code || 'no code' }}</div>
												<div v-if="item.reference" v-tooltip="trans('Reference')" class="w-fit text-xs text-gray-400 italic">{{ item.reference || 'no reference' }}</div>
												<div v-if="item.gross_weight" v-tooltip="trans('Weight')" class="w-fit text-xs text-gray-400 italic">{{ item.gross_weight }}</div>
											</div>
											<div v-if="!item.no_price" xclick="() => selectProduct(item)" v-tooltip="trans('Price')" class="w-fit text-xs text-gray-x500">
												{{ locale?.currencyFormat(item.currency_code || 'usd', item.price || 0) }}
											</div>
										</div>
									</slot>
								</div>
							</template>
							<div v-else class="text-center text-gray-500 col-span-3">
								{{ trans("No products found") }}
							</div>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <!-- <Pagination
                        v-if="portfoliosMeta"
                        :on-click="getPortfoliosList"
                        :has-data="true"
                        :meta="portfoliosMeta"
                        xexportLinks="queryBuilderProps.exportLinks"
                        :per-page-options="[]"
                        xon-per-page-change="onPerPageChange"
                    /> -->
                    
					<div class="mt-4">
						<Button
							@click="() => onSubmitVariant()"
							xdisabled="selectedProduct.length < 1"
							xv-tooltip="selectedProduct.length < 1 ? trans('Select at least one product') : ''"
							xlabel="submitLabel ?? `${trans('Add')} ${selectedProduct.length}`"
							label="Select as variant"
							type="primary"
							full
							xicon="fas fa-plus"
							:loading="isLoadingSubmit"
						/>
					</div>
                </div>
            </div>
        </div>
	</Modal>
</template>

