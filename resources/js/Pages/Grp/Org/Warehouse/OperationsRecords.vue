<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { capitalize } from "@/Composables/capitalize"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTruckLoading } from "@fal"

library.add(faTruckLoading)

type RouteLink = { name: string; parameters: Record<string, string> }

defineProps<{
	title: string
	pageHead: object
	data: object
}>()
</script>

<template>
	<div>
		<Head :title="capitalize(title)" />
		<PageHeading :data="pageHead" />
		<Table :resource="data">
			<template #cell(reference)="{ item }">
				<Link :href="route((item.route as RouteLink).name, (item.route as RouteLink).parameters)" class="primaryLink">{{ item.reference }}</Link>
			</template>
			<template #cell(code)="{ item }">
				<Link :href="route((item.route as RouteLink).name, (item.route as RouteLink).parameters)" class="primaryLink">{{ item.code }}</Link>
			</template>
			<template #cell(org_stock_code)="{ item }">
				<Link v-if="item.org_stock_route" :href="route((item.org_stock_route as RouteLink).name, (item.org_stock_route as RouteLink).parameters)" class="secondaryLink">{{ item.org_stock_code }}</Link>
			</template>
		</Table>
	</div>
</template>
