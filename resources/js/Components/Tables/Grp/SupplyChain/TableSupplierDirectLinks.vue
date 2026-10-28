<script setup lang="ts">
import { ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import qs from "qs"
import Table from "@/Components/Table/Table.vue"
import PillFilterBar from "@/Components/Utils/PillFilterBar.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBan } from "@fal"
import { RouteTarget } from "@/Components/Procurement/SupplierDirectLinkDialog.vue"

library.add(faBan)

interface SupplierDirectLinkRow {
	id: number
	reference: string
	route: RouteTarget
	created_at: string
	created_by: string | null
	person_name: string | null
	live: boolean
	expires_at: string | null
	revoked_at: string | null
	email: string | null
	number_views: number
	last_viewed_at: string | null
	last_visit_ip: string | null
	revoke_route: RouteTarget
}

const props = defineProps<{
	data: {
		facets?: { state: { value: string; label: string; count: number }[] }
		active_facets?: { state: string | null }
	}
	tab: string
	canStop: boolean
}>()

const selectState = (value: string | null) => {
	const query = qs.parse(location.search.substring(1)) as Record<string, any>
	query[`${props.tab}_facets`] = { ...(query[`${props.tab}_facets`] ?? {}), state: value }
	delete query[`${props.tab}Page`]

	router.get(location.pathname + "?" + qs.stringify(query, { skipNulls: true, encodeValuesOnly: true }), {}, { preserveState: true, preserveScroll: true, replace: true })
}

const confirming = ref<number | null>(null)

const stop = (link: SupplierDirectLinkRow) => {
	confirming.value = null
	router.post(route(link.revoke_route.name, link.revoke_route.parameters), {}, { preserveScroll: true, only: ["direct_links"] })
}
</script>

<template>
	<div>
	<div v-if="data.facets" class="mx-4 mt-4">
		<PillFilterBar :label="ctrans('State')" :options="data.facets.state" :selected="data.active_facets?.state ?? null" @select="selectState" />
	</div>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(reference)="{ item }: { item: SupplierDirectLinkRow }">
			<Link :href="route(item.route.name, item.route.parameters)" class="primaryLink whitespace-nowrap font-mono">{{ item.reference }}</Link>
		</template>
		<template #cell(created_at)="{ item }: { item: SupplierDirectLinkRow }">
			<span class="whitespace-nowrap">{{ useFormatTime(item.created_at) }}</span>
			<span v-if="item.created_by" class="text-gray-400"> · {{ item.created_by }}</span>
		</template>
		<template #cell(state)="{ item }: { item: SupplierDirectLinkRow }">
			<span v-if="item.revoked_at" class="whitespace-nowrap text-red-600">{{ ctrans("Stopped") }}</span>
			<span v-else-if="!item.live" class="whitespace-nowrap text-gray-400">{{ ctrans("Expired") }}</span>
			<span v-else class="whitespace-nowrap text-green-700">{{ ctrans("Active") }}</span>
		</template>
		<template #cell(expires_at)="{ item }: { item: SupplierDirectLinkRow }">
			<span class="whitespace-nowrap">{{ item.expires_at ? useFormatTime(item.expires_at) : ctrans("Forever") }}</span>
		</template>
		<template #cell(number_views)="{ item }: { item: SupplierDirectLinkRow }">
			<span class="tabular-nums">{{ item.number_views }}</span>
		</template>
		<template #cell(last_viewed_at)="{ item }: { item: SupplierDirectLinkRow }">
			<span class="whitespace-nowrap">{{ item.last_viewed_at ? useFormatTime(item.last_viewed_at) : "—" }}</span>
		</template>
		<template #cell(last_visit_ip)="{ item }: { item: SupplierDirectLinkRow }">
			<span class="font-mono text-xs">{{ item.last_visit_ip ?? "—" }}</span>
		</template>
		<template #cell(actions)="{ item }: { item: SupplierDirectLinkRow }">
			<div v-if="confirming === item.id" class="flex items-center justify-end gap-1 whitespace-nowrap">
				<span class="text-gray-600">{{ ctrans("Stop this link?") }}</span>
				<Button type="red" size="xxs" :label="ctrans('Stop')" @click="stop(item)" />
				<Button type="tertiary" size="xxs" :label="ctrans('Cancel')" @click="confirming = null" />
			</div>
			<Button v-else-if="item.live && canStop" type="tertiary" size="xxs" :label="ctrans('Stop')" @click="confirming = item.id">
				<template #icon><FontAwesomeIcon icon="fal fa-ban" class="text-red-500" fixed-width aria-hidden="true" /></template>
			</Button>
		</template>
	</Table>
	</div>
</template>
