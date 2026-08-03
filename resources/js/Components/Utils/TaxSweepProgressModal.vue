<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { ref, watch, computed, onUnmounted } from "vue"
import { trans } from "laravel-vue-i18n"
import Modal from "@/Components/Utils/Modal.vue"

type Progress = {
    state: string
    baskets_done: number
    baskets_total: number
    started_at: string
    pending_large?: number
}

/**
 * Self-contained sweep progress, the pricing pattern: the modal opens when a sweep starts,
 * closing it leaves a mini bar in place that reopens it, and the parent gets told while a
 * sweep is running so it can lock the controls that would start another one.
 */
const props = defineProps<{
    masterAssetId: number | null
    initial?: Progress | null
    // When another surface (the bulk edit modal) displays the progress, this one stays a
    // silent subscriber: mini bar only, no self-opening modal.
    autoOpen?: boolean
    // And when the host draws its own in-cell progress, no mini bar either.
    mini?: boolean
}>()

const emit = defineEmits<{
    (e: 'update:running', running: boolean): void
    (e: 'progress', progress: Progress): void
}>()

const progress = ref<Progress | null>(props.initial ?? null)
const isOpen = ref(false)
// The sweep (by its start time) the user already dismissed; its later events stay in the mini bar.
const dismissedFor = ref<string | null>(props.initial?.started_at ?? null)
const subscribedTo = ref<number | null>(null)

const isRunning = computed(() => !!progress.value && progress.value.state !== 'finished')

const progressPct = computed(() =>
    progress.value?.baskets_total
        ? Math.round(progress.value.baskets_done / progress.value.baskets_total * 100)
        : 0)

watch(isRunning, (running) => emit('update:running', running), { immediate: true })

const closeModal = () => {
    isOpen.value = false
    dismissedFor.value = progress.value?.started_at ?? null
}

const unsubscribe = () => {
    if (subscribedTo.value && window.Echo) {
        window.Echo.leave(`grp.master-asset.${subscribedTo.value}`)
        subscribedTo.value = null
    }
}

watch(() => props.masterAssetId, (masterAssetId) => {
    if (masterAssetId === subscribedTo.value) return
    unsubscribe()

    if (masterAssetId && window.Echo) {
        subscribedTo.value = masterAssetId
        window.Echo.private(`grp.master-asset.${masterAssetId}`)
            .listen('.tax-preset-progress', (event: Progress) => {
                progress.value = event
                emit('progress', event)
                if (props.autoOpen !== false && event.baskets_total && event.started_at !== dismissedFor.value) {
                    isOpen.value = true
                }
            })
    }
}, { immediate: true })

onUnmounted(unsubscribe)
</script>

<template>
    <div>
        <!-- Mini bar: the sweep carries on visibly after the modal is dismissed; click reopens. -->
        <div
            v-if="mini !== false && progress && !isOpen && (isRunning || dismissedFor === progress.started_at)"
            @click="isOpen = true"
            class="mt-2 cursor-pointer rounded-md border px-3 py-2"
            :class="isRunning ? 'border-indigo-200 bg-indigo-50' : 'border-green-200 bg-green-50'">
            <div class="flex justify-between text-xs mb-1">
                <span :class="isRunning ? 'text-indigo-700' : 'text-green-700'">
                    {{ isRunning ? trans("Retaxing open baskets…") : trans("Baskets retaxed") }}
                </span>
                <span class="tabular-nums text-gray-600">{{ progress.baskets_done }} / {{ progress.baskets_total }}</span>
            </div>
            <div class="h-1.5 rounded-full bg-white overflow-hidden">
                <div
                    class="h-full rounded-full transition-all duration-300"
                    :class="isRunning ? 'bg-indigo-500' : 'bg-green-500'"
                    :style="{ width: progressPct + '%' }" />
            </div>
        </div>

        <Modal :isOpen="isOpen" @onClose="closeModal" width="w-full max-w-md">
            <div class="space-y-4">
                <h3 class="text-base font-semibold text-gray-800">
                    {{ isRunning ? trans("Retaxing open baskets…") : trans("Baskets retaxed") }}
                </h3>

                <p class="text-sm text-gray-500">
                    {{ trans("Open baskets holding this product are being recalculated at the new tax treatment. Orders already submitted keep the tax they were sold under.") }}
                </p>

                <div v-if="progress" class="space-y-1.5">
                    <div class="flex justify-between text-sm">
                        <span :class="isRunning ? 'text-indigo-700' : 'text-green-700'">
                            {{ isRunning ? trans("In progress") : trans("Done") }}
                        </span>
                        <span class="tabular-nums text-gray-600">
                            {{ progress.baskets_done }} / {{ progress.baskets_total }}
                        </span>
                    </div>
                    <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-300"
                            :class="isRunning ? 'bg-indigo-500' : 'bg-green-500'"
                            :style="{ width: progressPct + '%' }" />
                    </div>
                    <p
                        v-if="isRunning && progress?.pending_large"
                        class="text-xs text-gray-500 italic">
                        {{ trans(':n large basket(s) still processing, they take a few minutes', { n: `${progress.pending_large}` }) }}
                    </p>
                </div>

                <div class="flex justify-end">
                    <button
                        type="button"
                        @click="closeModal"
                        class="rounded-md px-4 py-2 text-sm font-medium"
                        :class="!isRunning
                            ? 'bg-indigo-600 text-white hover:bg-indigo-500'
                            : 'text-gray-500 hover:bg-gray-50'">
                        {{ !isRunning ? trans("Close") : trans("Run in background") }}
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>
