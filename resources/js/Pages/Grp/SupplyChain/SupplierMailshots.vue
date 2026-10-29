<script setup lang="ts">
import { ref } from "vue"
import { Head, Link, useForm } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import MultiSelect from "primevue/multiselect"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faMailBulk, faPlus, faPersonDolly } from "@fal"

library.add(faMailBulk, faPlus, faPersonDolly)

interface SupplierMailshotRow {
	slug: string
	subject: string
	countries: string
	shop_code: string | null
	state: string
	state_label: string
	sent_at: string | null
	number_dispatched_emails: number
	number_dispatched_emails_state_opened: number
	number_dispatched_emails_state_clicked: number
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	data: object
	can_send: boolean
	store_route: { name: string; parameters: Record<string, unknown> }
	countries: { value: number; label: string; count: number }[]
	shops: { value: number; label: string }[]
	default_shop_id: number | null
}>()

const isOpen = ref(false)
const form = useForm({
	shop_id: props.default_shop_id,
	countries: [] as number[],
	subject: "",
})

const create = () => form.post(route(props.store_route.name, props.store_route.parameters))

const stateClass = (state: string) => (state === "sent" ? "text-green-700" : state === "sending" ? "text-amber-700" : "text-gray-500")
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #other>
			<Button v-if="can_send" type="tertiary" size="xs" :label="ctrans('New email')" @click="isOpen = true">
				<template #icon><FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" /></template>
			</Button>
		</template>
	</PageHeading>

	<Table :resource="data" class="mt-4">
		<template #cell(subject)="{ item }: { item: SupplierMailshotRow }">
			<Link :href="route('grp.supply-chain.supplier_mailshots.show', [item.slug])" class="primaryLink">{{ item.subject }}</Link>
		</template>
		<template #cell(state)="{ item }: { item: SupplierMailshotRow }">
			<span class="whitespace-nowrap" :class="stateClass(item.state)">{{ item.state_label }}</span>
		</template>
		<template #cell(sent_at)="{ item }: { item: SupplierMailshotRow }">
			<span class="whitespace-nowrap">{{ item.sent_at ? useFormatTime(item.sent_at) : "—" }}</span>
		</template>
		<template #cell(number_dispatched_emails)="{ item }: { item: SupplierMailshotRow }">
			<span class="tabular-nums">{{ item.number_dispatched_emails }}</span>
		</template>
		<template #cell(number_dispatched_emails_state_opened)="{ item }: { item: SupplierMailshotRow }">
			<span class="tabular-nums">{{ item.number_dispatched_emails_state_opened + item.number_dispatched_emails_state_clicked }}</span>
		</template>
		<template #cell(number_dispatched_emails_state_clicked)="{ item }: { item: SupplierMailshotRow }">
			<span class="tabular-nums">{{ item.number_dispatched_emails_state_clicked }}</span>
		</template>
	</Table>

	<Dialog v-model:visible="isOpen" modal dismissableMask :header="ctrans('New email to suppliers')" :style="{ width: '34rem', maxWidth: 'calc(100vw - 2rem)' }">
		<form class="space-y-4 text-sm text-gray-700" @submit.prevent="create">
			<label class="block space-y-1">
				<span class="text-xs font-semibold text-gray-600">{{ ctrans("Suppliers in") }}</span>
				<MultiSelect
					v-model="form.countries"
					:options="countries"
					optionLabel="label"
					optionValue="value"
					filter
					display="chip"
					class="w-full"
					:placeholder="ctrans('Choose countries')"
					:invalid="!!form.errors.countries">
					<template #option="{ option }">
						<span class="flex w-full justify-between gap-3">
							<span>{{ option.label }}</span>
							<span class="tabular-nums text-gray-400">{{ option.count }}</span>
						</span>
					</template>
				</MultiSelect>
				<span v-if="form.errors.countries" class="text-xs text-red-600">{{ form.errors.countries }}</span>
			</label>
			<label class="block space-y-1">
				<span class="text-xs font-semibold text-gray-600">{{ ctrans("Subject") }}</span>
				<InputText v-model="form.subject" class="w-full" :invalid="!!form.errors.subject" />
				<span v-if="form.errors.subject" class="text-xs text-red-600">{{ form.errors.subject }}</span>
			</label>
			<label class="block space-y-1">
				<span class="text-xs font-semibold text-gray-600">{{ ctrans("Send as") }}</span>
				<Select v-model="form.shop_id" :options="shops" optionLabel="label" optionValue="value" class="w-full" :invalid="!!form.errors.shop_id" />
				<span class="block text-xs text-gray-400">{{ ctrans("The email goes out with this shop's name and address.") }}</span>
				<span v-if="form.errors.shop_id" class="text-xs text-red-600">{{ form.errors.shop_id }}</span>
			</label>
			<div class="flex justify-end">
				<Button type="tertiary" size="xs" :label="ctrans('Write the email')" :loading="form.processing" :disabled="!form.countries.length || !form.subject" @click="create" />
			</div>
		</form>
	</Dialog>
</template>
