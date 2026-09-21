<!--
 Author Louis Perez
 Created on 15-09-2026-10h-01m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { Dialog } from "primevue"
import Button from "@/Components/Elements/Buttons/Button.vue"

const copy = {
    resolved: { header: trans("Mark as done"), question: trans("What was done?"), placeholder: trans("e.g. fixed the rounding in the invoice totals"), label: trans("Done"), icon: "fal fa-check", type: "primary" },
    cancelled: { header: trans("Cancel ticket"), question: trans("Why is this ticket being cancelled?"), placeholder: trans("e.g. duplicate of HELP-12, following up there"), label: trans("Cancel ticket"), icon: "fal fa-ban", type: "negative" },
    answered: { header: trans("Reopen ticket"), question: trans("What is still wrong?"), placeholder: trans("e.g. the total is still off on invoice 1234"), label: trans("Reopen"), icon: "fal fa-undo", type: "primary" },
}

const props = defineProps<{
    status: "resolved" | "cancelled" | "answered"
    updateRoute: { name: string; parameters: Record<string, unknown> }
    canWaitForDeployment?: boolean
}>()

const emit = defineEmits<{
    (e: "updated"): void
}>()

const visible = defineModel<boolean>("visible", { default: false })

const statusNote = ref("")
const isSendingStatusNote = ref(false)

watch(visible, (isVisible) => {
    if (isVisible) statusNote.value = ""
})

const sendStatusNote = (isWaitingForDeployment = false) => {
    router.patch(
        route(props.updateRoute.name, props.updateRoute.parameters),
        isWaitingForDeployment
            ? { status: "pending_deploy", question: statusNote.value }
            : { status: props.status, status_comment: statusNote.value },
        {
            preserveScroll: true,
            onStart: () => (isSendingStatusNote.value = true),
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
                <textarea v-model="statusNote" rows="5" class="w-full rounded border-gray-300 text-sm" :placeholder="copy[status].placeholder" />
                <p class="mt-1 text-xs text-gray-400">{{ trans("This is published as a comment on the ticket.") }}</p>
                <p v-if="status === 'resolved' && canWaitForDeployment" class="mt-1 text-xs text-gray-400">{{ trans("Use Set as Done on Next Deployment only when the fix is already on main: the ticket closes and this comment is posted after the next deployment.") }}</p>
            </div>
            <div class="flex justify-end gap-2">
                <Button type="tertiary" :label="trans('Back')" @click="visible = false" />
                <Button
                    v-if="status === 'resolved' && canWaitForDeployment"
                    type="secondary"
                    icon="fal fa-rocket"
                    :label="trans('Set as Done on Next Deployment')"
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
