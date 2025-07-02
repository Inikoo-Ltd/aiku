<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Product } from "@/types/product"

import { library } from "@fortawesome/fontawesome-svg-core"
import { faConciergeBell, faGarage, faExclamationTriangle, faPencil, faSearch, faThLarge, faListUl, faStar as falStar, faTrashAlt} from "@fal"
import { inject, onMounted, ref } from "vue"
import { trans } from "laravel-vue-i18n"
import { faStar } from "@fas"
import { faCheck } from "@far"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Components/Image.vue"
import { get, set } from "lodash"
import ConditionIcon from "@/Components/Utils/ConditionIcon.vue"

library.add( faConciergeBell, faGarage, faExclamationTriangle, faPencil, faSearch, faThLarge, faListUl, faStar, falStar, faTrashAlt, faCheck )

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

// const selectedProducts = ref<Product[]>([])
const onUnchecked = (itemId: number) => {
	props.selectedData.products = props.selectedData.products.filter(product => product !== itemId)
}

const progressToUploadToShopify = ref({})
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
onMounted(() => {
    // emits('mounted')
    props.data.data?.forEach(porto => {
        console.log('porto', selectSocketiBasedPlatform(porto))
        const xxx = window.Echo.private(selectSocketiBasedPlatform(porto)?.event).listen(
            selectSocketiBasedPlatform(porto)?.action,
            (eventData) => {
                console.log('socket in: ', porto.id, eventData)
                if(eventData.errors_response) {
                    set(progressToUploadToShopify.value, [porto.id], 'error')
                    setTimeout(() => {
                        set(progressToUploadToShopify.value, [porto.id], null)
                    }, 3000);

                } else {
                    set(progressToUploadToShopify.value, [porto.id], 'success')
                }
            }
        );

        console.log('xxx', xxx)
    });

})
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
	>
        <template #cell(image)="{ item: product }">
            <div class="overflow-hidden w-10 h-10">
				<Image :src="product.image" :alt="product.name" />
			</div>
        </template>

		<template #cell(code)="{ item: product }">
			<Link :href="portfolioRoute(product)" class="primaryLink whitespace-nowrap">
				{{ product["code"] }}
			</Link>
        </template>

		<!-- Column: Stock -->
		<template #cell(quantity_left)="{ item }">
			<div>
				{{ locale.number(item.quantity_left) }}
			</div>
		</template>

		<!-- Column: Weight -->
		<template #cell(weight)="{ item }">
			<div>
				{{ locale.number(item.weight/1000) }} kg
			</div>
		</template>

		<!-- Column: Price -->
		<template #cell(price)="{ item }">
			<div>
				{{ locale.currencyFormat(item.currency_code, item.price) }}
			</div>
		</template>

		<!-- Column: RPP -->
		<template #cell(customer_price)="{ item }">
			<div>
				{{ locale.currencyFormat(item.currency_code, item.customer_price) }}
			</div>
		</template>

		<!-- Column: Price -->
		<template #cell(status)="{ item: product }">
			<div class="flex justify-center">
				
				<FontAwesomeIcon v-if="(product.platform_product_id)" v-tooltip="trans('Uploaded to platform')" icon="far fa-check" class="text-green-500" fixed-width aria-hidden="true" />
				<ConditionIcon v-if="get(progressToUploadToShopify, [product.id], null)" :state="get(progressToUploadToShopify, [product.id], undefined)" class="text-xl mx-auto" />
				<div v-else>
					<ButtonWithLink
						:routeTarget="product.platform_upload_portfolio"
						label="Upload"
						icon="fal fa-upload"
						type="positive"
						size="xs"
						@success="() => set(progressToUploadToShopify, [product.id], 'loading')"
					/>
				</div>
			</div>
        </template>

		<template #cell(actions)="{ item }">
			<div class="mx-auto">
				<ButtonWithLink
					v-tooltip="trans('Unselect portfolio')"
					type="negative"
					icon="fal fa-trash-alt"
					:routeTarget="item.delete_portfolio"
					size="xs"
					:bindToLink="{
						preserveScroll: true,
					}"
				/>
			</div>
		</template>
	</Table>
</template>

