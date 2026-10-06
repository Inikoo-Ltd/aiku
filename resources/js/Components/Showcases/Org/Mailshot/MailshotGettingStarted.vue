<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import Dialog from 'primevue/dialog'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faFileAlt, faThLarge, faHistory, faSearch, faEye, faArrowRight, faCheck, faPencil, faStore, faEnvelope, faExclamationTriangle,
} from '@fal'
import { ctrans } from '@/Composables/useTrans'
import { useFormatTime } from '@/Composables/useFormatTime'
import { routeType } from '@/types/route'

library.add(faFileAlt, faThLarge, faHistory, faSearch, faEye, faArrowRight, faCheck, faPencil, faStore, faEnvelope, faExclamationTriangle)

interface MailshotTemplate {
    id: number
    slug: string
    name?: string
    subject?: string
    shop_name?: string
    compiled_layout: string
    created_at?: string
}

const props = defineProps<{
    subject: string | null
    isComposed: boolean
    workshopRoute?: routeType
    ownShopTemplates?: MailshotTemplate[]
    otherShopTemplates?: MailshotTemplate[]
}>()

const templateSource = ref<'own' | 'other'>((props.ownShopTemplates?.length ?? 0) > 0 || !(props.otherShopTemplates?.length) ? 'own' : 'other')
const search = ref('')
const previewTemplate = ref<MailshotTemplate | null>(null)
const isPreviewOpen = computed({
    get: () => !!previewTemplate.value,
    set: (isOpen: boolean) => {
        if (!isOpen) {
            previewTemplate.value = null
        }
    },
})

const templates = computed(() => (templateSource.value === 'own' ? props.ownShopTemplates : props.otherShopTemplates) ?? [])

const filteredTemplates = computed(() => {
    const query = search.value.trim().toLowerCase()
    if (!query) {
        return templates.value
    }

    return templates.value.filter((template) =>
        [template.name, template.subject, template.shop_name].some((value) => String(value ?? '').toLowerCase().includes(query))
    )
})

const templateTitle = (template: MailshotTemplate) => template.name || template.subject || ctrans('Untitled template')

const workshopUrl = (query: Record<string, string | number> = {}) =>
    props.workshopRoute ? route(props.workshopRoute.name, { ...props.workshopRoute.parameters, ...query }) : '#'

const steps = computed(() => [
    { key: 'compose', label: ctrans('Compose the email'), hint: props.isComposed ? ctrans('Composed, not published yet') : ctrans('Start from scratch or a template') },
    { key: 'recipients', label: ctrans('Choose recipients'), hint: ctrans('Who will receive it') },
    { key: 'send', label: ctrans('Review & send'), hint: ctrans('Send now or schedule') },
])

const templatesSection = ref<HTMLElement | null>(null)
const scrollToTemplates = () => templatesSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
</script>

