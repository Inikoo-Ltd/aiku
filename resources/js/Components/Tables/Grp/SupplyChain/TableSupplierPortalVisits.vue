<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { useFormatTime } from "@/Composables/useFormatTime"

interface SupplierPortalVisitRow {
	id: number
	created_at: string
	ip: string | null
	user_agent: string | null
	country_code: string | null
	city: string | null
	reference: string
}

defineProps<{
	data: object
	tab: string
}>()

const device = (userAgent: string | null) => {
	if (!userAgent) {
		return "—"
	}
	const system = ["iPhone", "iPad", "Android", "Windows", "Mac OS", "Linux"].find((name) => userAgent.includes(name)) ?? ""
	const browser = ["Edg", "OPR", "SamsungBrowser", "Chrome", "Firefox", "Safari"].find((name) => userAgent.includes(name)) ?? ""
	const names: Record<string, string> = { Edg: "Edge", OPR: "Opera", SamsungBrowser: "Samsung Internet", "Mac OS": "Mac" }

	return [names[browser] ?? browser, names[system] ?? system].filter(Boolean).join(" · ") || userAgent.slice(0, 40)
}
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-4">
		<template #cell(created_at)="{ item }: { item: SupplierPortalVisitRow }">
			<span class="whitespace-nowrap">{{ useFormatTime(item.created_at, { formatTime: "hm" }) }}</span>
		</template>
		<template #cell(from)="{ item }: { item: SupplierPortalVisitRow }">
			<span class="flex items-center gap-1.5 whitespace-nowrap">
				<img v-if="item.country_code" :src="`/flags/${item.country_code.toLowerCase()}.png`" class="h-3 w-4 object-cover" :alt="item.country_code" />
				<span>{{ item.city ?? (item.country_code ? "" : "—") }}</span>
			</span>
		</template>
		<template #cell(ip)="{ item }: { item: SupplierPortalVisitRow }">
			<span class="whitespace-nowrap font-mono text-xs text-gray-500">{{ item.ip ?? "—" }}</span>
		</template>
		<template #cell(device)="{ item }: { item: SupplierPortalVisitRow }">
			<span class="whitespace-nowrap" :title="item.user_agent ?? undefined">{{ device(item.user_agent) }}</span>
		</template>
		<template #cell(reference)="{ item }: { item: SupplierPortalVisitRow }">
			<span class="whitespace-nowrap font-mono text-xs">{{ item.reference }}</span>
		</template>
	</Table>
</template>
