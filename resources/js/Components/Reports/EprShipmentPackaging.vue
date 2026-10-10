<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 10 Oct 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrashAlt, faPencil, faPlus } from "@fal"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Select from "primevue/select"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

library.add(faTrashAlt, faPencil, faPlus)

const props = defineProps<{
	data?: { id: number; code: string; name: string; material_category: string | null; weight_g: number | null; used: number; kg: number }[]
	organisationId: number
	materials: Record<string, string>
}>()

const locale = useLocaleStore()
const materialOptions = computed(() => Object.entries(props.materials).map(([value, label]) => ({ value, label })))

const form = ref<{ code: string; material_category: string | null; weight_g: number | null }>({ code: "", material_category: "paper_cardboard", weight_g: null })
const errors = ref<Record<string, string>>({})
const saving = ref(false)

const edit = (row: NonNullable<typeof props.data>[number]) => {
	form.value = { code: row.code, material_category: row.material_category ?? "paper_cardboard", weight_g: row.weight_g }
	errors.value = {}
}

const save = () => {
	saving.value = true
	router.post(route("grp.models.shipment_packaging.store", { organisation: props.organisationId }), form.value, {
		preserveScroll: true,
		onSuccess: () => {
			form.value = { code: "", material_category: form.value.material_category, weight_g: null }
			errors.value = {}
		},
		onError: (e) => (errors.value = e),
		onFinish: () => (saving.value = false),
	})
}

const remove = (id: number) => router.delete(route("grp.models.shipment_packaging.delete", { organisation: props.organisationId, orgStock: id }), { preserveScroll: true })

const totalKg = computed(() => (props.data ?? []).reduce((sum, row) => sum + row.kg, 0))
const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent] focus:!border-[--app-accent]"
</script>

<template>
	<div class="px-4 py-5 space-y-5 text-sm text-gray-700">
		<p class="max-w-2xl text-xs text-gray-500">
			{{ ctrans("Cartons, mailers and tape used to send parcels. The return counts what the warehouse books as used in the period, at the weight of one unit, as household shipment packaging.") }}
		</p>

		<div class="flex flex-wrap items-start gap-2">
			<div>
				<InputText v-model="form.code" :placeholder="ctrans('SKO code')" class="!w-40 !py-1 !text-xs" :class="fieldFocusClass" :invalid="!!errors.code" />
				<div v-if="errors.code" class="mt-1 text-xs text-red-600">{{ errors.code }}</div>
			</div>
			<Select v-model="form.material_category" :options="materialOptions" optionLabel="label" optionValue="value" class="!w-44" :class="fieldFocusClass" :pt="{ label: { class: '!py-1 !text-xs' } }" :aria-label="ctrans('Material')" />
			<div>
				<InputNumber v-model="form.weight_g" :minFractionDigits="0" :maxFractionDigits="3" suffix=" g" :placeholder="ctrans('Weight of one unit')" :pt="{ pcInputText: { root: { class: '!w-36 !py-1 !text-xs' } } }" :class="fieldFocusClass" :invalid="!!errors.weight_g" />
				<div v-if="errors.weight_g" class="mt-1 text-xs text-red-600">{{ errors.weight_g }}</div>
			</div>
			<Button type="tertiary" size="xs" :icon="['fal', 'plus']" :label="ctrans('Save')" :loading="saving" :disabled="!form.code || !form.weight_g" @click="save" />
		</div>

		<div v-if="!data" class="h-24 rounded-lg bg-gray-100 animate-pulse" />
		<div v-else-if="!data.length" class="rounded-lg border border-dashed border-gray-300 px-6 py-8 text-center text-gray-500">
			{{ ctrans("No SKO is marked as shipment packaging yet. Add the cartons and mailers above with what one of them weighs.") }}
		</div>
		<div v-else class="rounded-lg ring-1 ring-gray-200 bg-white overflow-x-auto">
			<table class="min-w-full text-xs">
				<thead class="text-gray-600">
					<tr class="text-left">
						<th class="px-4 py-2 font-medium">{{ ctrans("SKO") }}</th>
						<th class="px-2 py-2 font-medium">{{ ctrans("Material") }}</th>
						<th class="px-2 py-2 font-medium text-right">{{ ctrans("One unit") }}</th>
						<th class="px-2 py-2 font-medium text-right">{{ ctrans("Used") }}</th>
						<th class="px-2 py-2 font-medium text-right">{{ ctrans("kg") }}</th>
						<th class="px-4 py-2" />
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100 tabular-nums">
					<tr v-for="row in data" :key="row.id">
						<td class="px-4 py-2">
							<div class="font-medium">{{ row.code }}</div>
							<div class="text-gray-500 truncate max-w-xs">{{ row.name }}</div>
						</td>
						<td class="px-2 py-2">{{ row.material_category ? materials[row.material_category] : "—" }}</td>
						<td class="px-2 py-2 text-right">{{ row.weight_g == null ? "—" : `${locale.number(row.weight_g)} g` }}</td>
						<td class="px-2 py-2 text-right">{{ locale.number(Math.round(row.used)) }}</td>
						<td class="px-2 py-2 text-right">{{ locale.number(row.kg) }}</td>
						<td class="px-4 py-2 text-right whitespace-nowrap">
							<Button type="transparent" size="xs" :icon="['fal', 'pencil']" :tooltip="ctrans('Edit')" @click="edit(row)" />
							<Button type="transparent" size="xs" :icon="['fal', 'trash-alt']" :tooltip="ctrans('No longer shipment packaging')" @click="remove(row.id)" />
						</td>
					</tr>
				</tbody>
				<tfoot>
					<tr class="border-t border-gray-200 font-medium text-gray-900 tabular-nums">
						<td class="px-4 py-2" colspan="4">{{ ctrans("Total") }}</td>
						<td class="px-2 py-2 text-right">{{ locale.number(Math.round(totalKg)) }}</td>
						<td />
					</tr>
				</tfoot>
			</table>
		</div>
	</div>
</template>