<template>
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col gap-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <span class="inline-flex items-center gap-x-1.5 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-200">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400" />
                    {{ ctrans('Draft') }}
                </span>
                <h2 class="mt-2 truncate text-xl font-semibold text-gray-900">{{ subject || ctrans('Untitled mailshot') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ ctrans('Three steps until this mailshot lands in your customers\' inboxes.') }}</p>
            </div>

            <ol class="flex shrink-0 flex-col gap-3 sm:flex-row sm:gap-0">
                <li v-for="(step, index) in steps" :key="step.key" class="flex items-center">
                    <div class="flex items-center gap-x-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                            :class="index === 0 ? 'bg-[var(--theme-color-4)] text-[var(--theme-color-5)] shadow-sm' : 'bg-gray-100 text-gray-500'">
                            {{ index + 1 }}
                        </span>
                        <div class="min-w-0">
                            <div class="text-sm font-medium" :class="index === 0 ? 'text-gray-900' : 'text-gray-500'">{{ step.label }}</div>
                            <div class="text-xs text-gray-400">{{ step.hint }}</div>
                        </div>
                    </div>
                    <span v-if="index < steps.length - 1" class="mx-4 hidden h-px w-10 bg-gray-200 sm:block" />
                </li>
            </ol>
        </section>

        <section v-if="isComposed" class="flex flex-col gap-4 rounded-xl border border-amber-200 bg-amber-50 p-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-x-3">
                <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5 text-xl text-amber-500" fixed-width aria-hidden="true" />
                <div>
                    <h3 class="font-semibold text-amber-900">{{ ctrans('Your email is composed but not published yet') }}</h3>
                    <p class="text-sm text-amber-800">{{ ctrans('Open the workshop and press Ctrl+S (⌘S on Mac) to publish it. Then it can be sent.') }}</p>
                </div>
            </div>
            <Link :href="workshopUrl()"
                class="inline-flex shrink-0 items-center gap-x-2 rounded-md bg-[var(--theme-color-4)] px-4 py-2 text-sm font-medium text-[var(--theme-color-5)] shadow-sm hover:bg-[color-mix(in_srgb,var(--theme-color-4)_85%,black)]">
                <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                {{ ctrans('Continue editing') }}
            </Link>
        </section>

        <template v-else>
            <section>
                <h3 class="mb-3 text-base font-semibold text-gray-900">{{ ctrans('How do you want to start?') }}</h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <Link :href="workshopUrl({ blank: 1 })"
                        class="group flex flex-col gap-y-2 rounded-xl border border-gray-200 bg-white p-5 transition hover:border-[var(--theme-color-4)] hover:shadow-md">
                        <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-gray-100 text-xl text-gray-600 group-hover:bg-[color-mix(in_srgb,var(--theme-color-4)_12%,white)] group-hover:text-[var(--theme-color-4)]">
                            <FontAwesomeIcon icon="fal fa-file-alt" fixed-width aria-hidden="true" />
                        </span>
                        <span class="font-semibold text-gray-900">{{ ctrans('Start from scratch') }}</span>
                        <span class="text-sm text-gray-500">{{ ctrans('Open an empty email and build it block by block.') }}</span>
                    </Link>
                    <button type="button"
                        class="group flex flex-col gap-y-2 rounded-xl border border-gray-200 bg-white p-5 text-left transition hover:border-[var(--theme-color-4)] hover:shadow-md"
                        @click="scrollToTemplates">
                        <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-gray-100 text-xl text-gray-600 group-hover:bg-[color-mix(in_srgb,var(--theme-color-4)_12%,white)] group-hover:text-[var(--theme-color-4)]">
                            <FontAwesomeIcon icon="fal fa-th-large" fixed-width aria-hidden="true" />
                        </span>
                        <span class="font-semibold text-gray-900">{{ ctrans('Pick a template') }}</span>
                        <span class="text-sm text-gray-500">{{ ctrans('Choose a ready-made design below and adjust it.') }}</span>
                    </button>
                    <Link :href="workshopUrl()"
                        class="group flex flex-col gap-y-2 rounded-xl border border-gray-200 bg-white p-5 transition hover:border-[var(--theme-color-4)] hover:shadow-md">
                        <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-gray-100 text-xl text-gray-600 group-hover:bg-[color-mix(in_srgb,var(--theme-color-4)_12%,white)] group-hover:text-[var(--theme-color-4)]">
                            <FontAwesomeIcon icon="fal fa-history" fixed-width aria-hidden="true" />
                        </span>
                        <span class="font-semibold text-gray-900">{{ ctrans('Copy a previous mailshot') }}</span>
                        <span class="text-sm text-gray-500">{{ ctrans('Reuse an email you already sent, from this or another shop.') }}</span>
                    </Link>
                </div>
            </section>

            <section ref="templatesSection" class="scroll-mt-4 rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                    <div class="flex items-center gap-x-3">
                        <h3 class="text-base font-semibold text-gray-900">{{ ctrans('Templates') }}</h3>
                        <div class="flex rounded-lg bg-gray-100 p-1 text-sm">
                            <button type="button" class="flex items-center gap-x-1.5 rounded-md px-3 py-1 transition"
                                :class="templateSource === 'own' ? 'bg-white font-medium text-[var(--theme-color-4)] shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                                @click="templateSource = 'own'">
                                <FontAwesomeIcon icon="fal fa-store" fixed-width aria-hidden="true" />
                                {{ ctrans('Your templates') }}
                                <span class="text-xs text-gray-400">{{ ownShopTemplates?.length ?? 0 }}</span>
                            </button>
                            <button type="button" class="flex items-center gap-x-1.5 rounded-md px-3 py-1 transition"
                                :class="templateSource === 'other' ? 'bg-white font-medium text-[var(--theme-color-4)] shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                                @click="templateSource = 'other'">
                                {{ ctrans('Other shops') }}
                                <span class="text-xs text-gray-400">{{ otherShopTemplates?.length ?? 0 }}</span>
                            </button>
                        </div>
                    </div>
                    <div class="relative w-full sm:w-64">
                        <FontAwesomeIcon icon="fal fa-search" class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400" fixed-width aria-hidden="true" />
                        <input v-model="search" type="search" :placeholder="ctrans('Search templates')" :aria-label="ctrans('Search templates')"
                            class="w-full rounded-lg border-gray-300 py-1.5 pl-9 pr-3 text-sm focus:border-[var(--theme-color-4)] focus:ring-[var(--theme-color-4)]" />
                    </div>
                </div>

                <div class="bg-gray-50 p-5">
                    <div v-if="!filteredTemplates.length" class="flex flex-col items-center justify-center py-12 text-center">
                        <FontAwesomeIcon icon="fal fa-envelope" class="mb-3 text-4xl text-gray-300" fixed-width aria-hidden="true" />
                        <p class="text-sm font-medium text-gray-700">
                            {{ templates.length ? ctrans('No templates match your search') : ctrans('No templates here yet') }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">{{ ctrans('You can always start from scratch.') }}</p>
                    </div>

                    <div v-else class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
                        <div v-for="template in filteredTemplates" :key="template.id"
                            class="group flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white transition hover:border-[var(--theme-color-4)] hover:shadow-md">
                            <button type="button" class="relative h-64 overflow-hidden border-b border-gray-100 bg-white" @click="previewTemplate = template">
                                <iframe :srcdoc="template.compiled_layout" sandbox="" loading="lazy" tabindex="-1" :title="templateTitle(template)"
                                    class="pointer-events-none h-[640px] w-[250%] origin-top-left scale-[0.4] border-0" />
                                <span class="absolute inset-0 flex items-center justify-center bg-black/0 opacity-0 transition group-hover:bg-black/30 group-hover:opacity-100">
                                    <span class="flex items-center gap-x-1.5 rounded-md bg-white px-3 py-1.5 text-xs font-medium text-gray-800 shadow">
                                        <FontAwesomeIcon icon="fal fa-eye" fixed-width aria-hidden="true" />
                                        {{ ctrans('Preview') }}
                                    </span>
                                </span>
                            </button>
                            <div class="flex flex-1 flex-col gap-y-2 p-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-medium text-gray-900" :title="templateTitle(template)">{{ templateTitle(template) }}</div>
                                    <div class="truncate text-[11px] text-gray-500">
                                        <span v-if="templateSource === 'other' && template.shop_name">{{ template.shop_name }} &bull; </span>
                                        <span v-if="template.created_at">{{ useFormatTime(template.created_at) }}</span>
                                    </div>
                                </div>
                                <Link :href="workshopUrl({ template: template.slug })"
                                    class="mt-auto inline-flex items-center justify-center gap-x-1.5 rounded-md border border-[var(--theme-color-4)] px-3 py-1.5 text-xs font-medium text-[var(--theme-color-4)] transition hover:bg-[var(--theme-color-4)] hover:text-[var(--theme-color-5)]">
                                    {{ ctrans('Use template') }}
                                    <FontAwesomeIcon icon="fal fa-arrow-right" fixed-width aria-hidden="true" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </template>

        <Dialog v-model:visible="isPreviewOpen" modal dismissableMask :draggable="false" :header="previewTemplate ? templateTitle(previewTemplate) : ''"
            :style="{ width: '56rem' }" :breakpoints="{ '960px': '95vw' }"
            :pt="{ header: { class: '!px-5 !py-3 border-b border-gray-200' }, content: { class: '!p-0' }, footer: { class: '!px-5 !py-3 border-t border-gray-200' } }">
            <div class="flex h-[70vh] justify-center bg-gray-100 p-4">
                <iframe v-if="previewTemplate" :srcdoc="previewTemplate.compiled_layout" sandbox="" :title="ctrans('Template preview')"
                    class="h-full w-full border-0 bg-white shadow-sm" />
            </div>
            <template #footer>
                <div class="flex w-full items-center justify-end gap-x-2">
                    <button type="button" class="h-9 rounded-md border border-gray-300 px-4 text-sm text-gray-700 hover:bg-gray-50" @click="previewTemplate = null">
                        {{ ctrans('Close') }}
                    </button>
                    <Link v-if="previewTemplate" :href="workshopUrl({ template: previewTemplate.slug })"
                        class="inline-flex h-9 items-center gap-x-2 rounded-md bg-[var(--theme-color-4)] px-5 text-sm font-medium text-[var(--theme-color-5)] hover:bg-[color-mix(in_srgb,var(--theme-color-4)_85%,black)]">
                        {{ ctrans('Use this template') }}
                        <FontAwesomeIcon icon="fal fa-arrow-right" fixed-width aria-hidden="true" />
                    </Link>
                </div>
            </template>
        </Dialog>
    </div>
</template>
