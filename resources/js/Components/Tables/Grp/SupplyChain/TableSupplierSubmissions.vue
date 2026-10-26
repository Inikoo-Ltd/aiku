<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { useFormatTime } from "@/Composables/useFormatTime"

interface SupplierSubmissionRow {
	id: number
	submitted_at: string | null
	supplier_code: string
	supplier_name: string
	type_label: string
	state: string
	state_label: string
	summary: string
}

defineProps<{
	data: object
	tab?: string
}>()

const stateClass: Record<string, string> = {
	received: "text-gray-800 font-medium",
	under_review: "text-gray-700",
	more_information_requested: "text-amber-700",
	accepted: "text-green-700",
	not_proceeding: "text-red-600",
}
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(submitted_at)="{ item }: { item: SupplierSubmissionRow }">
			<Link :href="route('grp.supply-chain.supplier_submissions.show', [item.id])" class="primaryLink whitespace-nowrap">
				{{ item.submitted_at ? useFormatTime(item.submitted_at) : "—" }}
			</Link>
		</template>
		<template #cell(supplier_code)="{ item }: { item: SupplierSubmissionRow }">
			<span class="whitespace-nowrap" :title="item.supplier_name">{{ item.supplier_code }}</span>
		</template>
		<template #cell(type)="{ item }: { item: SupplierSubmissionRow }">
			{{ item.type_label }}
		</template>
		<template #cell(state)="{ item }: { item: SupplierSubmissionRow }">
			<span class="whitespace-nowrap" :class="stateClass[item.state]">{{ item.state_label }}</span>
		</template>
	</Table>
</template>
