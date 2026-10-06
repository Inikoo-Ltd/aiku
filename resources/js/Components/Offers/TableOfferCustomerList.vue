<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { inject } from "vue"
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"

defineProps<{
	data?: TableTS
	tab?: string
}>()

const locale = inject("locale", aikuLocaleStructure)

type RouteData = { name: string; parameters: Record<string, string | number> }
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(customer_name)="{ item }">
			<Link :href="route((item.customer_route as RouteData).name, (item.customer_route as RouteData).parameters)" class="primaryLink">
				{{ item.customer_name }}
			</Link>
			<span class="ml-1 text-xs text-gray-500">{{ item.customer_reference }}</span>
		</template>

		<template #cell(code)="{ item }">
			<span class="font-mono">{{ item.code }}</span>
		</template>

		<template #cell(order_reference)="{ item }">
			<Link
				v-if="item.order_route"
				:href="route((item.order_route as RouteData).name, (item.order_route as RouteData).parameters)"
				class="primaryLink">
				{{ item.order_reference }}
			</Link>
			<span v-else class="text-gray-400">{{ ctrans("Not used yet") }}</span>
		</template>

		<template #cell(order_date)="{ item }">
			<span v-if="item.order_date">
				{{ useFormatTime(item.order_date, { localeCode: locale.language.code }) }}
			</span>
		</template>
	</Table>
</template>
