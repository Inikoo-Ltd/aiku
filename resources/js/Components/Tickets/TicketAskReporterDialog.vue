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
    updateRoute: { name: string; parameters: Record<string, unknown> }
    defaultWaitingHours?: number | null
}>()

const emit = defineEmits<{
    (e: "updated"): void
}>()

const visible = defineModel<boolean>("visible", { default: false })

const waitingPresets = [
    { label: trans("2 hours"), hours: 2 },
    { label: trans("1 day"), hours: 24 },
    { label: trans("2 days"), hours: 48 },
    { label: trans("3 days"), hours: 72 },
    { label: trans("14 days"), hours: 336 },
]

const question = ref("")
const waitingHours = ref(72)
const isAsking = ref(false)

watch(visible, (isVisible) => {
    if (!isVisible) return
    question.value = ""
    waitingHours.value = props.defaultWaitingHours ?? 72
})

const askReporter = () => {
    router.patch(
        route(props.updateRoute.name, props.updateRoute.parameters),
        { status: "waiting", question: question.value, waiting_hours: waitingHours.value },
        {
            preserveScroll: true,
            onStart: () => (isAsking.value = true),
            onFinish: () => (isAsking.value = false),
            onSuccess: () => {
                visible.value = false
                emit("updated")
            },
        }
    )
}
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="trans('Ask reporter')" :style="{ width: '32rem' }">
        <div class="space-y-4 text-sm">
            <div>
                <p class="mb-1 text-xs text-gray-500">{{ trans("What do we need to continue?") }}</p>
                <textarea v-model="question" rows="5" class="w-full rounded border-gray-300 text-sm" :placeholder="trans('e.g. please send the order number and a screenshot of the error')" />
            </div>
            <div>
                <p class="mb-1 text-xs text-gray-500">{{ trans("Cancel the ticket if there is no reply in") }}</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="preset in waitingPresets"
                        :key="preset.hours"
                        type="button"
                        class="rounded-full border px-3 py-1 transition duration-200"
                        :class="waitingHours === preset.hours ? 'border-[--app-accent] bg-[--app-accent-soft] text-[--app-accent-strong]' : 'border-gray-300 text-gray-600 hover:bg-gray-50 active:!bg-gray-100'"
                        @click="waitingHours = preset.hours">
                        {{ preset.label }}
                    </button>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <Button type="tertiary" :label="trans('Cancel')" @click="visible = false" />
                <Button :label="trans('Send and wait')" icon="fal fa-question-circle" :loading="isAsking" :disabled="!question.trim()" @click="askReporter" />
            </div>
        </div>
    </Dialog>
</template>
