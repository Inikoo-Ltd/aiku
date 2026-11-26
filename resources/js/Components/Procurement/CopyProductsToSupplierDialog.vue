<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-18 16:40
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import Checkbox from "primevue/checkbox"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SupplierSelectOption from "@/Components/SupplyChain/SupplierSelectOption.vue"
import { supplierOptionFilterFields } from "@/types/supplier"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faPlus, faTimes } from "@fal"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { CopyProducts, CopyTarget } from "@/types/supplier-products-copy"

library.add(faCheck, faPlus, faTimes)

const props = defineProps<{
	copyProducts: CopyProducts
	copyTargets?: CopyTarget[]
	isLoadingTargets?: boolean
	selectedIds: number[]
	idsParameter: "org_supplier_products" | "supplier_products"
}>()

const emit = defineEmits<{
	(e: "copied"): void
}>()

const visible = defineModel<boolean>("visible", { default: false })

const isCopying = ref(false)
const targetSupplierId = ref<number | null>(null)
const supplierSearch = ref("")
const isMakePreferred = ref(false)
const copyError = ref<string | null>(null)
const targetAgentId = ref<number | null>(props.copyProducts.agent_id)
const targetAgent = computed(() => props.copyProducts.agents.find((agent) => agent.id === targetAgentId.value))
const targetSuppliers = computed(() => (props.copyTargets ?? []).filter((supplier) => supplier.agent_id === targetAgentId.value))
const targetSupplier = computed(() => targetSuppliers.value.find((supplier) => supplier.id === targetSupplierId.value))

watch(visible, (isVisible) => {
	if (isVisible) {
		targetSupplierId.value = null
		isMakePreferred.value = false
		copyError.value = null
	}
})

function copySelectedProducts() {
	if (!targetSupplierId.value) {
		return
	}

	router.post(
		route(props.copyProducts.copy_route.name, [...Object.values(props.copyProducts.copy_route.parameters ?? {}), targetSupplierId.value]),
		{ [props.idsParameter]: props.selectedIds, make_preferred: props.copyProducts.can_make_preferred && isMakePreferred.value },
		{
			preserveScroll: true,
			onStart: () => isCopying.value = true,
			onFinish: () => isCopying.value = false,
			onError: (errors) => copyError.value = Object.values(errors)[0] as string,
			onSuccess: () => {
				visible.value = false
				emit("copied")
			},
		}
	)
}

function createSupplierWithSelectedProducts() {
	if (!targetAgent.value) {
		return
	}

	router.get(
		route(targetAgent.value.create_route.name, targetAgent.value.create_route.parameters),
		{ [props.idsParameter]: props.selectedIds }
	)
}

defineExpose({ createSupplierWithSelectedProducts })
</script>

<template>
	<Dialog
		v-model:visible="visible"
		modal
		:header="ctrans('Add to another supplier')"
		:style="{ width: '30rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
	>
		<div class="flex flex-col gap-2">
			<p class="text-sm text-gray-600">
				{{ ctrans(":count products stay with this supplier and are added to the other one, linked to the same SKOs. Products it already has are skipped.", { count: String(selectedIds.length) }) }}
			</p>
			<template v-if="copyProducts.agents.length > 1">
				<label for="copy-products-target-agent" class="font-medium text-gray-700">
					{{ ctrans("Agent") }}
				</label>
				<Select
					v-model="targetAgentId"
					inputId="copy-products-target-agent"
					:options="copyProducts.agents"
					optionLabel="label"
					optionValue="id"
					fluid
					@change="targetSupplierId = null"
				/>
				<p v-if="targetAgentId !== copyProducts.agent_id" class="text-sm text-amber-600">
					{{ ctrans("Costs are converted to the supplier's currency at today's rate. Review them after adding.") }}
				</p>
			</template>
			<label for="copy-products-target-supplier" class="font-medium text-gray-700">
				{{ ctrans("Supplier") }} <span class="text-red-500">*</span>
			</label>
			<Select
				v-model="targetSupplierId"
				inputId="copy-products-target-supplier"
				:options="targetSuppliers"
				optionLabel="label"
				optionValue="id"
				filter
				autoFilterFocus
				:filterFields="supplierOptionFilterFields"
				:filterPlaceholder="ctrans('Code, name, contact or product code')"
				@filter="(event) => supplierSearch = event.value"
				@hide="supplierSearch = ''"
				fluid
				:loading="isLoadingTargets"
				:placeholder="ctrans('Select a supplier')"
				:emptyMessage="ctrans('No other supplier')"
			>
				<template #option="{ option }">
					<SupplierSelectOption :supplier="option" :search="supplierSearch">
						<template #aside>
							<span class="text-xs tabular-nums text-gray-500">{{ ctransChoice(":count product|:count products", option.number_products, { count: String(option.number_products) }) }}</span>
						</template>
					</SupplierSelectOption>
				</template>
			</Select>
			<label v-if="copyProducts.can_make_preferred" for="copy-products-make-preferred" class="mt-2 flex cursor-pointer items-start gap-2">
				<Checkbox v-model="isMakePreferred" inputId="copy-products-make-preferred" binary class="mt-0.5" />
				<span class="text-sm">
					<span class="font-medium text-gray-700">{{ ctrans("Make :supplier as preferred supplier", { supplier: targetSupplier?.label ?? ctrans("this supplier") }) }}</span>
					<span class="block text-xs text-gray-500">{{ ctrans("Purchase orders for these SKOs will go to this supplier.") }}</span>
				</span>
			</label>
			<p v-if="copyError" class="text-sm text-red-500">{{ copyError }}</p>
		</div>

		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="tertiary" class="shrink-0" :label="ctrans('New supplier')" :disabled="!targetAgent" @click="createSupplierWithSelectedProducts">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-plus" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="primary" full :label="ctrans('Add')" :disabled="!targetSupplierId" :loading="isCopying" @click="copySelectedProducts">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
