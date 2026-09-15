<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { useForm, router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { useFormatTime } from "@/Composables/useFormatTime"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TicketComposer from "@/Components/Tickets/TicketComposer.vue"
import TicketBody from "@/Components/Tickets/TicketBody.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPencil, faTrashAlt, faUser } from "@fal"
import { faSlack } from "@fortawesome/free-brands-svg-icons"

library.add(faPencil, faTrashAlt, faUser)

const props = withDefaults(defineProps<{
    ticket: { subject: string; description: string | null; reporter: string | null; reporter_avatar?: Record<string, string> | null; is_from_slack?: boolean; reference_url?: string | null; created_at: string; images?: Record<string, string>[] }
    comments: { id: number; body: string; is_internal: boolean; can_toggle_visibility?: boolean; is_staff: boolean; author: string | null; created_at: string; images?: Record<string, string>[]; attachments?: { name: string; url: string }[]; can_edit?: boolean; can_delete?: boolean }[]
    commentRoute: { name: string; parameters: Record<string, unknown> }
    mentionable?: { username: string; name: string | null }[]
    commentsNewestFirst?: boolean
    showDescription?: boolean
}>(), { commentsNewestFirst: true, showDescription: true })

const emit = defineEmits<{
    (e: "update:commentsNewestFirst", value: boolean): void
}>()

const form = useForm<{ body: string; images: File[] }>({ body: "", images: [] })

const isNewestFirst = ref(props.commentsNewestFirst)

const toggleCommentOrder = () => {
    isNewestFirst.value = !isNewestFirst.value
    emit("update:commentsNewestFirst", isNewestFirst.value)
}

const sortedComments = computed(() =>
    [...props.comments].sort((a, b) => (new Date(a.created_at).getTime() - new Date(b.created_at).getTime() || a.id - b.id) * (isNewestFirst.value ? -1 : 1))
)

const editingId = ref<number | null>(null)
const editBody = ref("")

const startEdit = (comment: { id: number; body: string }) => {
    editingId.value = comment.id
    editBody.value = comment.body
}

const saveEdit = (id: number) => {
    router.patch(route("grp.models.ticket.comment.update", id), { body: editBody.value }, { preserveScroll: true, onSuccess: () => (editingId.value = null) })
}

const toggleVisibility = (id: number) => {
    router.patch(route("grp.models.ticket.comment.toggle_visibility", id), {}, { preserveScroll: true })
}

const submit = () => {
    form.post(route(props.commentRoute.name, props.commentRoute.parameters), {
        onError: (errors) => notify({ title: trans("Comment not posted"), text: [...new Set(Object.values(errors))].join("<br>"), type: "error" }),
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => form.reset(),
    })
}
</script>

<template>
    <div class="space-y-4">
        <div v-if="showDescription" class="bg-white rounded-lg border-2 border-indigo-300 p-5 shadow-sm">
            <div class="text-xs text-gray-500 mb-3 pb-2 border-b border-gray-200 flex items-center gap-2">
                <TicketUserAvatar :name="ticket.reporter" :avatar="ticket.reporter_avatar" size="sm" />
                <span class="font-semibold text-gray-800">{{ ticket.reporter || trans("Unknown") }}</span>
                <span>· {{ useFormatTime(ticket.created_at, { formatTime: "PP, HH:mm:ss zzz" }) }}</span>
                <FontAwesomeIcon v-if="ticket.is_from_slack" v-tooltip="trans('Raised from Slack')" :icon="faSlack" class="text-gray-500" />
            </div>
            <h2 class="text-lg font-semibold mb-3">{{ ticket.subject }}</h2>
            <a v-if="ticket.reference_url" :href="ticket.reference_url" target="_blank" rel="noopener" class="mb-3 block truncate text-sm text-indigo-600 hover:underline">{{ ticket.reference_url }}</a>
            <TicketBody v-if="ticket.description || ticket.images?.length" :text="ticket.description" :images="ticket.images" />
            <p v-else class="text-sm text-gray-400">{{ trans("No description") }}</p>
        </div>

        <slot name="after-description" />

        <form class="bg-white rounded-lg border border-gray-300 p-4 space-y-3" @submit.prevent="submit">
            <TicketComposer v-model:body="form.body" v-model:images="form.images" :rows="4" :mentionable="mentionable" :placeholder="trans('Write a comment, paste a screenshot or drop images')" />
            <p v-if="form.errors.body || form.errors.images" class="text-xs text-red-600">{{ form.errors.body || form.errors.images }}</p>
            <div class="flex items-center justify-end">
                <Button :label="trans('Comment')" :loading="form.processing" :disabled="!form.body.trim() && !form.images.length" @click="submit" />
            </div>
        </form>

        <div v-if="comments.length > 1" class="ml-6 flex justify-end text-xs text-gray-500">
            <button type="button" class="px-1 py-0.5 hover:text-gray-900" :title="trans('Sort comments')" @click="toggleCommentOrder">
                {{ isNewestFirst ? "↓" : "↑" }} {{ isNewestFirst ? trans("Newest first") : trans("Oldest first") }}
            </button>
        </div>

        <div class="ml-6 space-y-3 border-l-2 border-gray-200 pl-4">
            <div
                v-for="comment in sortedComments"
                :key="comment.id"
                class="rounded-md border px-3 py-2 text-sm"
                :class="comment.is_internal ? 'bg-amber-50 border-amber-200' : comment.is_staff ?'bg-gray-50 border-gray-200' : 'bg-blue-50 border-blue-200'"
            >
                <div class="text-xs text-gray-500 mb-1 flex items-center gap-2">
                    <span v-if="comment.author" class="font-medium text-gray-700">{{ comment.author }} ·</span>
                    <span v-else class="flex items-center gap-2"><img class="h-4 select-none" src="/art/invader.svg" alt="aiku" /> ·</span>
                    {{ useFormatTime(comment.created_at, { formatTime: "hm" }) }}
                    <span v-if="comment.is_internal" class="px-1.5 py-0.5 rounded bg-amber-200 text-amber-900 text-[10px] font-medium">{{ trans("Internal") }}</span>
                    <span class="ml-auto flex gap-1">
                        <Button v-if="comment.can_toggle_visibility" type="tertiary" size="xs" :label="comment.is_internal ? trans('Make public') : trans('Hide')" @click="toggleVisibility(comment.id)" />
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
                    <textarea v-model="editBody" :rows="Math.max(3, editBody.split('\n').length + 1)" class="w-full rounded border-gray-300 text-sm [field-sizing:content] min-h-[4.5rem]" />
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
