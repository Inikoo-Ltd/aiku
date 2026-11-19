<!--
* Author: Vika Aqordi
* Created on: 2026-11-18 10:00
* Github: https://github.com/aqordeon
* Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref } from "vue"
import axios from "axios"
import Select from "primevue/select"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faTimes, faExclamationTriangle, faArrowRight } from "@fal"
import Icon from "@/Components/Icon.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { SupplierProductStateOption, SupplierProductStateRow, isDiscontinuedState, stateSelectPt } from "@/Composables/useSupplierProductState"

library.add(faCheck, faTimes, faExclamationTriangle, faArrowRight)

const props = defineProps<{
    selectedIds: number[]
    idsParameter: "supplier_products" | "org_supplier_products"
    rows: SupplierProductStateRow[]
    stateOptions: SupplierProductStateOption[]
}>()

const emits = defineEmits<{
    (e: "saved"): void
}>()

const isDialogOpen = ref(false)
const bulkState = ref<string | null>(null)
const discontinuedReason = ref("")
const isSaving = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)

const previewRows = computed(() => props.rows.filter((row) => props.selectedIds.includes(row.id)))
const hiddenCount = computed(() => props.selectedIds.length - previewRows.value.length)
const newOption = computed(() => props.stateOptions.find((option) => option.value === bulkState.value) ?? null)
const changingCount = computed(() => previewRows.value.filter((row) => row.state?.value !== bulkState.value).length)

const effects = computed<string[]>(() => {
    const effectsByState: Record<string, string[]> = {
        in_process: [ctrans("Cannot be added to purchase orders")],
        active: [ctrans("Can be added to purchase orders")],
        discontinuing: [ctrans("Cannot be added to new purchase orders")],
        discontinued: [ctrans("Cannot be added to purchase orders"), ctrans("Discontinued date is recorded")],
    }

    if (!bulkState.value) {
        return []
    }

    return [
        ...(effectsByState[bulkState.value] ?? []),
        ctrans("Applies in every organisation"),
        ctrans("Stock and website do not change: products still sell while there is stock"),
    ]
})

function openDialog() {
    bulkState.value = null
    discontinuedReason.value = ""
    error.value = null
    success.value = null
    isDialogOpen.value = true
}

async function save() {
    if (!bulkState.value || !props.selectedIds.length) {
        error.value = ctrans("Choose the new state first")
        return
    }

    isSaving.value = true
    error.value = null
    try {
        const response = await axios.patch(route("grp.models.supplier-products.state.update"), {
            [props.idsParameter]: props.selectedIds,
            state: bulkState.value,
            ...(isDiscontinuedState(bulkState.value) ? { discontinued_reason: discontinuedReason.value.trim() || null } : {}),
        })
        success.value = ctrans(":count products set to :state", {
            count: response.data.updated,
            state: newOption.value?.label ?? bulkState.value,
        })
        isDialogOpen.value = false
        emits("saved")
    } catch (exception: any) {
        error.value = exception?.response?.data?.message ?? ctrans("Failed to update the state")
    } finally {
        isSaving.value = false
    }
}
</script>

