<!--
* Author: Vika Aqordi
* Created on: 2026-11-25 10:00
* Github: https://github.com/aqordeon
* Copyright: 2026
-->

<script setup lang="ts">
import { ref } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import Select from "primevue/select"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUserTie, faPencil, faTimes } from "@fal"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"

library.add(faUserTie, faPencil, faTimes)

interface ContactOption {
    value: number
    label: string
    organisations: string[]
}

const props = defineProps<{
    data: {
        supplier_id: number
        user_id: number | null
        name: string | null
        can_edit: boolean
    }
}>()

const isEditing = ref(false)
const isLoading = ref(false)
const isSaving = ref(false)
const options = ref<ContactOption[]>([])
const userId = ref<number | null>(props.data.user_id)
const error = ref<string | null>(null)

async function startEditing() {
    isEditing.value = true
    error.value = null
    userId.value = props.data.user_id
    isLoading.value = true
    try {
        const response = await axios.get(route("grp.json.suppliers.relationship_contact_options"), { params: { suppliers: [props.data.supplier_id] } })
        options.value = response.data.options
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
            suppliers: [props.data.supplier_id],
            relationship_contact_user_id: relationshipContactUserId,
        })
        if (!response.data.updated) {
            error.value = ctrans("Choose one of the contacts shown on this supplier's portal.")
            return
        }
        isEditing.value = false
        router.reload({ only: ["showcase"] })
    } catch (exception: any) {
        error.value = exception?.response?.data?.message ?? ctrans("Failed to set the relationship contact")
    } finally {
        isSaving.value = false
    }
}
</script>

<template>
    <div class="px-6 py-3.5">
        <div class="group flex items-center gap-x-4">
            <FontAwesomeIcon v-tooltip="ctrans('Relationship contact')" icon="fal fa-user-tie" class="flex-none text-gray-400" fixed-width aria-hidden="true" />
            <div class="min-w-0 flex-1 text-sm">
                <span class="text-gray-400">{{ ctrans("Relationship contact") }}:</span>
                <span v-if="data.name" class="font-medium text-gray-900">&nbsp;{{ data.name }}</span>
                <span v-else class="italic text-gray-400"> {{ ctrans("Not set") }}</span>
            </div>
            <button
                v-if="data.can_edit && !isEditing"
                v-tooltip="ctrans('Set relationship contact')"
                type="button"
                class="flex-none text-gray-300 transition-colors hover:text-[var(--app-accent)] focus:text-[var(--app-accent)] group-hover:text-gray-400"
                @click="startEditing"
            >
                <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                <span class="sr-only">{{ ctrans("Set relationship contact") }}</span>
            </button>
            <button
                v-if="isEditing"
                v-tooltip="ctrans('Cancel')"
                type="button"
                class="flex-none text-red-500"
                @click="isEditing = false"
            >
                <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                <span class="sr-only">{{ ctrans("Cancel") }}</span>
            </button>
        </div>

        <div v-if="isEditing" class="mt-2 space-y-1 pl-9">
            <div v-if="isLoading" class="flex items-center gap-2 text-xs text-gray-500">
                <LoadingIcon />
                {{ ctrans("Loading the contacts shown on the portal") }}
            </div>
            <template v-else>
                <div class="flex items-center gap-2">
                    <Select
                        v-model="userId"
                        :options="options"
                        optionLabel="label"
                        optionValue="value"
                        filter
                        autoFilterFocus
                        showClear
                        size="small"
                        class="w-full text-xs [&.p-focus]:!border-[var(--app-accent)]"
                        :placeholder="options.length ? ctrans('Choose a contact') : ctrans('This supplier has no portal contacts yet')"
                        :disabled="isSaving || !options.length"
                        @change="save(userId)"
                    >
                        <template #option="{ option }">
                            <div class="min-w-0 text-xs">
                                <div class="font-semibold text-gray-800">{{ option.label }}</div>
                                <div class="truncate text-gray-500">{{ option.organisations.join(" · ") }}</div>
                            </div>
                        </template>
                    </Select>
                    <LoadingIcon v-if="isSaving" />
                </div>
                <p class="text-xs text-gray-500">{{ ctrans("Only the contacts shown on this supplier's portal. The portal labels the chosen one Main.") }}</p>
            </template>
            <p v-if="error" class="text-xs text-red-500">{{ error }}</p>
        </div>
    </div>
</template>
