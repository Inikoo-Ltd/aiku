<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { useConfirm } from "primevue/useconfirm"
import { ctrans } from "@/Composables/useTrans"

/**
 * The pause switch that sits on an ad group, an ad or a keyword row.
 *
 * Deliberately a text button rather than a switch: a toggle that flips under the cursor and then
 * rolls back when Google refuses reads as a bug. This one shows what it will do, asks, then reports.
 */
const props = defineProps<{
    type: "ad_group" | "ad" | "keyword"
    adGroupId: string
    elementId?: string
    status: string | null
    label: string
    updateRoute: { name: string; parameters: Record<string, unknown> }
}>()

const confirm = useConfirm()
const busy = ref(false)
const error = ref<string | null>(null)

const isEnabled = computed(() => props.status === "ENABLED")

// A row Google has already removed cannot be switched, so it says so instead of offering a dead control.
const isActionable = computed(() => props.status === "ENABLED" || props.status === "PAUSED")

const toggle = () => {
    const next = isEnabled.value ? "PAUSED" : "ENABLED"

    confirm.require({
        header: isEnabled.value ? ctrans("Pause") + " " + props.label : ctrans("Resume") + " " + props.label,
        message: isEnabled.value
            ? ctrans("This stops it serving in Google Ads straight away.")
            : ctrans("This lets it serve in Google Ads straight away."),
        rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
        acceptProps: { label: isEnabled.value ? ctrans("Pause it") : ctrans("Resume it") },
        accept: () =>
            router.patch(
                route(props.updateRoute.name, props.updateRoute.parameters),
                {
                    type: props.type,
                    ad_group_id: props.adGroupId,
                    element_id: props.elementId ?? props.adGroupId,
                    status: next,
                },
                {
                    preserveScroll: true,
                    onStart: () => {
                        busy.value = true
                        error.value = null
                    },
                    onError: (errors) =>
                        (error.value = Object.values(errors as Record<string, string>)[0] ?? ctrans("That change was refused.")),
                    onFinish: () => (busy.value = false),
                }
            ),
    })
}
</script>

<template>
    <span class="inline-flex items-center gap-2">
        <button
            v-if="isActionable"
            type="button"
            :disabled="busy"
            class="rounded px-1.5 py-0.5 text-xs underline-offset-2 transition hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-50"
            :class="isEnabled ? 'text-[#a15c00]' : 'text-[#006300]'"
            @click="toggle">
            {{ busy ? ctrans("Saving") : isEnabled ? ctrans("Pause") : ctrans("Resume") }}
        </button>
        <span v-if="error" class="text-xs text-[#d03b3b]">{{ error }}</span>
    </span>
</template>
