<!--
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFileSignature, faPlus, faPencil } from "@fal"

library.add(faFileSignature, faPlus, faPencil)

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	data: object
	supplier_counts: { accepted: number, outdated: number, not_accepted: number } | null
	can_edit: boolean
	draft_route: { name: string, parameters: Record<string, number> } | null
	store_route: { name: string, parameters: Record<string, number> } | null
}>()

const isStarting = ref(false)
const startError = ref<string | null>(null)

function startVersion() {
	if (!props.store_route) {
		return
	}
	router.post(route(props.store_route.name, props.store_route.parameters), {}, {
		onStart: () => { isStarting.value = true; startError.value = null },
		onFinish: () => { isStarting.value = false },
		onError: (errors) => { startError.value = Object.values(errors)[0] as string },
	})
}

const cards = [
	{ key: "accepted", label: "Accepted", class: "text-green-700" },
	{ key: "outdated", label: "Outdated", class: "text-amber-700" },
	{ key: "not_accepted", label: "Not accepted", class: "text-red-700" },
] as const
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #other>
			<Link v-if="draft_route" :href="route(draft_route.name, draft_route.parameters)">
				<Button type="tertiary" :label="can_edit ? ctrans('Continue the draft') : ctrans('See the draft')">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-pencil" class="text-gray-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</Link>
			<Button v-else-if="store_route" type="tertiary" :label="ctrans('New version')" :loading="isStarting" @click="startVersion">
				<template #icon>
					<FontAwesomeIcon icon="fal fa-plus" class="text-green-600" fixed-width aria-hidden="true" />
				</template>
			</Button>
		</template>
	</PageHeading>

	<p v-if="startError" class="px-4 pt-2 text-xs text-red-600">{{ startError }}</p>

	<div v-if="supplier_counts" class="grid grid-cols-3 gap-3 px-4 pt-4 md:max-w-2xl">
		<div v-for="card in cards" :key="card.key" class="rounded-lg border border-gray-200 bg-white p-3">
			<div class="text-xs text-gray-500">{{ ctrans(card.label) }}</div>
			<div class="text-2xl font-semibold tabular-nums" :class="card.class">{{ supplier_counts[card.key] }}</div>
			<div class="flex flex-wrap gap-x-2 text-xs text-gray-500">
				<Link :href="route('grp.supply-chain.suppliers.index', { 'elements[agreement]': card.key })" class="hover:underline">{{ ctrans("No agent") }}</Link>
				<Link :href="route('grp.supply-chain.agent_suppliers.index', { 'elements[agreement]': card.key })" class="hover:underline">{{ ctrans("Through agent") }}</Link>
			</div>
		</div>
	</div>
	<p v-else class="px-4 pt-4 text-sm text-gray-500">{{ ctrans("No version is published yet, so suppliers have nothing to accept.") }}</p>

	<Table :resource="data" class="mt-5">
		<template #cell(version)="{ item }">
			<Link :href="route('grp.supply-chain.supplier_agreement.show', { supplierAgreementVersion: item.id })" class="whitespace-nowrap font-medium hover:underline">v{{ item.version }}</Link>
		</template>
		<template #cell(state)="{ item }">
			<span class="whitespace-nowrap rounded-full border px-2 py-0.5 text-xs" :class="item.state.value === 'published' ? 'border-green-200 bg-green-50 text-green-700' : item.state.value === 'draft' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-gray-200 bg-gray-50 text-gray-500'">{{ item.state.label }}</span>
		</template>
		<template #cell(title)="{ item }">
			{{ item.title }}
			<div v-if="item.change_summary" class="text-xs text-gray-500">{{ item.change_summary }}</div>
		</template>
		<template #cell(requires_reacceptance)="{ item }">
			{{ item.requires_reacceptance ? ctrans("Yes") : ctrans("No, notice only") }}
		</template>
		<template #cell(published_at)="{ item }">
			<template v-if="item.published_at">
				{{ useFormatTime(item.published_at) }}
				<div v-if="item.published_by" class="text-xs text-gray-500">{{ item.published_by }}</div>
			</template>
		</template>
	</Table>
</template>
