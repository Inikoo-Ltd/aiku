<script setup lang="ts">
import qs from "qs"
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import PillFilterBar from "@/Components/Utils/PillFilterBar.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"

interface SupplierPortalActivityRow {
	code: string
	name: string
	slug: string
	number_emails: number
	number_people: number
	number_visits: number
	last_visit_at: string | null
	last_ip: string | null
	last_country_code: string | null
	last_city: string | null
}

const props = defineProps<{
	data: {
		facets?: { state: { value: string; label: string; count: number }[] }
		active_facets?: { state: string | null }
	}
	tab?: string
}>()

const selectState = (value: string | null) => {
	const query = qs.parse(location.search.substring(1)) as Record<string, any>
	query.facets = { ...(query.facets ?? {}), state: value }
	delete query.page

	router.get(location.pathname + "?" + qs.stringify(query, { skipNulls: true, encodeValuesOnly: true }), {}, { preserveState: true, preserveScroll: true, replace: true })
}
</script>

<template>
	<div>
		<div v-if="data.facets" class="mx-4 mt-4">
			<PillFilterBar :label="ctrans('Visits')" :options="data.facets.state" :selected="data.active_facets?.state ?? null" @select="selectState" />
		</div>
		<Table :resource="data" :name="tab" class="mt-4">
			<template #cell(code)="{ item }: { item: SupplierPortalActivityRow }">
				<Link :href="route('grp.supply-chain.suppliers.share', [item.slug])" class="primaryLink whitespace-nowrap">{{ item.code }}</Link>
			</template>
			<template #cell(number_emails)="{ item }: { item: SupplierPortalActivityRow }">
				<span class="tabular-nums">{{ item.number_emails }}</span>
			</template>
			<template #cell(number_people)="{ item }: { item: SupplierPortalActivityRow }">
				<span class="tabular-nums">{{ item.number_people }}</span>
			</template>
			<template #cell(number_visits)="{ item }: { item: SupplierPortalActivityRow }">
				<span class="tabular-nums" :class="item.number_visits ? 'text-gray-800' : 'text-gray-400'">{{ item.number_visits }}</span>
			</template>
			<template #cell(last_visit_at)="{ item }: { item: SupplierPortalActivityRow }">
				<span class="whitespace-nowrap">{{ item.last_visit_at ? useFormatTime(item.last_visit_at, { formatTime: "hm" }) : "—" }}</span>
			</template>
			<template #cell(last_from)="{ item }: { item: SupplierPortalActivityRow }">
				<span v-if="item.last_ip" class="flex items-center gap-1.5 whitespace-nowrap">
					<img v-if="item.last_country_code" :src="`/flags/${item.last_country_code.toLowerCase()}.png`" class="h-3 w-4 object-cover" :alt="item.last_country_code" />
					<span v-if="item.last_city">{{ item.last_city }}</span>
					<span class="font-mono text-xs text-gray-400">{{ item.last_ip }}</span>
				</span>
				<span v-else class="text-gray-400">—</span>
			</template>
		</Table>
	</div>
</template>
