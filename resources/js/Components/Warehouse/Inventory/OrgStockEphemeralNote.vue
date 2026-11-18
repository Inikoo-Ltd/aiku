<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faStickyNote, faCheck, faTimes, faPencil, faPlus, faTrashAlt } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"

type EphemeralNote = {
    text: string
    author: string | null
    set_at: string | null
    expires_at: string
}

const props = defineProps<{
    note: EphemeralNote | null
    updateRoute: routeType | null
}>()

const isOpen = ref(false)
const text = ref("")
const expiresInDays = ref(30)
const isChangingExpiry = ref(false)
const isSaving = ref(false)
const error = ref<string | null>(null)

const expiryOptions = computed(() => [
    { label: ctrans(":days days", { days: 7 }), value: 7 },
    { label: ctrans(":days days", { days: 14 }), value: 14 },
    { label: ctrans(":days days", { days: 30 }), value: 30 },
])

const open = () => {
    text.value = props.note?.text ?? ""
    expiresInDays.value = 30
    isChangingExpiry.value = false
    error.value = null
    isOpen.value = true
}

const save = (note: string | null) => {
    if (!props.updateRoute) {
        return
    }
    isSaving.value = true
    error.value = null
    router.patch(
        route(props.updateRoute.name, props.updateRoute.parameters),
        { note, expires_in_days: expiresInDays.value },
        {
            preserveScroll: true,
            onSuccess: () => (isOpen.value = false),
            onError: (errors) => (error.value = Object.values(errors)[0] as string),
            onFinish: () => (isSaving.value = false),
        }
    )
}
</script>

<template>
    <div v-if="note || updateRoute" class="px-4 pt-3">
        <div v-if="note" class="flex items-start gap-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm">
            <FontAwesomeIcon :icon="faStickyNote" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
            <div class="min-w-0 flex-1">
                <div class="whitespace-pre-line text-gray-800">{{ note.text }}</div>
                <div class="mt-0.5 text-xs text-gray-500">
                    {{ note.author ?? ctrans("Unknown") }}, {{ useFormatTime(note.set_at ?? undefined) }}
                    · {{ ctrans("hides on :date", { date: useFormatTime(note.expires_at) }) }}
                </div>
            </div>
            <Button v-if="updateRoute" type="tertiary" size="xs" :label="ctrans('Edit')" @click="open">
                <template #icon><FontAwesomeIcon :icon="faPencil" fixed-width aria-hidden="true" /></template>
            </Button>
        </div>

        <Button v-else type="tertiary" size="xs" :label="ctrans('Add ephemeral note')" @click="open">
            <template #icon><FontAwesomeIcon :icon="faPlus" fixed-width aria-hidden="true" /></template>
        </Button>

        <Dialog v-model:visible="isOpen" modal :header="ctrans('Ephemeral note')" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
            <div class="space-y-3">
                <p class="text-xs text-gray-500">
                    {{ ctrans("A short status everyone sees on this SKO, e.g. waiting for labels. It hides by itself.") }}
                </p>
                <Textarea v-model="text" rows="3" maxlength="500" autoResize class="w-full text-sm" :placeholder="ctrans('Waiting for labels before it can be made')" />

                <div class="text-xs text-gray-500">
                    {{ ctrans("Hides in :days days", { days: expiresInDays }) }}
                    <button v-if="!isChangingExpiry" type="button" class="ml-1 underline hover:text-gray-700" @click="isChangingExpiry = true">
                        {{ ctrans("change") }}
                    </button>
                </div>
                <SegmentedToggle v-if="isChangingExpiry" v-model="expiresInDays" :options="expiryOptions" />

                <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
            </div>

            <template #footer>
                <div class="flex w-full gap-2">
                    <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isOpen = false">
                        <template #icon><FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button v-if="note" type="tertiary" class="shrink-0" :label="ctrans('Remove')" :disabled="isSaving" @click="save(null)">
                        <template #icon><FontAwesomeIcon :icon="faTrashAlt" class="text-amber-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button type="primary" full :label="ctrans('Save')" :loading="isSaving" :disabled="!text.trim()" @click="save(text)">
                        <template #icon><FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>
            </template>
        </Dialog>
    </div>
</template>
