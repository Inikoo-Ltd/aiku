<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { useLocaleStore } from '@/Stores/locale'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

const props = defineProps<{
	data: {}
	tab?: string
}>()

const locale = useLocaleStore()

function bayRoute(bay: bay) {
	switch (route().current()) {
		case 'grp.org.warehouses.show.dispatching.picked_bays.index':
			return route(
				'grp.org.warehouses.show.dispatching.picked_bays.show',
				[route().params['organisation'], route().params['warehouse'], bay.slug])
		default:
			return route(
				'grp.org.warehouses.show.dispatching.picked_bays.index',
				[bay.organisation_slug, bay.slug])
	}
}
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<!-- Column: Code -->
		<template #cell(code)="{ item: bay }">
			<Link :href="bayRoute(bay)" class="primaryLink">
				{{ bay['code'] }}
			</Link>
		</template>
	</Table>
</template>
