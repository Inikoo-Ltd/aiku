<!--
  - Author Louis Perez
  - Created on 09-11-2026-12h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { ref, watch } from "vue"
import axios from "axios"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import TrialRunFormulation, { type TrialRunFormulationForm } from "@/Components/Production/TrialRunFormulation.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLightbulbOn, faFileAlt, faFilePdf, faFlask, faClipboardList, faShieldCheck, faPaperclip, faPencil, faChevronDown } from "@fal"
library.add(faLightbulbOn, faFileAlt, faFilePdf, faFlask, faClipboardList, faShieldCheck, faPaperclip, faPencil, faChevronDown)

interface RecipeLine {
    code: string
    description: string | null
    unit: string
    quantity: number
    percent?: number | null
}

export interface TrialRunBriefData {
    development: { code: string, name: string }
    sku: { code: string, name: string, image: Record<string, string> | null }
    trial_run: string
    brief: { development: string | null, sku: string | null }
    references: { name: string | null, mime: string | null, url: string, preview_url: string }[]
    concept: null | {
        version: number
        status: string
        notes: string | null
        upload: null | { kind: string, name: string, url: string, preview_url: string }
    }
    specs: { label: string, value: string }[]
    compliance: {
        label_text: { key: string, label: string, value: string }[]
        pictograms: { label: string, icon: string }[]
        marks: { key: string, label: string }[]
        notes: { label: string, value: string }[]
    }
    formulation: null | {
        recipe: { steps: { position: number, name: string, raw_materials: RecipeLine[] }[], ingredients: RecipeLine[] }
        notes: string | null
        recorded_by: string | null
        recorded_at: string | null
    }
    form: TrialRunFormulationForm | null
}

const props = defineProps<{
    briefRoute: routeType
    refreshKey?: number
    collapsible?: boolean
}>()

const emit = defineEmits<{
    loaded: [brief: TrialRunBriefData | null]
    formulationSaved: []
}>()

const brief = ref<TrialRunBriefData | null>(null)
const loading = ref(true)
const failed = ref(false)
const editing = ref(false)
const open = ref(!props.collapsible)

async function load() {
    loading.value = true
    failed.value = false
    try {
        const { data } = await axios.get(route(props.briefRoute.name, props.briefRoute.parameters))
        brief.value = data.brief
        emit("loaded", data.brief)
    } catch {
        failed.value = true
    } finally {
        loading.value = false
    }
}

watch(() => [props.briefRoute, props.refreshKey], load, { immediate: true })

const isImage = (mime: string | null) => !!mime && mime.startsWith("image/")

function saved() {
    editing.value = false
    emit("formulationSaved")
    load()
}
</script>

