<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFileExcel } from "@fal"
library.add(faFileExcel)

defineProps<{
	data: object
	name?: string
	tab?: string
}>()
</script>

<template>
	<Table :resource="data" :name="name ?? tab">
		<template #cell(reference)="{ item }">
			<Link
				:href="route(item.route.name, item.route.parameters)"
				class="whitespace-nowrap font-medium hover:text-[var(--app-accent)] hover:underline"
				:class="item.is_void ? 'text-gray-400 line-through' : 'text-gray-700'">
				{{ item.reference }}
			</Link>
		</template>
		<template #cell(state)="{ item }">
			<span class="whitespace-nowrap" :class="item.is_void ? 'text-red-600' : ''">{{ item.state }}</span>
		</template>
		<template #cell(date)="{ item }">
			<span class="whitespace-nowrap">{{ item.date }}</span>
		</template>
		<template #cell(customs_invoices)="{ item }">
			<div class="flex flex-wrap gap-x-3 gap-y-1">
				<a
					v-for="customsInvoice in item.customs_invoices"
					:key="customsInvoice.reference"
					:href="customsInvoice.download_url"
					download
					:title="customsInvoice.label"
					class="inline-flex items-center gap-1 whitespace-nowrap hover:text-[var(--app-accent)] hover:underline"
					:class="item.is_void ? 'text-gray-400 line-through' : 'text-gray-700'">
					<FontAwesomeIcon :icon="faFileExcel" fixed-width aria-hidden="true" />
					{{ customsInvoice.reference }}
				</a>
			</div>
		</template>
	</Table>
</template>
