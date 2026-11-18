<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-18 16:45
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PingIcon from "@/Components/Utils/PingIcon.vue"
import AddProductsFromSupplierDialog from "@/Components/Procurement/AddProductsFromSupplierDialog.vue"
import CopyProductsToSupplierDialog from "@/Components/Procurement/CopyProductsToSupplierDialog.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCopy, faFileImport, faPlus } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { CopyProducts, CopyTarget } from "@/types/supplier-products-copy"

library.add(faCopy, faFileImport, faPlus)

const props = defineProps<{
	copyProducts: CopyProducts
	copyTargets?: CopyTarget[]
	selectedIds: number[]
	idsParameter: "org_supplier_products" | "supplier_products"
}>()

const emit = defineEmits<{
	(e: "copied"): void
}>()

const isCopyDialogVisible = ref(false)
const isAddFromSupplierDialogVisible = ref(false)
const isLoadingTargets = ref(false)
const copyDialog = ref<InstanceType<typeof CopyProductsToSupplierDialog> | null>(null)

function loadCopyTargets() {
	if (!props.copyTargets) {
		router.reload({
			only: ["copy_targets"],
			onStart: () => isLoadingTargets.value = true,
			onFinish: () => isLoadingTargets.value = false,
		})
	}
}

function openCopyDialog() {
	isCopyDialogVisible.value = true
	loadCopyTargets()
}

function openAddFromSupplierDialog() {
	isAddFromSupplierDialogVisible.value = true
	loadCopyTargets()
}

function createNewSupplier() {
	if (props.copyProducts.agents.length > 1) {
		openCopyDialog()
		return
	}

	copyDialog.value?.createSupplierWithSelectedProducts()
}
</script>

<template>
	<div class="contents">
		<Button type="tertiary" :label="ctrans('Add products from another supplier')" @click="openAddFromSupplierDialog">
			<template #icon>
				<FontAwesomeIcon icon="fal fa-file-import" class="text-green-600" fixed-width aria-hidden="true" />
			</template>
		</Button>
		<div class="relative">
			<Button type="tertiary" :label="ctrans('Add to another supplier')" :disabled="!selectedIds.length" @click="openCopyDialog">
				<template #icon>
					<FontAwesomeIcon icon="fal fa-copy" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
				</template>
			</Button>
			<div v-if="selectedIds.length && copyProducts.agents.length > 0" class="absolute top-0 right-0 text-[6px] rounded-full text-orange-500">
				<PingIcon />
			</div>
		</div>
		<div class="relative">
			<Button type="tertiary" :label="ctrans('New supplier with these products')" :disabled="!selectedIds.length" @click="createNewSupplier">
				<template #icon>
					<FontAwesomeIcon icon="fal fa-plus" class="text-green-600" fixed-width aria-hidden="true" />
				</template>
			</Button>
			<div v-if="selectedIds.length && copyProducts.agents.length > 0" class="absolute top-0 right-0 text-[6px] rounded-full text-orange-500">
				<PingIcon />
			</div>
		</div>

		<CopyProductsToSupplierDialog
			ref="copyDialog"
			v-model:visible="isCopyDialogVisible"
			:copyProducts="copyProducts"
			:copyTargets="copyTargets"
			:isLoadingTargets="isLoadingTargets"
			:selectedIds="selectedIds"
			:idsParameter="idsParameter"
			@copied="emit('copied')"
		/>

		<AddProductsFromSupplierDialog
			v-model:visible="isAddFromSupplierDialogVisible"
			:copyProducts="copyProducts"
			:sourceSuppliers="copyTargets"
			:isLoadingSuppliers="isLoadingTargets"
		/>
	</div>
</template>
