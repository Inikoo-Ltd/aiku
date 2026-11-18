<!--
* Author: Vika Aqordi
* Created on: 2026-11-18 10:00
* Github: https://github.com/aqordeon
* Copyright: 2026
-->

<script setup lang="ts">
import { ref } from "vue"
import axios from "axios"
import Select from "primevue/select"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faTimes } from "@fal"
import Icon from "@/Components/Icon.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { SupplierProductState, SupplierProductStateOption, isDiscontinuedState, stateSelectPt } from "@/Composables/useSupplierProductState"

library.add(faCheck, faTimes)

const props = defineProps<{
    supplierProductId: number
    code: string
    state: SupplierProductState | null
    stateOptions: SupplierProductStateOption[]
}>()

const emits = defineEmits<{
    (e: "saved"): void
}>()

const newState = ref<string | null>(null)
const isSaving = ref(false)
const error = ref<string | null>(null)
const isReasonDialogOpen = ref(false)
const discontinuedReason = ref("")

const options = () => props.stateOptions.map((option) => ({ ...option, disabled: option.value === props.state?.value }))
const newStateLabel = () => props.stateOptions.find((option) => option.value === newState.value)?.label ?? ""

function requestState() {
    if (isDiscontinuedState(newState.value)) {
        discontinuedReason.value = ""
        isReasonDialogOpen.value = true
        return
    }

    saveState()
}

function confirmDiscontinuedReason() {
    isReasonDialogOpen.value = false
    saveState(discontinuedReason.value)
}

async function saveState(reason?: string) {
    const state = newState.value
    if (!state || state === props.state?.value) {
        return
    }

    isSaving.value = true
    error.value = null
    try {
        await axios.patch(route("grp.models.supplier-product.update", [props.supplierProductId]), {
            state,
            ...(isDiscontinuedState(state) ? { discontinued_reason: reason?.trim() || null } : {}),
        })
        newState.value = null
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
        <Icon v-if="state" :data="state.icon" :title="state.label" />
        <Select
            v-model="newState"
            :options="options()"
            optionLabel="label"
            optionValue="value"
            optionDisabled="disabled"
            size="small"
            class="w-36 text-xs [&.p-focus]:!border-[var(--app-accent)]"
            :pt="stateSelectPt"
            :placeholder="state?.label ?? ctrans('Select new')"
            :aria-label="ctrans('Select new state')"
            :disabled="isSaving"
        >
            <template #option="{ option }">
                <span class="flex w-full items-center justify-between gap-2 text-xs">
                    {{ option.label }}
                    <span v-if="option.disabled" class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-gray-400">{{ ctrans("current") }}</span>
                </span>
            </template>
        </Select>
        <Button
            type="primary"
            size="xs"
            :label="ctrans('Set')"
            :disabled="!newState || newState === state?.value"
            :loading="isSaving"
            @click="requestState"
        />
        <span v-if="error" class="text-xs text-red-500">{{ error }}</span>

        <Dialog
            v-model:visible="isReasonDialogOpen"
            modal
            :header="ctrans('Set :code to :state', { code, state: newStateLabel() })"
            :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }"
            :draggable="false"
        >
            <label class="block space-y-1 text-sm">
                <span class="text-xs font-semibold text-gray-600">{{ ctrans("Reason") }}</span>
                <Textarea
                    v-model="discontinuedReason"
                    rows="3"
                    maxlength="500"
                    autoResize
                    autofocus
                    class="w-full text-xs"
                    :placeholder="ctrans('Optional, shown to the supplier on their portal')"
                />
            </label>

            <template #footer>
                <div class="flex w-full gap-2">
                    <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isReasonDialogOpen = false">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button type="primary" full :label="ctrans('Set state')" @click="confirmDiscontinuedReason">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </template>
        </Dialog>
    </div>
</template>
