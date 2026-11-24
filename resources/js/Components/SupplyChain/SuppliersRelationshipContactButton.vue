<!--
* Author: Vika Aqordi
* Created on: 2026-11-24 15:00
* Github: https://github.com/aqordeon
* Copyright: 2026
-->

<script setup lang="ts">
import { ref } from "vue"
import axios from "axios"
import Select from "primevue/select"
import Dialog from "primevue/dialog"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faTimes, faUserTie } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"

library.add(faCheck, faTimes, faUserTie)

const props = defineProps<{
    selectedIds: number[]
    options: { value: number; label: string }[]
}>()

const emits = defineEmits<{
    (e: "saved"): void
}>()

const isDialogOpen = ref(false)
const userId = ref<number | null>(null)
const isSaving = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)

function openDialog() {
    userId.value = null
    error.value = null
    success.value = null
    isDialogOpen.value = true
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
            :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }"
            :draggable="false"
        >
            <div class="space-y-3 whitespace-normal text-sm">
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-gray-600">{{ ctrans("Staff member") }} <span class="text-red-500">*</span></span>
                    <Select
                        v-model="userId"
                        :options="options"
                        optionLabel="label"
                        optionValue="value"
                        filter
                        autoFilterFocus
                        size="small"
                        class="w-full text-xs [&.p-focus]:!border-[var(--app-accent)]"
                        :placeholder="ctrans('Choose a staff member')"
                        :disabled="isSaving"
                        @change="error = null"
                    />
                </label>
                <p class="text-xs text-gray-500">{{ ctrans("The supplier portal labels this person Main among the contacts.") }}</p>
                <button
                    type="button"
                    class="text-xs text-red-500 hover:underline disabled:opacity-50"
                    :disabled="isSaving"
                    @click="save(null)"
                >
                    {{ ctrans("Clear the relationship contact of these suppliers") }}
                </button>
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