<template>
    <div class="flex items-center gap-1.5 whitespace-nowrap">
        <Button
            type="tertiary"
            :label="ctrans('Set state (:count)', { count: selectedIds.length })"
            :disabled="!selectedIds.length"
            :tooltip="selectedIds.length ? undefined : ctrans('Tick the products to change their state')"
            @click="openDialog"
        >
            <template #icon>
                <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
            </template>
        </Button>
        <span v-if="success" class="text-xs text-green-600">{{ success }}</span>

        <Dialog
            v-model:visible="isDialogOpen"
            modal
            :header="ctrans('Set state of :count products', { count: selectedIds.length })"
            :style="{ width: '40rem', maxWidth: 'calc(100vw - 2rem)' }"
            :draggable="false"
        >
            <div class="space-y-4 whitespace-normal text-sm">
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-gray-600">{{ ctrans("New state") }} <span class="text-red-500">*</span></span>
                    <Select
                        v-model="bulkState"
                        :options="stateOptions"
                        optionLabel="label"
                        optionValue="value"
                        size="small"
                        class="w-full text-xs [&.p-focus]:!border-[var(--app-accent)]"
                        :pt="stateSelectPt"
                        :placeholder="ctrans('Choose the new state')"
                        :disabled="isSaving"
                        @change="error = null"
                    />
                </label>

                <label v-if="isDiscontinuedState(bulkState)" class="block space-y-1">
                    <span class="text-xs font-semibold text-gray-600">{{ ctrans("Reason") }}</span>
                    <Textarea
                        v-model="discontinuedReason"
                        rows="2"
                        maxlength="500"
                        autoResize
                        class="w-full text-xs"
                        :placeholder="ctrans('Optional, shown to the supplier on their portal')"
                        :disabled="isSaving"
                    />
                </label>

                <div v-if="effects.length" class="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    <div class="mb-1 flex items-center gap-1 font-semibold">
                        <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-amber-500" fixed-width aria-hidden="true" />
                        {{ ctrans("What will happen:") }}
                    </div>
                    <ul class="list-disc space-y-0.5 pl-4">
                        <li v-for="effect in effects" :key="effect">{{ effect }}</li>
                    </ul>
                </div>

                <div class="max-h-[50vh] overflow-y-auto rounded border border-gray-200">
                    <table class="w-full text-xs">
                        <thead class="sticky top-0 whitespace-nowrap bg-gray-50 text-left text-gray-500">
                            <tr>
                                <th class="px-3 py-2 font-semibold">{{ ctrans("Code") }}</th>
                                <th class="px-3 py-2 font-semibold">{{ ctrans("Name") }}</th>
                                <th class="px-3 py-2 font-semibold">{{ ctrans("Current") }}</th>
                                <th class="px-3 py-2 font-semibold">{{ ctrans("New") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="row in previewRows"
                                :key="row.id"
                                :class="bulkState && row.state?.value === bulkState ? 'text-gray-400' : ''"
                            >
                                <td class="whitespace-nowrap px-3 py-1.5 font-medium">{{ row.code }}</td>
                                <td class="px-3 py-1.5">{{ row.name }}</td>
                                <td class="whitespace-nowrap px-3 py-1.5">
                                    <span v-if="row.state" class="inline-flex items-center gap-1">
                                        <Icon :data="row.state.icon" />
                                        {{ row.state.label }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-1.5">
                                    <span v-if="!newOption" class="text-gray-400">-</span>
                                    <span v-else-if="row.state?.value === bulkState" class="italic">{{ ctrans("no change") }}</span>
                                    <span v-else class="inline-flex items-center gap-1 font-semibold text-[var(--app-accent)]">
                                        <FontAwesomeIcon icon="fal fa-arrow-right" fixed-width aria-hidden="true" />
                                        {{ newOption.label }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="hiddenCount > 0" class="text-xs text-gray-500">
                    {{ ctrans(":count more ticked products on other pages are not shown here", { count: hiddenCount }) }}
                </p>
                <p v-if="newOption" class="text-xs text-gray-600">
                    {{ ctrans(":changing of :total products will change to :state", { changing: changingCount + Math.max(hiddenCount, 0), total: selectedIds.length, state: newOption.label }) }}
                </p>
                <p v-if="error" class="text-xs text-red-500">{{ error }}</p>
            </div>

            <template #footer>
                <div class="flex w-full gap-2">
                    <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isDialogOpen = false">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button
                        type="primary"
                        full
                        :label="ctrans('Set state')"
                        :loading="isSaving"
                        :disabled="!bulkState || (changingCount === 0 && hiddenCount <= 0)"
                        @click="save"
                    >
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </template>
        </Dialog>
    </div>
</template>
