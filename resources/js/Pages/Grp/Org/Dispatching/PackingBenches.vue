<script setup lang="ts">
import { ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faTrashAlt } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"

defineProps<{
	pageHead: {}
	title: string
	canEdit: boolean
	data: {}
}>()

const deletingId = ref<number | null>(null)
const deleteError = ref<{ id: number; message: string } | null>(null)

const editRoute = (bench: { slug: string }) => {
	const params = route().params
	return route("grp.org.warehouses.show.dispatching.packing_benches.edit", [params.organisation, params.warehouse, bench.slug])
}

const deleteBench = (bench: { id: number; code: string }) => {
	if (!window.confirm(ctrans("Delete packing bench :code? Orders already packed on it keep showing it.", { code: bench.code }))) {
		return
	}
	router.delete(route("grp.models.packing_benches.delete", { packingBench: bench.id }), {
		preserveScroll: true,
		onStart: () => {
			deletingId.value = bench.id
			deleteError.value = null
		},
		onError: (errors) => {
			deleteError.value = { id: bench.id, message: Object.values(errors)[0] ?? ctrans("Could not delete it") }
		},
		onFinish: () => {
			deletingId.value = null
		},
	})
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />
	<Table :resource="data" class="mt-5">
		<template #cell(code)="{ item: bench }">
			<Link v-if="canEdit" :href="editRoute(bench)" class="primaryLink whitespace-nowrap">{{ bench.code }}</Link>
			<span v-else class="whitespace-nowrap">{{ bench.code }}</span>
		</template>

		<template #cell(status)="{ item: bench }">
			<FontAwesomeIcon :icon="bench.status ? faCheck : faTimes" :class="bench.status ? 'text-green-600' : 'text-gray-400'" fixed-width aria-hidden="true" />
		</template>

		<template #cell(actions)="{ item: bench }">
			<div v-if="canEdit" class="flex items-center gap-2">
				<Button type="tertiary" size="xs" :label="ctrans('Delete')" :loading="deletingId === bench.id" @click="deleteBench(bench)">
					<template #icon>
						<FontAwesomeIcon :icon="faTrashAlt" class="text-amber-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<span v-if="deleteError?.id === bench.id" class="text-xs text-red-600">{{ deleteError.message }}</span>
			</div>
		</template>
	</Table>
</template>
