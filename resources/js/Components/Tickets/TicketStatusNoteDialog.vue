<!--
 Author Louis Perez
 Created on 15-09-2026-10h-01m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { Dialog } from "primevue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TicketComposer from "@/Components/Tickets/TicketComposer.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExclamationTriangle } from "@fal"

library.add(faExclamationTriangle)

const copy = {
    resolved: { header: ctrans("Mark as done"), question: ctrans("What was done?"), placeholder: ctrans("e.g. fixed the rounding in the invoice totals"), label: ctrans("Done"), icon: "fal fa-check", type: "primary" },
    cancelled: { header: ctrans("Cancel ticket"), question: ctrans("Why is this ticket being cancelled?"), placeholder: ctrans("e.g. duplicate of HELP-12, following up there"), label: ctrans("Cancel ticket"), icon: "fal fa-ban", type: "negative" },
    answered: { header: ctrans("Reopen ticket"), question: ctrans("What is still wrong?"), placeholder: ctrans("e.g. the total is still off on invoice 1234"), label: ctrans("Reopen"), icon: "fal fa-undo", type: "primary" },
}

const props = defineProps<{
    status: "resolved" | "cancelled" | "answered"
    updateRoute: { name: string; parameters: Record<string, unknown> }
    canWaitForDeployment?: boolean
    closesConversation?: boolean
    mentionable?: { username: string; name: string | null; suggested?: boolean; is_customer?: boolean }[]
}>()

// Settling this ticket sends what is typed here to a customer, so it is said before they type
// rather than after they press the button.
const goesToTheCustomer = computed(() => !!props.closesConversation && ["resolved", "cancelled"].includes(props.status))

const emit = defineEmits<{
    (e: "updated"): void
}>()

const visible = defineModel<boolean>("visible", { default: false })

const statusNote = ref("")
const statusNoteImages = ref<File[]>([])
const statusNoteError = ref("")
const deployCommit = ref("")
const deployCommitError = ref("")
const isSendingStatusNote = ref(false)

watch(visible, (isVisible) => {
    if (isVisible) {
        statusNote.value = ""
        statusNoteImages.value = []
        statusNoteError.value = ""
        deployCommit.value = ""
        deployCommitError.value = ""
    }
})

const sendStatusNote = (isWaitingForDeployment = false) => {
    router.post(
        route(props.updateRoute.name, props.updateRoute.parameters),
        {
            _method: "patch",
            ...(isWaitingForDeployment
                ? { status: "pending_deploy", question: statusNote.value, deploy_commit: deployCommit.value.trim() || null }
                : { status: props.status, status_comment: statusNote.value }),
            images: statusNoteImages.value,
        },
        {
            preserveScroll: true,
            forceFormData: true,
            onStart: () => {
                isSendingStatusNote.value = true
                deployCommitError.value = ""
                statusNoteError.value = ""
            },
            onError: (errors) => {
                deployCommitError.value = errors.deploy_commit ?? ""
                statusNoteError.value = Object.entries(errors).find(([key]) => key.startsWith("images"))?.[1] ?? ""
            },
            onFinish: () => (isSendingStatusNote.value = false),
            onSuccess: () => {
                visible.value = false
                emit("updated")
            },
        }
    )
}
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="copy[status].header" :style="{ width: '32rem' }">
        <div class="space-y-4 text-sm">
            <div>
                <p class="mb-1 text-xs text-gray-500">{{ copy[status].question }}</p>
                <TicketComposer v-model:body="statusNote" v-model:images="statusNoteImages" :mentionable="mentionable" :placeholder="copy[status].placeholder" />
                <p v-if="statusNoteError" class="mt-1 text-xs text-red-600">{{ statusNoteError }}</p>
                <p class="mt-1 text-xs text-gray-400">{{ ctrans("This is published as a comment on the ticket.") }}</p>
                <p v-if="goesToTheCustomer" class="mt-2 flex items-start gap-1.5 rounded-md border border-amber-300 bg-amber-50 px-2.5 py-2 text-xs text-amber-800">
                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5 shrink-0 text-amber-500" fixed-width aria-hidden="true" />
                    <span>{{ ctrans("The customer reads this. It is sent to them in the conversation this ticket came from, and that conversation is then closed.") }}</span>
                </p>
                <p v-if="status === 'resolved' && canWaitForDeployment" class="mt-1 text-xs text-gray-400">{{ ctrans("Use Set as Done on Next Deployment only when the fix is already on main: the ticket closes and this comment is posted after the next deployment.") }}</p>
            </div>
            <div v-if="status === 'resolved' && canWaitForDeployment">
                <p class="mb-1 text-xs text-gray-500">{{ ctrans("Fix commit (optional)") }}</p>
                <input v-model="deployCommit" type="text" maxlength="40" spellcheck="false" autocomplete="off" class="w-full rounded border-gray-300 font-mono text-sm" :placeholder="ctrans('e.g. f194aff213')" />
                <p v-if="deployCommitError" class="mt-1 text-xs text-red-600">{{ deployCommitError }}</p>
                <p v-else class="mt-1 text-xs text-gray-400">{{ ctrans("With a commit, only a deployment that includes it closes the ticket.") }}</p>
            </div>
            <div class="flex justify-end gap-2">
                <Button type="tertiary" :label="ctrans('Back')" @click="visible = false" />
                <Button
                    v-if="status === 'resolved' && canWaitForDeployment"
                    type="secondary"
                    icon="fal fa-rocket"
                    :label="ctrans('Set as Done on Next Deployment')"
                    :loading="isSendingStatusNote"
                    :disabled="!statusNote.trim()"
                    @click="sendStatusNote(true)" />
                <Button
                    :type="copy[status].type"
                    :label="copy[status].label"
                    :icon="copy[status].icon"
                    :loading="isSendingStatusNote"
                    :disabled="!statusNote.trim()"
                    @click="sendStatusNote()" />
            </div>
        </div>
    </Dialog>
</template>
