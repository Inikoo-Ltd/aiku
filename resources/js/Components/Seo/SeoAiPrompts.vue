<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router, useForm } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import { Marked, Tokens } from "marked"
import Dialog from "primevue/dialog"
import InputChips from "primevue/inputchips"
import Select from "primevue/select"
import Textarea from "primevue/textarea"
import ToggleSwitch from "primevue/toggleswitch"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"

type Latest = {
    date: string
    model: string | null
    is_mentioned: boolean
    brand_position: number | null
    is_cited: boolean
    brands: string[]
    competitors: { name: string, is_mentioned: boolean, is_cited: boolean }[]
    our_citations: { url: string, title: string | null }[]
    citations: { url: string, domain: string, title: string | null, is_ours: boolean }[]
    answer: string | null
    check_url: string | null
}

type Prompt = {
    id: number
    prompt: string
    country_code: string
    language_code: string
    is_active: boolean
    is_pending: boolean
    runs: number
    mentioned: number
    cited: number
    latest: Latest | null
    update_route: routeType & { method: string }
    delete_route: routeType & { method: string }
}

type Option = { value: string, label: string }

type PromptsData = {
    prompts: Prompt[]
    days: number
    brandNames: string[]
    maxBrandNames: number
    brandNamesRoute: routeType & { method: string }
    storeRoute: routeType
    defaults: { country_code: string | null, language_code: string | null }
    options: { countries: Option[], languages: Option[] }
}

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    data?: PromptsData | null
    tab: string
    canEdit: boolean
}>()

const form = useForm({
    prompt: "",
    country_code: props.data?.defaults.country_code ?? null,
    language_code: props.data?.defaults.language_code ?? null,
})

const addPrompt = () => {
    form.post(route(props.data!.storeRoute.name, props.data!.storeRoute.parameters), {
        preserveScroll: true,
        only: [props.tab],
        onSuccess: () => form.reset("prompt"),
    })
}

const brandForm = useForm({ names: [...(props.data?.brandNames ?? [])] })

const saveBrandNames = () => {
    brandForm.patch(route(props.data!.brandNamesRoute.name, props.data!.brandNamesRoute.parameters), {
        preserveScroll: true,
        only: [props.tab],
    })
}

const brandNamesChanged = computed(() => JSON.stringify(brandForm.names) !== JSON.stringify(props.data?.brandNames ?? []))

const togglingId = ref<number | null>(null)

const toggle = (prompt: Prompt) => {
    router.patch(route(prompt.update_route.name, prompt.update_route.parameters), { is_active: !prompt.is_active }, {
        preserveScroll: true,
        only: [props.tab],
        onStart: () => togglingId.value = prompt.id,
        onFinish: () => togglingId.value = null,
    })
}

const remove = (prompt: Prompt) => {
    if (!window.confirm(ctrans("Delete this prompt and all its answers? Switch it off instead to keep the history."))) {
        return
    }

    router.delete(route(prompt.delete_route.name, prompt.delete_route.parameters), {
        preserveScroll: true,
        only: [props.tab],
    })
}

const shownPrompt = ref<Prompt | null>(null)

const escapeHtml = (text: string) => text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;")

const answerMarkdown = new Marked({
    renderer: {
        html: ({ text }) => escapeHtml(text),
        image: ({ text }) => escapeHtml(text),
        link(token: Tokens.Link) {
            const text = this.parser.parseInline(token.tokens)

            return /^https?:\/\//i.test(token.href)
                ? `<a href="${escapeHtml(token.href)}" target="_blank" rel="noopener noreferrer nofollow">${text}</a>`
                : text
        },
    },
})

const answerHtml = computed(() => shownPrompt.value?.latest?.answer
    ? answerMarkdown.parse(shownPrompt.value.latest.answer, { gfm: true, async: false }) as string
    : "")

const promptMarket = (prompt: Prompt) => {
    const country = props.data?.options.countries.find((option) => option.value === prompt.country_code)?.label ?? prompt.country_code
    const language = props.data?.options.languages.find((option) => option.value === prompt.language_code)?.label ?? prompt.language_code

    return `${country}, ${language}`
}
</script>

