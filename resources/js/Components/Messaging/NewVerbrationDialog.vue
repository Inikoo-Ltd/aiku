<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 26 Nov 2026 04:30:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import AutoComplete from "primevue/autocomplete"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useStaffMessaging, type StaffCoworker } from "@/Stores/staff-messaging"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faCheck } from "@fal"

const visible = defineModel<boolean>("visible", { default: false })
const emit = defineEmits<{ created: [ulid: string] }>()

const store = useStaffMessaging()
const name = ref("")
const people = ref<StaffCoworker[]>([])
const suggestions = ref<StaffCoworker[]>([])
const error = ref<string | null>(null)
const isSaving = ref(false)

const canSubmit = computed(() => name.value.trim().length > 0 && people.value.length > 0 && !isSaving.value)

watch(visible, (open) => {
    if (open) {
        name.value = ""
        people.value = []
        error.value = null
    }
})

const search = async (event: { query: string }) => {
    const { data } = await axios.get(route("grp.chat.staff.coworkers.index"), { params: { q: event.query } })
    const chosen = new Set(people.value.map((person) => person.id))
    suggestions.value = (data.data as StaffCoworker[]).filter((person) => !chosen.has(person.id))
}

const submit = async () => {
    if (!canSubmit.value) return
    isSaving.value = true
    error.value = null
    try {
        const conversation = await store.createGroup(name.value.trim(), people.value.map((person) => person.id))
        visible.value = false
        emit("created", conversation.ulid)
    } catch (exception: any) {
        error.value = exception?.response?.data?.message ?? ctrans("The Verbration could not be created. Try again.")
    } finally {
        isSaving.value = false
    }
}
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="ctrans('New Verbration')" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
        <div class="space-y-4 text-sm">
            <p class="text-gray-500">{{ ctrans("A private group: only the people you invite can see it.") }}</p>
            <label class="block space-y-1">
                <span class="font-medium text-gray-700">{{ ctrans("Name") }} <span class="text-red-500">*</span></span>
                <InputText v-model="name" class="w-full" maxlength="255" :placeholder="ctrans('e.g. Buying team')" autofocus @keydown.enter="submit" />
            </label>
            <label class="block space-y-1">
                <span class="font-medium text-gray-700">{{ ctrans("People") }} <span class="text-red-500">*</span></span>
                <AutoComplete
                    v-model="people"
                    multiple
                    fluid
                    :suggestions="suggestions"
                    optionLabel="name"
                    dataKey="id"
                    :delay="200"
                    :placeholder="people.length ? '' : ctrans('Type a name')"
                    @complete="search" />
            </label>
            <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
        </div>
        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="primary" full :label="ctrans('Create')" :loading="isSaving" :disabled="!canSubmit" @click="submit">
                    <template #icon>
                        <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </template>
    </Dialog>
</template>