<template>
    <section class="rounded-xl border border-gray-200 bg-white text-left">
        <button type="button" class="flex w-full items-center gap-3 px-4 py-3 text-left" :class="collapsible ? 'hover:bg-gray-50' : 'cursor-default'" :aria-expanded="open" @click="collapsible && (open = !open)">
            <FontAwesomeIcon icon="fal fa-lightbulb-on" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold text-gray-900">{{ ctrans("Trial run for product development") }}</div>
                <div v-if="brief" class="truncate text-xs text-gray-500">
                    {{ brief.development.code }} · {{ brief.development.name }} · {{ ctrans("Trial run :number of :sku", { number: brief.trial_run, sku: brief.sku.code }) }}
                </div>
            </div>
            <FontAwesomeIcon v-if="collapsible" icon="fal fa-chevron-down" class="text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fixed-width aria-hidden="true" />
        </button>

        <div v-if="open" class="space-y-4 border-t border-gray-100 px-4 py-4">
            <div v-if="loading && !brief" class="flex justify-center py-4"><LoadingIcon /></div>
            <p v-else-if="failed" class="text-sm text-red-600">{{ ctrans("The development information could not be loaded") }}</p>
            <p v-else-if="!brief" class="text-sm text-gray-500">{{ ctrans("This trial run is not linked to a product development any more") }}</p>
            <template v-else>
                <div>
                    <h4 class="mb-1 flex items-center gap-1.5 text-sm font-semibold text-gray-800">
                        <FontAwesomeIcon icon="fal fa-flask" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />{{ ctrans("Formulation") }}
                    </h4>
                    <TrialRunFormulation v-if="editing && brief.form" :form="brief.form" :sku="brief.sku.code" cancellable changing @saved="saved" @cancel="editing = false" />
                    <template v-else-if="brief.formulation">
                        <div v-for="step in brief.formulation.recipe.steps" :key="step.position + step.name" class="mb-2">
                            <div v-if="brief.formulation.recipe.steps.length > 1" class="text-xs font-medium text-gray-500">{{ step.name }}</div>
                            <table class="w-full text-sm">
                                <tbody>
                                    <tr v-for="line in step.raw_materials" :key="line.code" class="border-t border-gray-100">
                                        <td class="py-1 pr-3">{{ line.description }} <span class="whitespace-nowrap text-xs text-gray-400">{{ line.code }}</span></td>
                                        <td class="whitespace-nowrap py-1 text-right tabular-nums">{{ line.quantity }} {{ line.unit }}</td>
                                    </tr>
                                    <tr v-if="!step.raw_materials.length"><td class="py-1 text-xs text-gray-400">{{ ctrans("No raw materials") }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-if="brief.formulation.notes" class="whitespace-pre-line rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ brief.formulation.notes }}</p>
                        <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs text-gray-400">{{ ctrans("Recorded by :name on :date", { name: brief.formulation.recorded_by ?? "", date: brief.formulation.recorded_at ? useFormatTime(brief.formulation.recorded_at, { formatTime: "d MMM yyyy, HH:mm" }) : "" }) }}</p>
                            <Button v-if="brief.form" type="tertiary" size="xs" :label="ctrans('Change formulation')" @click="editing = true">
                                <template #icon><FontAwesomeIcon icon="fal fa-pencil" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
                            </Button>
                        </div>
                    </template>
                    <p v-else class="text-sm text-amber-700">{{ ctrans("Not recorded yet: it is the first step when the trial run starts") }}</p>
                </div>

                <div v-if="brief.brief.development || brief.brief.sku">
                    <h4 class="mb-1 flex items-center gap-1.5 text-sm font-semibold text-gray-800">
                        <FontAwesomeIcon icon="fal fa-file-alt" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />{{ ctrans("Brief") }}
                    </h4>
                    <p v-if="brief.brief.development" class="whitespace-pre-line text-sm text-gray-700">{{ brief.brief.development }}</p>
                    <div v-if="brief.brief.sku" class="mt-2 rounded-md bg-gray-50 px-3 py-2">
                        <div class="text-xs font-medium text-gray-500">{{ brief.sku.code }} · {{ brief.sku.name }}</div>
                        <p class="whitespace-pre-line text-sm text-gray-700">{{ brief.brief.sku }}</p>
                    </div>
                </div>

                <div v-if="brief.concept">
                    <h4 class="mb-1 flex items-center gap-1.5 text-sm font-semibold text-gray-800">
                        <FontAwesomeIcon icon="fal fa-lightbulb-on" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />{{ ctrans("Concept") }}
                        <span class="text-xs font-normal text-gray-500">V{{ brief.concept.version }} · {{ brief.concept.status }}</span>
                    </h4>
                    <p v-if="brief.concept.notes" class="whitespace-pre-line text-sm text-gray-700">{{ brief.concept.notes }}</p>
                    <a v-if="brief.concept.upload" :href="brief.concept.upload.preview_url" target="_blank" rel="noopener" class="mt-2 inline-block">
                        <img v-if="brief.concept.upload.kind === 'image'" :src="brief.concept.upload.preview_url" :alt="brief.concept.upload.name" class="max-h-56 rounded-md border border-gray-200 object-contain" />
                        <span v-else class="inline-flex items-center gap-1.5 text-sm text-[var(--app-accent)] hover:underline">
                            <FontAwesomeIcon icon="fal fa-file-pdf" fixed-width aria-hidden="true" />{{ brief.concept.upload.name }}
                        </span>
                    </a>
                </div>

                <div v-if="brief.specs.length">
                    <h4 class="mb-1 flex items-center gap-1.5 text-sm font-semibold text-gray-800">
                        <FontAwesomeIcon icon="fal fa-clipboard-list" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />{{ ctrans("Specifications") }}
                    </h4>
                    <dl class="grid grid-cols-[minmax(8rem,auto)_1fr] gap-x-4 gap-y-1 text-sm">
                        <template v-for="spec in brief.specs" :key="spec.label">
                            <dt class="text-gray-500">{{ spec.label }}</dt>
                            <dd class="whitespace-pre-line text-gray-800">{{ spec.value }}</dd>
                        </template>
                    </dl>
                </div>

                <div v-if="brief.compliance.label_text.length || brief.compliance.pictograms.length || brief.compliance.marks.length || brief.compliance.notes.length">
                    <h4 class="mb-1 flex items-center gap-1.5 text-sm font-semibold text-gray-800">
                        <FontAwesomeIcon icon="fal fa-shield-check" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />{{ ctrans("Compliance") }}
                    </h4>
                    <dl class="grid grid-cols-[minmax(8rem,auto)_1fr] gap-x-4 gap-y-1 text-sm">
                        <template v-for="line in [...brief.compliance.label_text, ...brief.compliance.notes]" :key="line.label">
                            <dt class="text-gray-500">{{ line.label }}</dt>
                            <dd class="whitespace-pre-line text-gray-800">{{ line.value }}</dd>
                        </template>
                    </dl>
                    <div v-if="brief.compliance.pictograms.length || brief.compliance.marks.length" class="mt-2 flex flex-wrap gap-2">
                        <figure v-for="pictogram in brief.compliance.pictograms" :key="pictogram.label" class="flex w-20 flex-col items-center gap-1 text-center">
                            <img :src="pictogram.icon" :alt="pictogram.label" class="h-10 w-10 object-contain" />
                            <figcaption class="text-[11px] leading-tight text-gray-600">{{ pictogram.label }}</figcaption>
                        </figure>
                        <span v-for="mark in brief.compliance.marks" :key="mark.key" class="self-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ mark.label }}</span>
                    </div>
                </div>

                <div v-if="brief.references.length">
                    <h4 class="mb-1 flex items-center gap-1.5 text-sm font-semibold text-gray-800">
                        <FontAwesomeIcon icon="fal fa-paperclip" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />{{ ctrans("Reference files") }}
                    </h4>
                    <div class="flex flex-wrap gap-2">
                        <a v-for="file in brief.references" :key="file.url" :href="file.preview_url" target="_blank" rel="noopener" class="flex items-center gap-2 rounded-md border border-gray-200 p-1.5 pr-3 text-sm text-gray-700 hover:border-[var(--app-accent-muted)]">
                            <img v-if="isImage(file.mime)" :src="file.preview_url" :alt="file.name ?? ''" class="h-12 w-12 rounded object-cover" />
                            <FontAwesomeIcon v-else icon="fal fa-file-pdf" class="text-gray-400" fixed-width aria-hidden="true" />
                            <span class="max-w-[12rem] truncate">{{ file.name }}</span>
                        </a>
                    </div>
                </div>
            </template>
        </div>
    </section>
</template>
