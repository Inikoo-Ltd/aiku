<!--
  - Author: aqordeon <dev@aw-advantage.com>
  - Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { ref, watch } from "vue"
import axios from "axios"
import { format, parseISO } from "date-fns"
import { Dialog, DatePicker, Textarea, Button } from "primevue"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    task: { reference: string; due_at: string | null; requester: { name: string } | null }
}>()

const visible = defineModel<boolean>("visible", { default: false })

const emit = defineEmits<{
    suggested: [task: any]
}>()

const today = new Date(new Date().setHours(0, 0, 0, 0))
const etaDate = ref<Date | null>(null)
const etaReason = ref("")
const isSaving = ref(false)

watch(visible, (isVisible) => {
    if (!isVisible) return
    const currentDue = props.task.due_at ? parseISO(props.task.due_at) : null
    etaDate.value = currentDue && currentDue >= today ? currentDue : null
    etaReason.value = ""
})

const requesterName = () => props.task.requester?.name ?? ctrans("the requester")

const suggest = async () => {
    if (!etaDate.value || !etaReason.value.trim() || isSaving.value) return
    isSaving.value = true
    try {
        const { data } = await axios.post(route("grp.tasks.eta_proposal.store", props.task.reference), {
            due_at: format(etaDate.value, "yyyy-MM-dd"),
            reason: etaReason.value.trim(),
        })
        visible.value = false
        emit("suggested", data.data)
        notify({ title: ctrans("New ETA suggested"), text: ctrans(":name will be asked to agree", { name: requesterName() }), type: "success" })
    } catch (error: any) {
        notify({ title: ctrans("Could not suggest a new ETA"), text: error.response?.data?.message, type: "error" })
    } finally {
        isSaving.value = false
    }
}
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        dismissableMask
        :header="ctrans('Suggest a new ETA for :reference', { reference: task.reference })"
        :style="{ width: '28rem' }"
        :breakpoints="{ '640px': '95vw' }">
        <form class="space-y-3" @submit.prevent="suggest">
            <div>
                <label :for="`staff-task-eta-date-${task.reference}`" class="mb-1 block text-sm font-semibold text-gray-700">{{ ctrans("New ETA") }}</label>
                <DatePicker v-model="etaDate" :input-id="`staff-task-eta-date-${task.reference}`" :minDate="today" dateFormat="dd/mm/yy" showIcon iconDisplay="input" fluid placeholder="dd/mm/yyyy" />
            </div>
            <div>
                <label :for="`staff-task-eta-reason-${task.reference}`" class="mb-1 block text-sm font-semibold text-gray-700">{{ ctrans("Why") }}</label>
                <Textarea :id="`staff-task-eta-reason-${task.reference}`" v-model="etaReason" rows="3" maxlength="500" autoResize fluid :placeholder="ctrans('What changed, so :name can agree', { name: requesterName() })" />
            </div>
            <div class="flex justify-end gap-x-2">
                <Button type="button" text severity="secondary" :label="ctrans('Back')" @click="visible = false" />
                <Button type="submit" :label="ctrans('Suggest')" :loading="isSaving" :disabled="!etaDate || !etaReason.trim()" />
            </div>
        </form>
    </Dialog>
</template>
