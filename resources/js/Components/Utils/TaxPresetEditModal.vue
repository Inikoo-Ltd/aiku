<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { ref, watch, computed } from "vue"
import { trans } from "laravel-vue-i18n"
import Modal from "@/Components/Utils/Modal.vue"
import TaxPresetCards from "@/Components/Utils/TaxPresetCards.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowRight } from "@fal"
import { taxPresetIcon } from "@/Composables/taxPresets"

// The editor the bulk edit tab opens: the same cards as the product edit page, wrapped in
// a modal. Works for one product or a whole selection; a mixed selection starts blank.
type Progress = {
    state: string
    baskets_done: number
    baskets_total: number
    started_at: string
    pending_large?: number
}

const props = defineProps<{
    isOpen: boolean
    options: { value: string; title: string; description?: string }[]
    initial: string | null
    subjectLabel: string
    isSaving?: boolean
    /** After save the same dialog turns into the sweep progress, no second modal. */
    phase?: 'edit' | 'sweep'
    progress?: Progress | null
    /** What the sweep is changing, shown as from -> to; from is null on a mixed selection. */
    fromPreset?: string | null
    toPreset?: string | null
}>()

const emit = defineEmits<{
    (e: 'close'): void
    (e: 'save', value: string): void
}>()

const picked = ref<string>(props.initial ?? '')

watch(() => props.isOpen, (open) => {
    if (open) picked.value = props.initial ?? ''
})

const canSave = computed(() => !!picked.value && picked.value !== (props.initial ?? '') && picked.value !== 'custom')

const sweepRunning = computed(() => !!props.progress && props.progress.state !== 'finished')

const progressPct = computed(() =>
    props.progress?.baskets_total
        ? Math.round(props.progress.baskets_done / props.progress.baskets_total * 100)
        : 0)
</script>

<template>
    <Modal :isOpen="isOpen" @onClose="emit('close')" width="w-full max-w-lg">
        <div v-if="phase !== 'sweep'" class="space-y-4">
            <h3 class="text-base font-semibold text-gray-800">
                {{ trans("Tax treatment") }} — {{ subjectLabel }}
            </h3>

            <TaxPresetCards v-model="picked" :options="options" :savedValue="initial" :disabled="isSaving" />

            <p class="text-sm text-gray-500">
                {{ trans("Every open basket holding these products will be retaxed. Orders already submitted keep the tax they were sold under.") }}
            </p>

            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    @click="emit('close')"
                    class="rounded-md px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                    {{ trans("Cancel") }}
                </button>
                <button
                    type="button"
                    :disabled="!canSave || isSaving"
                    @click="emit('save', picked)"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                    <span v-if="isSaving" class="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white align-middle mr-1.5" />
                    {{ trans("Yes, change the tax") }}
                </button>
            </div>
        </div>

        <!-- Same dialog, second act: the sweep this save triggered. -->
        <div v-else class="space-y-4">
            <h3 class="text-base font-semibold text-gray-800">
                {{ sweepRunning || !progress ? trans("Retaxing open baskets…") : trans("Baskets retaxed") }}
                — {{ subjectLabel }}
            </h3>

            <div v-if="toPreset" class="flex items-center gap-2">
                <span
                    v-if="fromPreset"
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-500"
                    v-tooltip="fromPreset">
                    <FontAwesomeIcon :icon="taxPresetIcon(fromPreset)" class="h-4 w-4" />
                </span>
                <FontAwesomeIcon :icon="faArrowRight" class="h-3.5 w-3.5 text-gray-400" />
                <span
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-indigo-600"
                    v-tooltip="toPreset">
                    <FontAwesomeIcon :icon="taxPresetIcon(toPreset)" class="h-4 w-4" />
                </span>
            </div>

            <p class="text-sm text-gray-500">
                {{ trans("Open baskets holding these products are being recalculated at the new tax treatment. Orders already submitted keep the tax they were sold under.") }}
            </p>

            <div v-if="progress" class="space-y-1.5">
                <div class="flex justify-between text-sm">
                    <span :class="sweepRunning ? 'text-indigo-700' : 'text-green-700'">
                        {{ sweepRunning ? trans("In progress") : trans("Done") }}
                    </span>
                    <span class="tabular-nums text-gray-600">
                        {{ progress.baskets_done }} / {{ progress.baskets_total }}
                    </span>
                </div>
                <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all duration-300"
                        :class="sweepRunning ? 'bg-indigo-500' : 'bg-green-500'"
                        :style="{ width: progressPct + '%' }" />
                </div>
            <p
                v-if="progress && progress.state !== 'finished' && progress.pending_large"
                class="text-xs text-gray-500 italic">
                {{ trans(':n large basket(s) still processing, they take a few minutes', { n: `${progress.pending_large}` }) }}
            </p>
            </div>
            <div v-else class="flex items-center gap-2 text-sm text-gray-500">
                <span class="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-gray-300 border-t-indigo-500" />
                {{ trans("Counting the baskets…") }}
            </div>

            <div class="flex justify-end">
                <button
                    type="button"
                    @click="emit('close')"
                    class="rounded-md px-4 py-2 text-sm font-medium"
                    :class="progress && !sweepRunning
                        ? 'bg-indigo-600 text-white hover:bg-indigo-500'
                        : 'text-gray-500 hover:bg-gray-50'">
                    {{ progress && !sweepRunning ? trans("Close") : trans("Run in background") }}
                </button>
            </div>
        </div>
    </Modal>
</template>
