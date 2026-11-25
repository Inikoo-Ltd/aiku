<!--
* Author: Vika Aqordi
* Created on: 2026-11-24 15:00
* Github: https://github.com/aqordeon
* Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref } from "vue"
import axios from "axios"
import Select from "primevue/select"
import Dialog from "primevue/dialog"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faTimes, faUserTie, faExclamationTriangle } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"

library.add(faCheck, faTimes, faUserTie, faExclamationTriangle)

interface ContactOption {
    value: number
    label: string
    organisations: string[]
    suppliers_count: number
}

const props = defineProps<{
    selectedIds: number[]
}>()

const emits = defineEmits<{
    (e: "saved"): void
}>()

const isDialogOpen = ref(false)
const isLoading = ref(false)
const options = ref<ContactOption[]>([])
const suppliersCount = ref(0)
const userId = ref<number | null>(null)
const isSaving = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)

const selectedOption = computed(() => options.value.find((option) => option.value === userId.value) ?? null)
const skippedCount = computed(() => (selectedOption.value ? suppliersCount.value - selectedOption.value.suppliers_count : 0))

async function openDialog() {
    userId.value = null
    error.value = null
    success.value = null
    options.value = []
    isDialogOpen.value = true
    isLoading.value = true
    try {
        const response = await axios.get(route("grp.json.suppliers.relationship_contact_options"), { params: { suppliers: props.selectedIds } })
        options.value = response.data.options
        suppliersCount.value = response.data.suppliers_count
    } catch (exception: any) {
        error.value = exception?.response?.data?.message ?? ctrans("Failed to load the contacts")
    } finally {
        isLoading.value = false
    }
}

async function save(relationshipContactUserId: number | null) {
    isSaving.value = true
    error.value = null
    try {
        const response = await axios.patch(route("grp.models.suppliers.relationship_contact.update"), {
            suppliers: props.selectedIds,
            relationship_contact_user_id: relationshipContactUserId,
        })
        success.value = relationshipContactUserId
            ? ctrans("Relationship contact set on :count suppliers", { count: response.data.updated })
            : ctrans("Relationship contact cleared on :count suppliers", { count: response.data.updated })
        isDialogOpen.value = false
        emits("saved")
    } catch (exception: any) {
        error.value = exception?.response?.data?.message ?? ctrans("Failed to set the relationship contact")
    } finally {
        isSaving.value = false
    }
}
</script>

<template>
    <div class="flex items-center gap-1.5 whitespace-nowrap">
        <Button
            type="tertiary"
            :label="ctrans('Set relationship contact (:count)', { count: selectedIds.length })"
            :disabled="!selectedIds.length"
            :tooltip="selectedIds.length ? undefined : ctrans('Tick the suppliers to set their relationship contact')"
            @click="openDialog"
        >
            <template #icon>
                <FontAwesomeIcon icon="fal fa-user-tie" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
            </template>
        </Button>
        <span v-if="success" class="text-xs text-green-600">{{ success }}</span>

        <Dialog
            v-model:visible="isDialogOpen"
            modal
            :header="ctrans('Set relationship contact of :count suppliers', { count: selectedIds.length })"
            :style="{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }"
            :draggable="false"
        >
            <div class="space-y-3 whitespace-normal text-sm">
                <div v-if="isLoading" class="flex items-center gap-2 text-xs text-gray-500">
                    <LoadingIcon />
                    {{ ctrans("Loading the contacts shown on their portals") }}
                </div>
                <template v-else>
                    <label class="block space-y-1">
                        <span class="text-xs font-semibold text-gray-600">{{ ctrans("Portal contact") }} <span class="text-red-500">*</span></span>
                        <Select
                            v-model="userId"
                            :options="options"
                            optionLabel="label"
                            optionValue="value"
                            filter
                            autoFilterFocus
                            size="small"
                            class="w-full text-xs [&.p-focus]:!border-[var(--app-accent)]"
                            :placeholder="options.length ? ctrans('Choose a contact') : ctrans('These suppliers have no portal contacts yet')"
                            :disabled="isSaving || !options.length"
                            @change="error = null"
                        >
                            <template #option="{ option }">
                                <div class="flex w-full items-center justify-between gap-3 text-xs">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-gray-800">{{ option.label }}</div>
                                        <div v-if="option.organisations.length" class="truncate text-gray-500">{{ option.organisations.join(" · ") }}</div>
                                    </div>
                                    <span class="shrink-0 tabular-nums text-gray-500">{{ ctrans(":count of :total suppliers", { count: option.suppliers_count, total: suppliersCount }) }}</span>
                                </div>
                            </template>
                        </Select>
                    </label>
                    <p class="text-xs text-gray-500">{{ ctrans("The contacts shown on these suppliers' portals, or David Hardy and Tomas Belan. The portal labels the chosen one Main.") }}</p>
                    <div v-if="skippedCount > 0" class="flex items-start gap-1.5 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
                        {{ ctrans(":count suppliers do not show :name on their portal and are left as they are", { count: skippedCount, name: selectedOption?.label ?? "" }) }}
                    </div>
                    <button
                        type="button"
                        class="text-xs text-red-500 hover:underline disabled:opacity-50"
                        :disabled="isSaving"
                        @click="save(null)"
                    >
                        {{ ctrans("Clear the relationship contact of these suppliers") }}
                    </button>
                </template>
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
                        :label="ctrans('Set relationship contact')"
                        :loading="isSaving"
                        :disabled="!userId"
                        @click="save(userId)"
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