<template>
    <div v-if="data" class="space-y-4 px-4 py-4">
        <form
            v-if="canEdit"
            class="grid grid-cols-1 gap-3 rounded-xl bg-white p-4 ring-1 ring-gray-200 md:grid-cols-[minmax(0,3fr)_minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end"
            @submit.prevent="addPrompt">
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Prompt") }}</span>
                <Textarea v-model="form.prompt" rows="1" autoResize class="w-full" :placeholder="ctrans('A question a customer would ask, for example: where can I buy wholesale incense sticks in the UK?')" />
            </label>
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Country") }}</span>
                <Select v-model="form.country_code" class="h-10 w-full items-center" :options="data.options.countries" optionLabel="label" optionValue="value" filter />
            </label>
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Language") }}</span>
                <Select v-model="form.language_code" class="h-10 w-full items-center" :options="data.options.languages" optionLabel="label" optionValue="value" filter />
            </label>
            <Button class="h-10 justify-center" type="create" :label="ctrans('Add prompt')" :loading="form.processing" @click="addPrompt" />
            <p v-if="Object.keys(form.errors).length" role="alert" class="text-sm text-red-600 md:col-span-4">
                {{ Object.values(form.errors)[0] }}
            </p>
        </form>

        <section class="rounded-xl bg-white ring-1 ring-gray-200" :aria-label="ctrans('Brand names')">
            <div class="flex flex-wrap items-end gap-3 px-5 py-3">
                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-medium text-gray-900">{{ ctrans("Our brand names") }}</h2>
                    <p class="text-xs text-gray-500">{{ ctrans("An answer names us when it writes one of these or links to our domain. Spaces, hyphens and capitals do not matter; image captions do not count.") }}</p>
                    <InputChips
                        v-if="canEdit"
                        v-model="brandForm.names"
                        :allowDuplicate="false"
                        :max="data.maxBrandNames"
                        class="mt-2 w-full"
                        :placeholder="ctrans('Type a name and press Enter')"
                        :aria-label="ctrans('Our brand names')" />
                    <p v-else class="mt-1 text-sm text-gray-700">{{ data.brandNames.join(", ") }}</p>
                    <p v-if="Object.keys(brandForm.errors).length" role="alert" class="mt-1 text-sm text-red-600">{{ Object.values(brandForm.errors)[0] }}</p>
                </div>
                <Button v-if="canEdit && brandNamesChanged" type="save" :label="ctrans('Save names')" :loading="brandForm.processing" @click="saveBrandNames" />
            </div>
        </section>

        <section class="rounded-xl bg-white ring-1 ring-gray-200" :aria-label="ctrans('Prompts')">
            <div class="border-b border-gray-100 px-5 py-3">
                <h2 class="text-sm font-medium text-gray-900">{{ ctrans("Prompts") }}</h2>
                <p class="text-xs text-gray-500">{{ ctrans("Each active prompt goes to ChatGPT once a week with web search on, as a user in its country sees it. Ten to twenty per brand is enough.") }}</p>
            </div>

            <p v-if="!data.prompts.length" class="px-5 py-4 text-sm text-gray-600">
                {{ ctrans("No prompt yet. Write the questions a customer asks before buying what this shop sells.") }}
            </p>

            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-xs text-gray-500">
                        <th scope="col" class="px-5 py-2 font-medium">{{ ctrans("Prompt") }}</th>
                        <th scope="col" class="px-3 py-2 font-medium">{{ ctrans("Last answer") }}</th>
                        <th scope="col" class="px-3 py-2 text-center font-medium">{{ ctrans("Named") }}</th>
                        <th scope="col" class="px-3 py-2 text-center font-medium">{{ ctrans("Cited") }}</th>
                        <th scope="col" class="px-3 py-2 font-medium">{{ ctrans("Competitors named") }}</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium" v-tooltip="ctrans('Answers naming us out of the answers of the last :days days', { days: data.days })">{{ ctrans("Named, :days days", { days: data.days }) }}</th>
                        <th v-if="canEdit" scope="col" class="px-3 py-2 text-center font-medium">{{ ctrans("Active") }}</th>
                        <th scope="col" class="px-5 py-2" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="prompt in data.prompts" :key="prompt.id" class="align-top" :class="prompt.is_active ? '' : 'text-gray-500'">
                        <td class="max-w-md px-5 py-2">
                            <span :class="prompt.is_active ? 'text-gray-900' : ''">{{ prompt.prompt }}</span>
                            <span class="block text-xs text-gray-500">{{ promptMarket(prompt) }}</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2">
                            <template v-if="prompt.latest">{{ useFormatTime(prompt.latest.date) }}</template>
                            <span v-else-if="prompt.is_pending" class="text-amber-700">{{ ctrans("Waiting for ChatGPT") }}</span>
                            <span v-else class="text-gray-500">{{ ctrans("Not asked yet") }}</span>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <template v-if="prompt.latest">
                                <span v-if="prompt.latest.is_mentioned" class="text-green-700">
                                    {{ ctrans("Yes") }}
                                    <span v-if="prompt.latest.brand_position" class="block text-xs" v-tooltip="ctrans('Our place in the list the answer gives, of :count entries', { count: prompt.latest.brands.length })">{{ ctrans("#:place of :count", { place: prompt.latest.brand_position, count: prompt.latest.brands.length }) }}</span>
                                </span>
                                <span v-else class="text-red-700">{{ ctrans("No") }}</span>
                            </template>
                            <span v-else class="text-gray-400">-</span>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <template v-if="prompt.latest">
                                <span v-if="prompt.latest.is_cited" class="text-green-700" v-tooltip="prompt.latest.our_citations.map((citation) => citation.url).join('\n')">
                                    {{ ctrans("Yes") }}
                                    <span class="block text-xs">{{ ctrans(":count pages", { count: prompt.latest.our_citations.length }) }}</span>
                                </span>
                                <span v-else class="text-red-700">{{ ctrans("No") }}</span>
                            </template>
                            <span v-else class="text-gray-400">-</span>
                        </td>
                        <td class="px-3 py-2">
                            <template v-if="prompt.latest?.competitors.length">
                                <span v-for="competitor in prompt.latest.competitors" :key="competitor.name" class="mr-2 inline-block">
                                    {{ competitor.name }}<span v-if="competitor.is_cited" class="text-xs text-gray-500"> ({{ ctrans("cited") }})</span>
                                </span>
                            </template>
                            <span v-else class="text-gray-400">-</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                            <template v-if="prompt.runs">{{ ctrans(":count of :total", { count: prompt.mentioned, total: prompt.runs }) }}</template>
                            <span v-else class="text-gray-400">-</span>
                        </td>
                        <td v-if="canEdit" class="px-3 py-2 text-center">
                            <ToggleSwitch :modelValue="prompt.is_active" :disabled="togglingId === prompt.id" :aria-label="ctrans('Ask this prompt every week')" @update:modelValue="toggle(prompt)" />
                        </td>
                        <td class="whitespace-nowrap px-5 py-2 text-right">
                            <Button v-if="prompt.latest?.answer" type="tertiary" size="xs" :label="ctrans('Answer')" @click="shownPrompt = prompt" />
                            <Button v-if="canEdit" class="ml-1" type="tertiary" size="xs" :label="ctrans('Delete')" @click="remove(prompt)" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <Dialog
            :visible="shownPrompt !== null"
            modal
            dismissableMask
            :header="shownPrompt?.prompt"
            :style="{ width: '56rem' }"
            :breakpoints="{ '960px': '95vw' }"
            @update:visible="(visible: boolean) => { if (!visible) shownPrompt = null }">
            <template v-if="shownPrompt?.latest">
                <p class="mb-3 text-xs text-gray-500">
                    {{ ctrans("ChatGPT (:model), :market, :date", { model: shownPrompt.latest.model ?? "-", market: promptMarket(shownPrompt), date: useFormatTime(shownPrompt.latest.date) }) }}
                </p>
                <div class="seo-ai-answer text-sm text-gray-800" v-html="answerHtml" />

                <template v-if="shownPrompt.latest.brands.length">
                    <h3 class="mt-5 text-xs font-medium text-gray-700">{{ ctrans("The answer's list, in order") }}</h3>
                    <p class="mt-1 text-sm text-gray-700">{{ shownPrompt.latest.brands.join(", ") }}</p>
                </template>

                <template v-if="shownPrompt.latest.citations.length">
                    <h3 class="mt-5 text-xs font-medium text-gray-700">{{ ctrans("Sources cited") }}</h3>
                    <ol class="mt-1 list-decimal space-y-1 pl-5 text-sm">
                        <li v-for="citation in shownPrompt.latest.citations" :key="citation.url" :class="citation.is_ours ? 'font-medium text-[--app-accent-strong]' : 'text-gray-700'">
                            <a :href="citation.url" target="_blank" rel="noopener noreferrer nofollow" class="underline-offset-2 hover:underline focus-visible:underline">{{ citation.title || citation.url }}</a>
                            <span class="ml-1 text-xs text-gray-500">{{ citation.domain }}</span>
                        </li>
                    </ol>
                </template>
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
.seo-ai-answer :deep(h1),
.seo-ai-answer :deep(h2),
.seo-ai-answer :deep(h3),
.seo-ai-answer :deep(h4) {
    margin: 1rem 0 0.5rem;
    font-weight: 600;
    color: #111827;
}

.seo-ai-answer :deep(p) {
    margin: 0.5rem 0;
}

.seo-ai-answer :deep(ul) {
    margin: 0.5rem 0;
    padding-left: 1.25rem;
    list-style: disc;
}

.seo-ai-answer :deep(ol) {
    margin: 0.5rem 0;
    padding-left: 1.25rem;
    list-style: decimal;
}

.seo-ai-answer :deep(a) {
    color: var(--app-accent-strong);
    text-decoration: underline;
    text-underline-offset: 2px;
}

.seo-ai-answer :deep(table) {
    margin: 0.75rem 0;
    border-collapse: collapse;
}

.seo-ai-answer :deep(th),
.seo-ai-answer :deep(td) {
    border: 1px solid #e5e7eb;
    padding: 0.25rem 0.5rem;
    text-align: left;
}
</style>
