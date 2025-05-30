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
import { ref, inject } from "vue"
import { trans } from "laravel-vue-i18n"
import { faStar } from "@fas"
import { faCheck } from "@far"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add( faConciergeBell, faGarage, faExclamationTriangle, faPencil, faSearch, faThLarge, faListUl, faStar, falStar, faTrashAlt, faCheck )

const props = defineProps<{
	data: {}
	tab?: string
	selectedData: {
		products: number[]
	}
}>()

function productRoute(product: Product) {
	switch (route().current()) {
		case "retina.dropshipping.products.index":
			return route("retina.dropshipping.products.show", [product.slug])
		case "retina.dropshipping.portfolios.index":
		case "retina.dropshipping.customer_sales_channels.portfolios.index":
			if (product.type == "StoredItem") {
				return route("retina.fulfilment.itemised_storage.stored_items.show", [product.slug])
			}

			return route("retina.dropshipping.customer_sales_channels.portfolios.show", [route().params['customerSalesChannel'], product.slug])

		case "grp.overview.catalogue.products.index":
			return route("grp.org.shops.show.catalogue.products.current_products.show", [
				product.organisation_slug,
				product.shop_slug,
				product.slug,
			])
		default:
			return '#'
	}
}

const locale = inject('locale', aikuLocaleStructure)

// const selectedProducts = ref<Product[]>([])
const onUnchecked = (itemId: number) => {
	props.selectedData.products = props.selectedData.products.filter(product => product !== itemId)
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
	>

		<template #cell(slug)="{ item: product }">
			<Link :href="productRoute(product)" class="primaryLink">
				{{ product["slug"] }}
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

		<!-- Column: Price -->
		<template #cell(status)="{ item: product }">
            <FontAwesomeIcon v-if="(product.platform_product_id) || (product.platform == 'manual')" v-tooltip="trans('Was uploaded to platform')" icon="far fa-check" class="text-green-500" fixed-width aria-hidden="true" />
        </template>

		<template #cell(actions)="{ item }">
			<ButtonWithLink
				v-tooltip="trans('Unselect portfolio')"
				type="negative"
				icon="fal fa-trash-alt"
				:routeTarget="item.delete_portfolio"
				size="s"
				:bindToLink="{
					preserveScroll: true,
				}"
			/>
		</template>
	</Table>
</template>

