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
import { faConciergeBell, faGarage, faExclamationTriangle, faPencil, faSearch, faThLarge, faListUl, faStar as falStar, faTrashAlt, } from "@fal"
import { ref, inject } from "vue"
import { trans } from "laravel-vue-i18n"
import { faStar } from "@fas"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"

library.add( faConciergeBell, faGarage, faExclamationTriangle, faPencil, faSearch, faThLarge, faListUl, faStar, falStar, faTrashAlt )

const props = defineProps<{
	data: {}
	tab?: string
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

</script>

<template>


	<!-- <pre>{{ data.data[0] }}</pre> -->
	<Table :resource="data" :name="tab" class="mt-5">

		<template #cell(slug)="{ item: product }">
			<Link :href="productRoute(product)" class="primaryLink">
				{{ product["slug"] }}
			</Link>
		</template>

		<template #cell(platform_product_id)="{ item }">
			<div>
				{{ item.platform_product_id }}
			</div>
		</template>

		<template #cell(quantity_left)="{ item }">
			<div>
				{{ locale.number(item.quantity_left) }}
			</div>
		</template>

		<template #cell(actions)="{ item }">
			<ButtonWithLink
				v-tooltip="trans('Unselect portfolio')"
				type="negative"
				icon="fal fa-times"
				:routeTarget="item.delete_portfolio"
				size="s"
			/>
		</template>
	</Table>
</template>

