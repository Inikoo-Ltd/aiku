<script setup lang="ts">
import { ref } from "vue"
import { Head, useForm } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import InputNumber from "primevue/inputnumber"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TableSupplierSubmissions from "@/Components/Tables/Grp/SupplyChain/TableSupplierSubmissions.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInboxIn, faCog, faCheck } from "@fal"

library.add(faInboxIn, faCog, faCheck)

const props = defineProps<{
	pageHead: object
	title: string
	data: object
	portalSettings: {
		can: boolean
		values: { start: string; target_days: number; minimum_orders: number }
		starts: { value: string; label: string }[]
		route: { name: string; parameters: Record<string, number> }
	}
}>()

const isOpen = ref(false)
const form = useForm({ ...props.portalSettings.values })

const save = () =>
	form.patch(route(props.portalSettings.route.name, props.portalSettings.route.parameters), {
		preserveScroll: true,
		onSuccess: () => (isOpen.value = false),
	})
</script>

<template>
	<div>
		<Head :title="capitalize(title)" />
		<PageHeading :data="pageHead">
			<template #other>
				<Button type="tertiary" size="xs" :label="ctrans('Supplier page settings')" @click="isOpen = true">
					<template #icon><FontAwesomeIcon icon="fal fa-cog" fixed-width aria-hidden="true" /></template>
				</Button>
			</template>
		</PageHeading>
		<TableSupplierSubmissions :data="data" />

		<Dialog v-model:visible="isOpen" modal dismissableMask :header="ctrans('Production time to CHO')" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }">
			<div class="space-y-3 text-sm">
				<p class="text-xs text-gray-500">{{ ctrans("How the production time card on every supplier page is measured. Suppliers are ranked against the others in their country.") }}</p>
				<label class="flex flex-col gap-1">
					<span class="font-medium text-gray-700">{{ ctrans("Count the days from") }}</span>
					<Select v-model="form.start" :options="portalSettings.starts" optionLabel="label" optionValue="value" :disabled="!portalSettings.can" />
					<span class="text-xs text-gray-400">{{ ctrans("to the clean hand over (CHO) of the order") }}</span>
				</label>
				<label class="flex flex-col gap-1">
					<span class="font-medium text-gray-700">{{ ctrans("Target, in days") }}</span>
					<InputNumber v-model="form.target_days" :min="1" :max="365" :disabled="!portalSettings.can" />
				</label>
				<label class="flex flex-col gap-1">
					<span class="font-medium text-gray-700">{{ ctrans("Orders needed to be ranked") }}</span>
					<InputNumber v-model="form.minimum_orders" :min="1" :max="100" :disabled="!portalSettings.can" />
					<span class="text-xs text-gray-400">{{ ctrans("Orders handed over in the last 12 months with both dates") }}</span>
				</label>
				<div v-if="Object.keys(form.errors).length" class="rounded border border-red-200 bg-red-50 p-2 text-xs text-red-700">
					<p v-for="(error, field) in form.errors" :key="field">{{ error }}</p>
				</div>
				<div v-if="portalSettings.can" class="flex justify-end">
					<Button type="tertiary" size="xs" :label="ctrans('Save')" :loading="form.processing" @click="save">
						<template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
					</Button>
				</div>
				<p v-else class="text-xs text-gray-500">{{ ctrans("Only supply chain editors can change this.") }}</p>
			</div>
		</Dialog>
	</div>
</template>
