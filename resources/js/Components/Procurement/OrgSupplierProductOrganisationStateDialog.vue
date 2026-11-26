<!--
* Author: Vika Aqordi
* Created on: 2026-11-26 14:00
* Github: https://github.com/aqordeon
* Copyright: 2026
-->

<script setup lang="ts">
import { ref, watch } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import ToggleSwitch from "primevue/toggleswitch"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faTimes } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { SupplierProductStateOption, stateSelectPt } from "@/Composables/useSupplierProductState"

library.add(faCheck, faTimes)

export type BuyingOrganisation = {
    code: string
    state: { value: string, label: string }
    is_available: boolean
    is_organisation_row_available: boolean
    is_supplier_product_available: boolean
    linked_sko_codes: string[]
    sko_suppliers: { sko_code: string, supplier_codes: string[] }[]
    org_supplier_product_ids: number[]
    can_edit: boolean
}

const props = defineProps<{
    productCode: string
    organisation: BuyingOrganisation | null
    stateOptions: SupplierProductStateOption[]
}>()

const isVisible = defineModel<boolean>("visible", { required: true })

const emits = defineEmits<{
    (e: "saved"): void
}>()

const newState = ref<string | null>(null)
const isAvailable = ref(false)
const isSaving = ref(false)
const error = ref<string | null>(null)

watch(isVisible, (visible) => {
    if (visible) {
        newState.value = props.organisation?.state.value ?? null
        isAvailable.value = props.organisation?.is_organisation_row_available ?? false
        error.value = null
    }
})

const changes = () => ({
    ...(newState.value && newState.value !== props.organisation?.state.value ? { state: newState.value } : {}),
    ...(isAvailable.value !== props.organisation?.is_organisation_row_available ? { is_available: isAvailable.value } : {}),
})

const hasChanges = () => Object.keys(changes()).length > 0

const options = () => props.stateOptions.map((option) => ({ ...option, disabled: option.value === props.organisation?.state.value }))

async function save() {
    const modelData = changes()
    if (!props.organisation || !Object.keys(modelData).length) {
        return
    }

    isSaving.value = true
    error.value = null
    try {
        for (const orgSupplierProductId of props.organisation.org_supplier_product_ids) {
            await axios.patch(route("grp.models.org_supplier_product.update", [orgSupplierProductId]), modelData)
        }
        isVisible.value = false
        emits("saved")
    } catch (exception: any) {
        error.value = exception?.response?.data?.message ?? ctrans("Failed to update the state")
    } finally {
        isSaving.value = false
    }
}
</script>

<template>
    <Dialog
        v-model:visible="isVisible"
        modal
        :header="ctrans(':code in :organisation', { code: productCode, organisation: organisation?.code ?? '' })"
        :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }"
        :draggable="false"
    >
        <div v-if="organisation" class="space-y-3 text-sm">
            <p class="text-xs text-gray-500">
                {{ ctrans("Changes :organisation only. The other organisations keep theirs.", { organisation: organisation.code }) }}
            </p>
            <div class="flex items-center justify-between gap-2 text-xs">
                <span class="text-gray-500">{{ ctrans("Current state") }}</span>
                <span class="font-semibold text-gray-700">{{ organisation.state.label }}</span>
            </div>
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <ToggleSwitch
                        v-model="isAvailable"
                        inputId="org-supplier-product-available"
                        :disabled="isSaving"
                        :pt="{ slider: { class: isAvailable ? '!bg-green-500 !border-green-500' : '!bg-red-500 !border-red-500' }, handle: { class: '!bg-white flex items-center justify-center' } }"
                    >
                        <template #handle="{ checked }">
                            <FontAwesomeIcon :icon="checked ? 'fal fa-check' : 'fal fa-times'" :class="checked ? 'text-green-600' : 'text-red-500'" class="text-[10px]" fixed-width aria-hidden="true" />
                        </template>
                    </ToggleSwitch>
                    <label for="org-supplier-product-available" class="text-xs text-gray-700">
                        {{ isAvailable
                            ? ctrans(":organisation can order it from this supplier", { organisation: organisation.code })
                            : ctrans(":organisation cannot order it from this supplier", { organisation: organisation.code }) }}
                    </label>
                </div>
                <p v-if="!organisation.is_supplier_product_available" class="text-xs text-amber-700">
                    {{ ctrans("The supplier product is unavailable for every organisation, so it stays unavailable until the supplier product is available again.") }}
                </p>
            </div>
            <label class="block space-y-1">
                <span class="text-xs font-semibold text-gray-600">{{ ctrans("State") }}</span>
                <Select
                    v-model="newState"
                    :options="options()"
                    optionLabel="label"
                    optionValue="value"
                    optionDisabled="disabled"
                    size="small"
                    class="w-full text-xs [&.p-focus]:!border-[var(--app-accent)]"
                    :pt="stateSelectPt"
                    :aria-label="ctrans('Select new state')"
                    :disabled="isSaving"
                />
            </label>
            <p v-if="error" class="text-xs text-red-500">{{ error }}</p>
        </div>

        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" :disabled="isSaving" @click="isVisible = false">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button
                    type="primary"
                    full
                    :label="ctrans('Save')"
                    :loading="isSaving"
                    :disabled="!hasChanges()"
                    @click="save"
                >
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </template>
    </Dialog>
</template>
