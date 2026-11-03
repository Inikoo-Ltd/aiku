<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 03 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faTimes, faCheck, faPencil, faUndo } from '@fal'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import InputNumber from 'primevue/inputnumber'
import Dialog from 'primevue/dialog'
import Button from '@/Components/Elements/Buttons/Button.vue'
import SegmentedToggle from '@/Components/Utils/SegmentedToggle.vue'
import { ctrans } from '@/Composables/useTrans'
import { useFormatTime } from '@/Composables/useFormatTime'

library.add(faTimes, faCheck, faPencil, faUndo)

type Thresholds = Record<string, number>

interface TierChange {
    applies_to: 'artefact' | 'family'
    thresholds: Thresholds | null
    previous_thresholds: Thresholds | null
    set_by: string | null
    set_at: string | null
}

const props = defineProps<{
    stepId: number
    stepName: string
    targets: { code: string, name: string, units_per_hour: number, calculated_units_per_hour: number }[]
    tiers: { source: 'artefact' | 'family' | null, artefact: Thresholds | null, family: Thresholds | null, history: TierChange[] }
    familyCode: string | null
    canEdit: boolean
    route: { name: string, parameters: object }
}>()

const bands = computed(() => props.targets.filter(target => target.code !== '0'))

const visible = ref(false)
const applyTo = ref<'artefact' | 'family'>('artefact')
const draft = ref<Thresholds>({})
const error = ref<string | null>(null)
const processing = ref(false)

const scopeOptions = computed(() => [
    { label: ctrans('This artefact'), value: 'artefact' as const },
    { label: ctrans('Family :family', { family: props.familyCode ?? '' }), value: 'family' as const },
])

const savedForScope = computed(() => props.tiers[applyTo.value])

function fillDraft() {
    error.value = null
    draft.value = Object.fromEntries(bands.value.map(band => [band.code, savedForScope.value?.[band.code] ?? band.calculated_units_per_hour]))
}

function open() {
    applyTo.value = props.tiers.source ?? 'artefact'
    fillDraft()
    visible.value = true
}

function send(thresholds: Thresholds | null) {
    processing.value = true
    error.value = null
    router.post(
        route(props.route.name, { ...props.route.parameters, manufactureTask: props.stepId }),
        { apply_to: applyTo.value, thresholds },
        {
            preserveScroll: true,
            onSuccess: () => { visible.value = false },
            onError: errors => { error.value = Object.values(errors)[0] as string },
            onFinish: () => { processing.value = false },
        }
    )
}

const describe = (thresholds: Thresholds | null) => thresholds
    ? Object.entries(thresholds).map(([band, rate]) => `${band}: ${rate}`).join(' · ')
    : ctrans('calculated')
</script>

<template>
    <div v-if="bands.length && (canEdit || tiers.source)" class="flex flex-wrap items-center gap-2 px-4 pb-3 text-xs">
        <span v-if="tiers.source" class="rounded bg-gray-100 px-1.5 py-0.5 font-medium text-gray-600">
            {{ tiers.source === 'family' ? ctrans('Edited tiers, from family :family', { family: familyCode ?? '' }) : ctrans('Edited tiers') }}
        </span>
        <Button v-if="canEdit" type="tertiary" size="xxs" :label="ctrans('Edit tiers')" @click="open">
            <template #icon><FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" /></template>
        </Button>

        <Dialog
            v-model:visible="visible"
            modal
            :header="ctrans('Pay band thresholds: :step', { step: stepName })"
            :style="{ width: '32rem' }"
            :breakpoints="{ '640px': '95vw' }">
            <div class="space-y-3 text-sm">
                <SegmentedToggle v-if="familyCode" v-model="applyTo" :options="scopeOptions" @update:modelValue="fillDraft" />

                <p class="text-xs text-gray-500">
                    {{ ctrans('Units per hour an artisan needs for each band. They apply from the start of today: today\'s sessions on this step are paid again, earlier days stay as they were paid.') }}
                    <template v-if="applyTo === 'family'">{{ ctrans('They apply to every artefact of the family on this step, except artefacts with their own edited tiers.') }}</template>
                </p>

                <table class="w-full text-xs">
                    <thead class="whitespace-nowrap text-gray-500">
                        <tr>
                            <th class="py-1 text-left font-medium">{{ ctrans('Band') }}</th>
                            <th class="py-1 text-right font-medium">{{ ctrans('Calculated') }}</th>
                            <th class="py-1 pl-3 text-right font-medium">{{ ctrans('Units per hour') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="band in bands" :key="band.code">
                            <td class="py-1">{{ band.name }}</td>
                            <td class="py-1 text-right tabular-nums text-gray-500">{{ band.calculated_units_per_hour }}</td>
                            <td class="py-1 pl-3 text-right">
                                <InputNumber
                                    v-model="draft[band.code]"
                                    :min="0.01"
                                    :minFractionDigits="0"
                                    :maxFractionDigits="2"
                                    :useGrouping="false"
                                    size="small"
                                    inputClass="w-24 text-right" />
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="error" class="text-xs text-red-600">{{ error }}</p>

                <details v-if="tiers.history.length" class="text-xs text-gray-600">
                    <summary class="cursor-pointer text-gray-500">{{ ctrans('Tier history') }}</summary>
                    <ul class="mt-1 divide-y divide-gray-100">
                        <li v-for="(change, index) in tiers.history" :key="index" class="py-1">
                            <span class="font-medium">{{ change.applies_to === 'family' ? ctrans('Family') : ctrans('Artefact') }}</span>
                            · {{ describe(change.previous_thresholds) }} → {{ describe(change.thresholds) }}
                            <span class="text-gray-400">· {{ ctrans('set by :name on :date', { name: change.set_by ?? '-', date: change.set_at ? useFormatTime(change.set_at, { formatTime: 'hm' }) : '-' }) }}</span>
                        </li>
                    </ul>
                </details>
            </div>

            <template #footer>
                <Button v-if="savedForScope" type="tertiary" :label="ctrans('Reset to calculated')" :disabled="processing" @click="send(null)">
                    <template #icon><FontAwesomeIcon icon="fal fa-undo" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" :label="ctrans('Cancel')" @click="visible = false">
                    <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" :label="ctrans('Save')" :loading="processing" @click="send(draft)">
                    <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                </Button>
            </template>
        </Dialog>
    </div>
</template>
