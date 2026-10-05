<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import axios from "axios"
import { useForm, router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { notify } from "@kyvg/vue3-notification"
import { useFormatTime } from "@/Composables/useFormatTime"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TicketComposer from "@/Components/Tickets/TicketComposer.vue"
import TicketBody from "@/Components/Tickets/TicketBody.vue"
import { attachmentIconFor } from "@/Components/Tickets/TicketAttachmentPreview.vue"
import TicketTranslation from "@/Components/Tickets/TicketTranslation.vue"
import TicketUserHoverCard from "@/Components/Tickets/TicketUserHoverCard.vue"
import ModalConfirmation from "@/Components/Utils/ModalConfirmation.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPencil, faTrashAlt, faUser, faShieldCheck, faShieldAlt, faForward, faSpinner } from "@fal"
import { faSlack } from "@fortawesome/free-brands-svg-icons"

library.add(faPencil, faTrashAlt, faUser, faShieldCheck, faShieldAlt, faForward, faSpinner)

const qaVerdictBadgeClass: Record<string, string> = {
    passed: "bg-green-200 text-green-900",
    failed: "bg-red-200 text-red-900",
    skipped: "bg-gray-200 text-gray-800",
}

const qaVerdictIcon: Record<string, string> = {
    passed: "fal fa-shield-check",
    failed: "fal fa-shield-alt",
    skipped: "fal fa-forward",
}

const props = withDefaults(defineProps<{
    ticket: { id?: number; subject: string; description: string | null; reporter: string | null; reporter_roles?: { key: string; label: string }[]; reporter_avatar?: Record<string, string> | null; reporter_username?: string | null; reporter_key?: string | null; reporter_profile_url?: string | null; is_from_slack?: boolean; reference_url?: string | null; created_at: string; images?: Record<string, string>[] }
    comments: { id: number; body: string; is_internal: boolean; is_lead_only?: boolean; type?: string; has_qa_verdict?: string | null; qa_verdict_label?: string | null; author_avatar?: Record<string, string> | null; author_username?: string | null; author_key?: string | null; author_profile_url?: string | null; author_roles?: { key: string; label: string }[]; can_toggle_visibility?: boolean; is_staff: boolean; author: string | null; created_at: string; images?: (Record<string, string> & { ulid?: string })[]; attachments?: { name: string; url: string; ulid?: string }[]; can_edit?: boolean; can_delete?: boolean }[]
    commentRoute: { name: string; parameters: Record<string, unknown> }
    mentionable?: { username: string; name: string | null; suggested?: boolean; is_customer?: boolean }[]
    commentsNewestFirst?: boolean
    showDescription?: boolean
    showComments?: boolean
    labelReporterOnMobile?: boolean
    canCommentInternally?: boolean
    translateRoutes?: { ticket: string; comment: string }
}>(), { commentsNewestFirst: true, showDescription: true, showComments: true, canCommentInternally: false, labelReporterOnMobile: false })

const emit = defineEmits<{
    (e: "update:commentsNewestFirst", value: boolean): void
}>()

const form = useForm<{ body: string; images: File[]; is_internal: boolean; type: string }>({ body: "", images: [], is_internal: false, type: "comment" })

const composer = ref<{ appendMention: (username: string) => void } | null>(null)

const mentionInReply = (username: string) => composer.value?.appendMention(username)

defineExpose({ mentionInReply })

const isNewestFirst = ref(props.commentsNewestFirst)

const toggleCommentOrder = () => {
    isNewestFirst.value = !isNewestFirst.value
    emit("update:commentsNewestFirst", isNewestFirst.value)
}

const sortedComments = computed(() =>
    [...props.comments].sort((a, b) => (new Date(a.created_at).getTime() - new Date(b.created_at).getTime() || a.id - b.id) * (isNewestFirst.value ? -1 : 1))
)

const daysAgo = (date: string) => {
    const days = Math.floor((Date.now() - new Date(date).getTime()) / 86_400_000)
    return days === 0 ? ctrans("today") : days === 1 ? ctrans("1 day ago") : ctrans(":days days ago", { days: String(days) })
}

const editingId = ref<number | null>(null)
const editBody = ref("")
const editRemovedMedia = ref<string[]>([])
const editImages = ref<File[]>([])

const startEdit = (comment: { id: number; body: string }) => {
    editingId.value = comment.id
    editBody.value = comment.body
    editRemovedMedia.value = []
    editImages.value = []
}

// Removing a file is only actually sent once Save is pressed, but it is still one-way enough
// (the undo is Cancel, which throws the whole edit away) to make somebody confirm it first.
const confirmRemoveMedia = (ulid: string, closeModal: () => void) => {
    editRemovedMedia.value.push(ulid)
    closeModal()
}

// Saving an edit and deleting a comment are both one-way enough, and both change what the
// other actions on the same comment would even mean, that neither should be tappable again
// — or paired with the other — while either is still in flight.
const savingEditId = ref<number | null>(null)
const deletingId = ref<number | null>(null)

const isCommentBusy = (id: number) => savingEditId.value === id || deletingId.value === id

const saveEdit = (id: number) => {
    savingEditId.value = id
    router.post(
        route("grp.models.ticket.comment.update", id),
        { _method: "patch", body: editBody.value, remove_media: editRemovedMedia.value, images: editImages.value },
        {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => (editingId.value = null),
            onFinish: () => (savingEditId.value = null),
        }
    )
}

const deleteComment = (id: number, closeModal: () => void) => {
    deletingId.value = id
    router.delete(route("grp.models.ticket.comment.delete", id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => closeModal(),
        onFinish: () => (deletingId.value = null),
    })
}

// What is still kept, while editing: whatever the comment has minus whatever was just
// marked for removal — the files themselves are only actually deleted once Save is pressed.
const keptEditImages = (comment: { images?: { ulid?: string }[] }) =>
    (comment.images ?? []).filter((image) => !image.ulid || !editRemovedMedia.value.includes(image.ulid))

const keptEditAttachments = (comment: { attachments?: { ulid?: string }[] }) =>
    (comment.attachments ?? []).filter((file) => !file.ulid || !editRemovedMedia.value.includes(file.ulid))

const expandedInternalIds = ref<number[]>([])

const toggleInternalExpanded = (id: number) => {
    expandedInternalIds.value = expandedInternalIds.value.includes(id) ? expandedInternalIds.value.filter((expandedId) => expandedId !== id) : [...expandedInternalIds.value, id]
}

const isCollapsed = (comment: { id: number; is_internal: boolean }) => comment.is_internal && !expandedInternalIds.value.includes(comment.id)

type Translation = { text: string; language: string | null }

const translations = ref<Record<string, Translation>>({})
const translatingKey = ref<string | null>(null)

const translate = async (key: string, routeName: string, id: number) => {
    translatingKey.value = key
    try {
        const { data } = await axios.post(route(routeName, id))
        translations.value = { ...translations.value, [key]: data }
    } catch {
        notify({ title: ctrans("Translation failed"), text: ctrans("Please try again"), type: "error" })
    } finally {
        translatingKey.value = null
    }
}

const translateComment = (id: number) => translate(`comment-${id}`, props.translateRoutes!.comment, id)

const translateDescription = () => translate("description", props.translateRoutes!.ticket, props.ticket.id!)

const toggleVisibility = (id: number) => {
    router.patch(route("grp.models.ticket.comment.toggle_visibility", id), {}, { preserveScroll: true })
}

const submit = () => {
    form.post(route(props.commentRoute.name, props.commentRoute.parameters), {
        onError: (errors) => notify({ title: ctrans("Comment not posted"), text: [...new Set(Object.values(errors))].join("<br>"), type: "error" }),
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => form.reset(),
    })
}
</script>

<template>
    <div class="space-y-4">
        <div v-if="showDescription" class="bg-white rounded-lg border-2 border-[--app-accent-muted] p-5 shadow-sm">
            <div class="text-xs text-gray-500 mb-3 pb-2 border-b border-gray-200 flex flex-wrap items-center gap-x-2 gap-y-1" :class="labelReporterOnMobile && 'max-lg:-mx-5 max-lg:px-5'">
                <span v-if="labelReporterOnMobile" class="w-full text-[10px] font-medium uppercase tracking-wide text-gray-400 lg:hidden">{{ ctrans("Reporter") }}</span>
                <TicketUserHoverCard
                    :name="ticket.reporter"
                    :avatar="ticket.reporter_avatar"
                    :roles="ticket.reporter_roles ?? []"
                    :username="ticket.reporter_username"
                    :reporterKey="ticket.reporter_key"
                    :profileUrl="ticket.reporter_profile_url"
                    size="sm"
                    @mention="mentionInReply" />
                <span>· {{ useFormatTime(ticket.created_at, { formatTime: "PP, HH:mm:ss zzz" }) }}</span>
                <span class="text-gray-400">({{ daysAgo(ticket.created_at) }})</span>
                <FontAwesomeIcon v-if="ticket.is_from_slack" v-tooltip="ctrans('Raised from Slack')" :icon="faSlack" class="text-gray-500" fixed-width />
            </div>
            <slot name="card-header-footer" />
            <h2 class="text-lg font-semibold mb-3">{{ ticket.subject }}</h2>
            <a v-if="ticket.reference_url" :href="ticket.reference_url" target="_blank" rel="noopener" class="mb-3 block truncate text-sm text-[--app-accent-strong] hover:underline">{{ ticket.reference_url }}</a>
            <TicketBody v-if="ticket.description || ticket.images?.length" :text="ticket.description" :images="ticket.images" />
            <TicketTranslation v-if="translateRoutes && ticket.id && ticket.description" :translation="translations.description" :is-translating="translatingKey === 'description'" @translate="translateDescription" />
            <p v-else class="text-sm text-gray-400">{{ ctrans("No description") }}</p>
        </div>

        <slot name="after-description" />

        <slot name="before-comments" />

        <form v-show="showComments" class="space-y-3 rounded-lg border p-4 transition duration-200" :class="[form.type === 'post_mortem' ? 'border-red-300 bg-red-50' : form.is_internal ? 'border-amber-300 bg-amber-50' : 'border-gray-300 bg-white', form.processing && 'pointer-events-none opacity-75']" :aria-busy="form.processing" @submit.prevent="submit">
            <TicketComposer ref="composer" v-model:body="form.body" v-model:images="form.images" :rows="4" :mentionable="form.is_internal ? mentionable?.filter((person) => !person.is_customer) : mentionable" :placeholder="ctrans('Write a comment, paste a screenshot or drop images')" />
            <p v-if="form.errors.body || form.errors.images" class="text-xs text-red-600">{{ form.errors.body || form.errors.images }}</p>
            <div class="flex flex-wrap items-center justify-end gap-3">
                <label v-if="canCommentInternally" class="mr-auto flex cursor-pointer select-none items-center gap-2 text-sm transition duration-200" :class="form.is_internal ? 'font-semibold text-amber-700' : 'text-gray-500 hover:text-gray-700'">
                    <input v-model="form.is_internal" type="checkbox" class="cursor-pointer rounded border-gray-300 text-amber-500 focus:ring-amber-400" />
                    {{ ctrans("Engineering note") }}
                    <span class="text-xs font-normal text-gray-400">{{ ctrans("staff only, shown collapsed") }}</span>
                </label>
                <label v-if="canCommentInternally" class="flex cursor-pointer select-none items-center gap-2 text-sm transition duration-200" :class="form.type === 'post_mortem' ? 'font-semibold text-red-700' : 'text-gray-500 hover:text-gray-700'">
                    <input v-model="form.type" type="checkbox" true-value="post_mortem" false-value="comment" class="cursor-pointer rounded border-gray-300 text-red-500 focus:ring-red-400" />
                    {{ ctrans("Incident post-mortem") }}
                </label>
                <Button :label="form.is_internal ? ctrans('Add engineering note') : ctrans('Comment')" :loading="form.processing" :disabled="!form.body.trim() && !form.images.length" @click="submit" />
            </div>
        </form>

        <div v-if="comments.length > 1" v-show="showComments" class="ml-6 flex justify-end text-xs text-gray-500">
            <button type="button" class="px-1 py-0.5 hover:text-gray-900" :title="ctrans('Sort comments')" @click="toggleCommentOrder">
                {{ isNewestFirst ? "↓" : "↑" }} {{ isNewestFirst ? ctrans("Newest first") : ctrans("Oldest first") }}
            </button>
        </div>

        <div v-show="showComments" class="ml-6 space-y-3 border-l-2 border-gray-200 pl-4">
            <div
                v-for="comment in sortedComments"
                :key="comment.id"
                class="rounded-md border px-3 py-2 text-sm"
                :class="comment.type === 'post_mortem' ? 'bg-red-50 border-red-200' : comment.has_qa_verdict ? 'bg-purple-50 border-purple-200' : comment.is_lead_only ? 'bg-rose-50 border-rose-200' : comment.is_internal ? 'bg-amber-50 border-amber-200' : comment.is_staff ?'bg-gray-50 border-gray-200' : 'bg-blue-50 border-blue-200'"
            >
                <div class="text-xs text-gray-500 mb-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span v-if="comment.author" class="flex items-center gap-1.5 font-medium text-gray-700">
                        <TicketUserHoverCard
                            :name="comment.author"
                            :avatar="comment.author_avatar"
                            :roles="comment.author_roles ?? []"
                            :username="comment.author_username"
                            :reporterKey="comment.author_key"
                            :profileUrl="comment.author_profile_url"
                            size="xs"
                            @mention="mentionInReply" />
                        ·
                    </span>
                    <span v-else class="flex items-center gap-2"><img class="h-4 select-none" src="/art/invader.svg" alt="aiku" /> ·</span>
                    {{ useFormatTime(comment.created_at, { formatTime: "hm" }) }}
                    <span v-if="comment.has_qa_verdict" class="px-1.5 py-0.5 rounded text-[10px] font-medium flex items-center gap-1" :class="qaVerdictBadgeClass[comment.has_qa_verdict] ?? 'bg-purple-200 text-purple-900'">
                        <FontAwesomeIcon :icon="qaVerdictIcon[comment.has_qa_verdict] ?? 'fal fa-vial'" fixed-width />
                        {{ ctrans("QA") }} · {{ comment.qa_verdict_label ?? comment.has_qa_verdict }}
                    </span>
                    <span v-if="comment.type === 'post_mortem'" class="px-1.5 py-0.5 rounded bg-red-200 text-red-900 text-[10px] font-medium">{{ ctrans("Incident post-mortem") }}</span>
                    <span v-if="comment.is_internal" class="px-1.5 py-0.5 rounded bg-amber-200 text-amber-900 text-[10px] font-medium">{{ ctrans("Engineering note") }}</span>
                    <span v-if="comment.is_lead_only" class="px-1.5 py-0.5 rounded bg-rose-200 text-rose-900 text-[10px] font-medium">{{ ctrans("Lead engineers only") }}</span>
                    <span class="ml-auto flex items-center gap-1">
                        <Button v-if="comment.can_toggle_visibility" type="tertiary" size="xs" :disabled="isCommentBusy(comment.id)" :label="comment.is_lead_only ? ctrans('Unhide') : ctrans('Hide')" @click="toggleVisibility(comment.id)" />
                        <button v-if="comment.can_edit" v-tooltip="ctrans('Edit')" type="button" class="p-1 text-gray-500 hover:text-gray-800 disabled:opacity-40 disabled:hover:text-gray-500" :disabled="isCommentBusy(comment.id)" @click="startEdit(comment)">
                            <FontAwesomeIcon :icon="savingEditId === comment.id ? 'fal fa-spinner' : 'fal fa-pencil'" :spin="savingEditId === comment.id" fixed-width />
                        </button>
                        <ModalConfirmation
                            v-if="comment.can_delete"
                            :title="ctrans('Delete this comment?')"
                            :description="ctrans('The comment will be removed from the ticket.')">
                            <template #default="{ changeModel }">
                                <button v-tooltip="ctrans('Delete')" type="button" class="p-1 text-gray-500 hover:text-red-600 disabled:opacity-40 disabled:hover:text-gray-500" :disabled="isCommentBusy(comment.id)" @click="changeModel">
                                    <FontAwesomeIcon :icon="deletingId === comment.id ? 'fal fa-spinner' : 'fal fa-trash-alt'" :spin="deletingId === comment.id" fixed-width />
                                </button>
                            </template>
                            <template #btn-yes="{ closeModal }">
                                <Button type="red" :label="ctrans('Yes, delete')" :loading="deletingId === comment.id" @click="deleteComment(comment.id, closeModal)" />
                            </template>
                        </ModalConfirmation>
                    </span>
                </div>
                <div v-if="editingId === comment.id" class="space-y-2">
                    <TicketComposer class="mt-2" v-model:body="editBody" v-model:images="editImages" :rows="4" :mentionable="mentionable" :max-images="Math.max(0, 5 - keptEditImages(comment).length - keptEditAttachments(comment).length)" />
                    <div v-if="keptEditImages(comment).length || keptEditAttachments(comment).length" class="flex flex-wrap gap-1.5">
                        <span v-for="image in keptEditImages(comment)" :key="image.ulid" class="relative">
                            <img :src="image.original" :alt="image.name" class="h-12 w-12 rounded border border-gray-200 object-cover" />
                            <ModalConfirmation v-if="image.ulid" :title="ctrans('Remove this image?')" :description="ctrans('It will be gone once you save. Cancelling the edit keeps it.')">
                                <template #default="{ changeModel }">
                                    <button v-tooltip="ctrans('Remove')" type="button" class="absolute -top-1.5 -right-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-gray-500 text-white hover:bg-red-500" @click="changeModel">
                                        <FontAwesomeIcon icon="fal fa-times" class="text-[9px]" fixed-width aria-hidden="true" />
                                    </button>
                                </template>
                                <template #btn-yes="{ closeModal }">
                                    <Button type="red" :label="ctrans('Remove')" @click="confirmRemoveMedia(image.ulid, closeModal)" />
                                </template>
                            </ModalConfirmation>
                        </span>
                        <span v-for="file in keptEditAttachments(comment)" :key="file.ulid" class="relative">
                            <span class="flex h-12 w-12 flex-col items-center justify-center rounded border border-gray-200 bg-gray-50" :class="attachmentIconFor(file).class">
                                <FontAwesomeIcon :icon="attachmentIconFor(file).icon" class="text-lg" fixed-width aria-hidden="true" />
                                <span class="w-full truncate px-0.5 text-center text-[9px] text-gray-500">{{ file.name }}</span>
                            </span>
                            <ModalConfirmation v-if="file.ulid" :title="ctrans('Remove this attachment?')" :description="ctrans('It will be gone once you save. Cancelling the edit keeps it.')">
                                <template #default="{ changeModel }">
                                    <button v-tooltip="ctrans('Remove')" type="button" class="absolute -top-1.5 -right-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-gray-500 text-white hover:bg-red-500" @click="changeModel">
                                        <FontAwesomeIcon icon="fal fa-times" class="text-[9px]" fixed-width aria-hidden="true" />
                                    </button>
                                </template>
                                <template #btn-yes="{ closeModal }">
                                    <Button type="red" :label="ctrans('Remove')" @click="confirmRemoveMedia(file.ulid, closeModal)" />
                                </template>
                            </ModalConfirmation>
                        </span>
                    </div>
                    <div class="flex gap-2 justify-end">
                        <Button type="tertiary" :label="ctrans('Cancel')" :disabled="savingEditId === comment.id" @click="editingId = null" />
                        <Button :label="ctrans('Save')" :loading="savingEditId === comment.id" @click="saveEdit(comment.id)" />
                    </div>
                </div>
                <button v-else-if="isCollapsed(comment)" type="button" class="flex w-full items-center gap-2 text-left text-gray-600 hover:text-gray-900" @click="toggleInternalExpanded(comment.id)">
                    <span class="truncate">{{ comment.body.trim().split("\n")[0] || ctrans("Attachments") }}</span>
                    <span class="shrink-0 text-xs text-amber-700">{{ ctrans("Show more") }}</span>
                </button>
                <template v-else>
                    <TicketBody :text="comment.body" :images="comment.images" :attachments="comment.attachments" />
                    <TicketTranslation v-if="translateRoutes" :translation="translations[`comment-${comment.id}`]" :is-translating="translatingKey === `comment-${comment.id}`" @translate="translateComment(comment.id)" />
                    <button v-if="comment.is_internal" type="button" class="mt-1 text-xs text-amber-700 hover:text-amber-900" @click="toggleInternalExpanded(comment.id)">{{ ctrans("Show less") }}</button>
                </template>
            </div>
        </div>
    </div>
</template>
