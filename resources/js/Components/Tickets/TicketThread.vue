<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { useForm, router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { useFormatTime } from "@/Composables/useFormatTime"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TicketComposer from "@/Components/Tickets/TicketComposer.vue"
import TicketBody from "@/Components/Tickets/TicketBody.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPencil, faTrashAlt } from "@fal"

library.add(faPencil, faTrashAlt)

const props = defineProps<{
    ticket: { description: string | null; reporter: string | null; created_at: string; images?: Record<string, string>[] }
    comments: { id: number; body: string; is_internal: boolean; is_staff: boolean; author: string | null; created_at: string; images?: Record<string, string>[]; attachments?: { name: string; url: string }[]; can_edit?: boolean; can_delete?: boolean }[]
    commentRoute: { name: string; parameters: Record<string, unknown> }
    allowInternal?: boolean
}>()

const form = useForm<{ body: string; is_internal: boolean; images: File[] }>({ body: "", is_internal: false, images: [] })

const editingId = ref<number | null>(null)
const editBody = ref("")

const startEdit = (comment: { id: number; body: string }) => {
    editingId.value = comment.id
    editBody.value = comment.body
}

const saveEdit = (id: number) => {
    router.patch(route("grp.models.ticket.comment.update", id), { body: editBody.value }, { preserveScroll: true, onSuccess: () => (editingId.value = null) })
}

const submit = () => {
    form.post(route(props.commentRoute.name, props.commentRoute.parameters), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => form.reset(),
    })
}
</script>

<template>
    <div class="space-y-4">
        <div class="bg-white rounded-lg border-2 border-indigo-300 p-5 shadow-sm">
            <div class="text-xs text-gray-500 mb-3 pb-2 border-b border-gray-200">
                <span class="font-semibold text-gray-800">{{ ticket.reporter || trans("Unknown") }}</span>
                · {{ useFormatTime(ticket.created_at, { formatTime: "hm" }) }}
            </div>
            <TicketBody v-if="ticket.description || ticket.images?.length" :text="ticket.description" :images="ticket.images" />
            <p v-else class="text-sm text-gray-400">{{ trans("No description") }}</p>
        </div>

        <form class="bg-white rounded-lg border border-gray-300 p-4 space-y-3" @submit.prevent="submit">
            <TicketComposer v-model:body="form.body" v-model:images="form.images" :rows="4" :placeholder="trans('Write a comment, paste a screenshot or drop images')" />
            <p v-if="form.errors.body || form.errors.images" class="text-xs text-red-600">{{ form.errors.body || form.errors.images }}</p>
            <div class="flex items-center justify-between">
                <label v-if="allowInternal" class="flex items-center gap-2 text-sm text-gray-600">
                    <input v-model="form.is_internal" type="checkbox" class="rounded border-gray-300" />
                    {{ trans("Internal note (hidden from customer)") }}
                </label>
                <span v-else />
                <Button :label="trans('Comment')" :loading="form.processing" :disabled="!form.body.trim() && !form.images.length" @click="submit" />
            </div>
        </form>

        <div class="ml-6 space-y-3 border-l-2 border-gray-200 pl-4">
            <div
                v-for="comment in comments"
                :key="comment.id"
                class="rounded-md border px-3 py-2 text-sm"
                :class="comment.is_internal ? 'bg-amber-50 border-amber-200' : comment.is_staff ? 'bg-gray-50 border-gray-200' : 'bg-blue-50 border-blue-200'"
            >
                <div class="text-xs text-gray-500 mb-1 flex items-center gap-2">
                    <span class="font-medium text-gray-700">{{ comment.author || trans("Unknown") }}</span>
                    · {{ useFormatTime(comment.created_at, { formatTime: "hm" }) }}
                    <span v-if="comment.is_internal" class="px-1.5 py-0.5 rounded bg-amber-200 text-amber-900 text-[10px] font-medium">{{ trans("Internal") }}</span>
                    <span class="ml-auto flex gap-1">
                        <button v-if="comment.can_edit" v-tooltip="trans('Edit')" type="button" class="p-1 text-gray-500 hover:text-gray-800" @click="startEdit(comment)">
                            <FontAwesomeIcon icon="fal fa-pencil" fixed-width />
                        </button>
                        <ModalConfirmationDelete
                            v-if="comment.can_delete"
                            :title="trans('Delete this comment?')"
                            :description="trans('The comment will be removed from the ticket.')"
                            :noLabel="trans('Yes, delete')"
                            :routeDelete="{ name: 'grp.models.ticket.comment.delete', parameters: { ticketComment: comment.id } }">
                            <template #default="{ changeModel }">
                                <button v-tooltip="trans('Delete')" type="button" class="p-1 text-gray-500 hover:text-red-600" @click="changeModel">
                                    <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width />
                                </button>
                            </template>
                        </ModalConfirmationDelete>
                    </span>
                </div>
                <div v-if="editingId === comment.id" class="space-y-2">
                    <textarea v-model="editBody" rows="3" class="w-full rounded border-gray-300 text-sm" />
                    <div class="flex gap-2 justify-end">
                        <Button type="tertiary" :label="trans('Cancel')" @click="editingId = null" />
                        <Button :label="trans('Save')" :disabled="!editBody.trim()" @click="saveEdit(comment.id)" />
                    </div>
                </div>
                <TicketBody v-else :text="comment.body" :images="comment.images" :attachments="comment.attachments" />
            </div>
        </div>
    </div>
</template>
