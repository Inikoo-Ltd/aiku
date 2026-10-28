<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import qs from "qs"
import Table from "@/Components/Table/Table.vue"
import PillFilterBar from "@/Components/Utils/PillFilterBar.vue"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus } from "@fal"
import { RouteTarget } from "@/Components/Procurement/SupplierDirectLinkDialog.vue"

library.add(faPlus)

interface SupplierPortalContactRow {
	id: number
	email: string
	name: string | null
	revoked_at: string | null
	last_link_sent_at: string | null
	number_links_sent: number
	number_visits: number
	last_visit_at: string | null
	last_visit_ip: string | null
}

const props = defineProps<{
	data: {
		facets?: { state: { value: string; label: string; count: number }[] }
		active_facets?: { state: string | null }
	}
	tab: string
	canAdd: boolean
	emailRoute: RouteTarget
}>()

const selectState = (value: string | null) => {
	const query = qs.parse(location.search.substring(1)) as Record<string, any>
	query[`${props.tab}_facets`] = { ...(query[`${props.tab}_facets`] ?? {}), state: value }
	delete query[`${props.tab}Page`]

	router.get(location.pathname + "?" + qs.stringify(query, { skipNulls: true, encodeValuesOnly: true }), {}, { preserveState: true, preserveScroll: true, replace: true })
}

const isAdding = ref(false)
const newEmail = ref("")
const isSaving = ref(false)
const emailError = ref<string | null>(null)

const addEmail = () => {
	isSaving.value = true
	emailError.value = null
	router.post(route(props.emailRoute.name, props.emailRoute.parameters), { email: newEmail.value }, {
		preserveScroll: true,
		only: ["self_log_in"],
		onSuccess: () => {
			newEmail.value = ""
			isAdding.value = false
		},
		onError: (errors) => (emailError.value = Object.values(errors).join(" ")),
		onFinish: () => (isSaving.value = false),
	})
}
</script>

<template>
	<div>
	<div class="mx-4 mt-4 flex flex-wrap items-center justify-between gap-2">
		<PillFilterBar v-if="data.facets" :label="ctrans('State')" :options="data.facets.state" :selected="data.active_facets?.state ?? null" @select="selectState" />
		<Button v-if="canAdd" type="tertiary" size="xs" :label="ctrans('Email')" @click="isAdding = true">
			<template #icon><FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" /></template>
		</Button>
	</div>

	<Dialog v-model:visible="isAdding" modal dismissableMask :header="ctrans('Add email')" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }">
		<form class="space-y-3 text-sm" @submit.prevent="addEmail">
			<p class="text-xs text-gray-500">{{ ctrans("Saved in the supplier's other emails. This email can log in by itself straight away.") }}</p>
			<InputText v-model="newEmail" type="email" class="w-full text-sm" :placeholder="ctrans('Email')" :aria-label="ctrans('Email')" :invalid="!!emailError" autofocus @input="emailError = null" />
			<p v-if="emailError" class="text-xs text-red-600">{{ emailError }}</p>
			<div class="flex justify-end">
				<Button type="tertiary" size="xs" :label="ctrans('Add')" :loading="isSaving" :disabled="!newEmail" @click="addEmail" />
			</div>
		</form>
	</Dialog>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(name)="{ item }: { item: SupplierPortalContactRow }">
			{{ item.name ?? "—" }}
		</template>
		<template #cell(state)="{ item }: { item: SupplierPortalContactRow }">
			<span v-if="item.revoked_at" class="whitespace-nowrap text-red-600">{{ ctrans("Stopped") }}</span>
			<span v-else class="whitespace-nowrap text-green-700">{{ ctrans("Active") }}</span>
		</template>
		<template #cell(number_links_sent)="{ item }: { item: SupplierPortalContactRow }">
			<span class="tabular-nums">{{ item.number_links_sent }}</span>
		</template>
		<template #cell(last_link_sent_at)="{ item }: { item: SupplierPortalContactRow }">
			<span class="whitespace-nowrap">{{ item.last_link_sent_at ? useFormatTime(item.last_link_sent_at) : "—" }}</span>
		</template>
		<template #cell(number_visits)="{ item }: { item: SupplierPortalContactRow }">
			<span class="tabular-nums">{{ item.number_visits }}</span>
		</template>
		<template #cell(last_visit_at)="{ item }: { item: SupplierPortalContactRow }">
			<span class="whitespace-nowrap">{{ item.last_visit_at ? useFormatTime(item.last_visit_at) : "—" }}</span>
		</template>
		<template #cell(last_visit_ip)="{ item }: { item: SupplierPortalContactRow }">
			<span class="font-mono text-xs">{{ item.last_visit_ip ?? "—" }}</span>
		</template>
	</Table>
	</div>
</template>
