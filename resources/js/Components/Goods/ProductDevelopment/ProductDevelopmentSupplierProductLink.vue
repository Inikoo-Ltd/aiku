<!--
  - Author Louis Perez
  - Created on 16-10-2026-23h-25m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import ProductDevelopmentRemoteSelect from "@/Components/Goods/ProductDevelopment/ProductDevelopmentRemoteSelect.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import type { ChecklistSku } from "@/Components/Goods/ProductDevelopment/productDevelopmentChecklist"

const props = defineProps<{
    linkRoute: string
    searchRoute: routeType | null
    currency: string | null
}>()

const visible = ref(false)
const sku = ref<ChecklistSku | null>(null)
const mode = ref<"existing" | "new">("new")
const supplierProductId = ref<number | null>(null)
const code = ref("")
const cost = ref<number | null>(null)
const unitsPerPack = ref<number | null>(1)
const unitsPerCarton = ref<number | null>(null)
const saving = ref(false)
const errors = ref<Record<string, string>>({})

const modes = computed(() => [
    { value: "new", label: ctrans("Create new") },
    { value: "existing", label: ctrans("Link existing") },
])

const canSubmit = computed(() => {
    if (saving.value) {
        return false
    }
    if (mode.value === "existing") {
        return !!supplierProductId.value
    }
    return code.value.trim() !== "" && cost.value !== null && !!unitsPerPack.value && !!unitsPerCarton.value
})

function open(target: ChecklistSku) {
    sku.value = target
    mode.value = "new"
    supplierProductId.value = null
    code.value = target.code
    cost.value = null
    unitsPerPack.value = 1
    unitsPerCarton.value = null
    errors.value = {}
    visible.value = true
}

function submit() {
    if (!sku.value || !canSubmit.value) {
        return
    }
    const payload = mode.value === "existing"
        ? { supplier_product_id: supplierProductId.value }
        : { code: code.value.trim(), cost: cost.value, units_per_pack: unitsPerPack.value, units_per_carton: unitsPerCarton.value }

    router.patch(route(props.linkRoute, { productDevelopmentItem: sku.value.id }), payload, {
        preserveScroll: true,
        onStart: () => saving.value = true,
        onFinish: () => saving.value = false,
        onError: (validationErrors) => errors.value = validationErrors,
        onSuccess: () => visible.value = false,
    })
}

defineExpose({ open })
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="ctrans('Supplier product for :sku', { sku: sku?.code ?? '' })" class="w-[32rem] max-w-[calc(100vw-2rem)]" :pt="{ content: { class: 'space-y-4' } }">
        <SegmentedToggle v-if="searchRoute" v-model="mode" :options="modes" />

        <div v-if="mode === 'existing' && searchRoute" class="space-y-1">
            <label class="text-xs text-gray-500">{{ ctrans("Supplier product") }}</label>
            <ProductDevelopmentRemoteSelect v-model="supplierProductId" :searchRoute="searchRoute" :placeholder="ctrans('Search by code or name')" />
        </div>

        <div v-else class="grid gap-3 sm:grid-cols-2">
            <div class="space-y-1 sm:col-span-2">
                <label class="text-xs text-gray-500">{{ ctrans("Supplier code") }}</label>
                <InputText v-model="code" size="small" class="w-full" :invalid="!!errors.code" />
            </div>
            <div class="space-y-1">
                <label class="text-xs text-gray-500">{{ ctrans("Unit cost") }}</label>
                <div class="flex items-center gap-2">
                    <InputNumber v-model="cost" :min="0" :maxFractionDigits="4" :useGrouping="false" size="small" class="min-w-0 flex-1" inputClass="w-full" :invalid="!!errors.cost" />
                    <span v-if="currency" class="text-sm text-gray-500">{{ currency }}</span>
                </div>
            </div>
            <div class="space-y-1">
                <label class="text-xs text-gray-500">{{ ctrans("Units per pack") }}</label>
                <InputNumber v-model="unitsPerPack" :min="1" :useGrouping="false" size="small" class="w-full" inputClass="w-full" :invalid="!!errors.units_per_pack" />
            </div>
            <div class="space-y-1">
                <label class="text-xs text-gray-500">{{ ctrans("Units per carton") }}</label>
                <InputNumber v-model="unitsPerCarton" :min="1" :useGrouping="false" size="small" class="w-full" inputClass="w-full" :invalid="!!errors.units_per_carton" />
            </div>
        </div>

        <p v-if="Object.keys(errors).length" class="text-xs text-red-600">{{ Object.values(errors)[0] }}</p>

        <template #footer>
            <Button type="negative" size="xs" :label="ctrans('Cancel')" @click="visible = false" />
            <Button type="primary" size="xs" icon="fas fa-save" :label="mode === 'existing' ? ctrans('Link this supplier product') : ctrans('Create and link')" :loading="saving" :disabled="!canSubmit" class="disabled:cursor-not-allowed disabled:opacity-50" @click="submit" />
        </template>
    </Dialog>
</template>
