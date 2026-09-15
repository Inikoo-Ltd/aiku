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

const props = defineProps<{
    status: "resolved" | "cancelled"
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
    <Dialog v-model:visible="visible" modal :header="status === 'cancelled' ? trans('Cancel ticket') : trans('Mark as done')" :style="{ width: '32rem' }">
        <div class="space-y-4 text-sm">
            <div>
                <p class="mb-1 text-xs text-gray-500">{{ status === "cancelled" ? trans("Why is this ticket being cancelled?") : trans("What was done?") }}</p>
                <textarea v-model="statusNote" rows="5" class="w-full rounded border-gray-300 text-sm" :placeholder="status === 'cancelled' ? trans('e.g. duplicate of HELP-12, following up there') : trans('e.g. fixed the rounding in the invoice totals')" />
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
                    :type="status === 'cancelled' ? 'negative' : 'primary'"
                    :label="status === 'cancelled' ? trans('Cancel ticket') : trans('Done')"
                    :icon="status === 'cancelled' ? 'fal fa-ban' : 'fal fa-check'"
                    :loading="isSendingStatusNote"
                    :disabled="!statusNote.trim()"
                    @click="sendStatusNote()" />
            </div>
        </div>
    </Dialog>
</template>
