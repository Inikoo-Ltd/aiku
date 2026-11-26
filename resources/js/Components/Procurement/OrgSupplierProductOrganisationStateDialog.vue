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
const isSaving = ref(false)
const error = ref<string | null>(null)

watch(isVisible, (visible) => {
    if (visible) {
        newState.value = props.organisation?.state.value ?? null
        error.value = null
    }
})

const options = () => props.stateOptions.map((option) => ({ ...option, disabled: option.value === props.organisation?.state.value }))

async function save() {
    if (!props.organisation || !newState.value || newState.value === props.organisation.state.value) {
        return
    }

    isSaving.value = true
    error.value = null
    try {
        for (const orgSupplierProductId of props.organisation.org_supplier_product_ids) {
            await axios.patch(route("grp.models.org_supplier_product.update", [orgSupplierProductId]), { state: newState.value })
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
                {{ ctrans("Changes the state for :organisation only. The other organisations keep theirs.", { organisation: organisation.code }) }}
            </p>
            <div class="flex items-center justify-between gap-2 text-xs">
                <span class="text-gray-500">{{ ctrans("Current state") }}</span>
                <span class="font-semibold text-gray-700">{{ organisation.state.label }}</span>
            </div>
            <label class="block space-y-1">
                <span class="text-xs font-semibold text-gray-600">{{ ctrans("New state") }} <span class="text-red-500">*</span></span>
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
                    :label="ctrans('Set state')"
                    :loading="isSaving"
                    :disabled="!newState || newState === organisation?.state.value"
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
