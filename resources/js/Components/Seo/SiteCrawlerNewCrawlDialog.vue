<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Fri, 06 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Textarea from "primevue/textarea"
import ToggleSwitch from "primevue/toggleswitch"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faChevronDown, faChevronRight, faPlus, faTrashAlt } from "@fal"
import { ctrans } from "@/Composables/useTrans"

export type CustomSearch = { name: string, mode: "contains" | "not_contains", type: "text" | "regex", value: string }

export type Extraction = { name: string, type: "css" | "xpath" | "regex", selector: string, output: "text" | "html" | "attribute", attribute: string | null }

export type CrawlConfig = {
    max_depth: number | null
    include: string[]
    exclude: string[]
    respect_robots_txt: boolean
    crawl_subdomains: boolean
    check_external_links: boolean
    check_images: boolean
    mode: "spider" | "list"
    urls: string[]
    custom_search: CustomSearch[]
    extractions: Extraction[]
    max_pages: number | null
    concurrency: number
}

export type NewCrawlSettings = {
    websites: Array<{ id: number, domain: string, slug: string }>
    defaults: CrawlConfig
    maxConcurrency: number
    maxListUrls: number
    maxCustomItems: number
    route: { name: string, method: string }
}

const props = defineProps<{
    settings: NewCrawlSettings
    websiteId?: number | null
}>()

const visible = defineModel<boolean>("visible", { default: false })

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)]"
const toggleClass = "[&.p-toggleswitch-checked_.p-toggleswitch-slider]:!bg-[var(--app-accent)]"

const websiteId = ref<number | null>(null)
const mode = ref<"spider" | "list">("spider")
const urls = ref("")
const name = ref("")
const maxPages = ref<number | null>(null)
const maxDepth = ref<number | null>(null)
const concurrency = ref(1)
const include = ref("")
const exclude = ref("")
const respectRobotsTxt = ref(true)
const crawlSubdomains = ref(false)
const checkExternalLinks = ref(true)
const checkImages = ref(true)
const errors = ref<Record<string, string>>({})
const isShowingAdvanced = ref(false)
const isShowingCustom = ref(false)
const customSearches = ref<CustomSearch[]>([])
const extractions = ref<Extraction[]>([])
const isSaving = ref(false)

const reset = () => {
    const defaults = props.settings.defaults

    websiteId.value = props.websiteId ?? (props.settings.websites.length === 1 ? props.settings.websites[0].id : null)
    name.value = ""
    mode.value = defaults.mode
    urls.value = defaults.urls.join("\n")
    maxPages.value = defaults.max_pages
    maxDepth.value = defaults.max_depth
    concurrency.value = defaults.concurrency
    include.value = defaults.include.join("\n")
    exclude.value = defaults.exclude.join("\n")
    respectRobotsTxt.value = defaults.respect_robots_txt
    crawlSubdomains.value = defaults.crawl_subdomains
    checkExternalLinks.value = defaults.check_external_links
    checkImages.value = defaults.check_images
    errors.value = {}
    isShowingAdvanced.value = false
    isShowingCustom.value = false
    customSearches.value = defaults.custom_search.map((search) => ({ ...search }))
    extractions.value = defaults.extractions.map((extraction) => ({ ...extraction }))
}

watch(visible, (isVisible) => isVisible && reset(), { immediate: true })

const lines = (text: string) => text.split("\n").map((line) => line.trim()).filter((line) => line !== "")

const patternErrors = (field: "include" | "exclude") => Object.entries(errors.value)
    .filter(([key]) => key.startsWith(`${field}.`))
    .map(([, message]) => message)

const otherErrors = computed(() => Object.entries(errors.value)
    .filter(([key]) => !["website", "name", "max_pages", "max_depth", "concurrency", "mode"].includes(key) && !key.startsWith("include") && !key.startsWith("exclude") && !key.startsWith("urls") && !key.startsWith("custom_search.") && !key.startsWith("extractions."))
    .map(([, message]) => message))

const searchModeOptions = [
    { label: ctrans("Contains"), value: "contains" },
    { label: ctrans("Does not contain"), value: "not_contains" },
]

const searchTypeOptions = [
    { label: ctrans("Text"), value: "text" },
    { label: ctrans("Regex"), value: "regex" },
]

const extractionTypeOptions = [
    { label: "CSS", value: "css" },
    { label: "XPath", value: "xpath" },
    { label: ctrans("Regex"), value: "regex" },
]

const extractionOutputOptions = [
    { label: ctrans("Text"), value: "text" },
    { label: "HTML", value: "html" },
    { label: ctrans("Attribute"), value: "attribute" },
]

const addSearch = () => customSearches.value.push({ name: "", mode: "contains", type: "text", value: "" })

const addExtraction = () => extractions.value.push({ name: "", type: "css", selector: "", output: "text", attribute: null })

const customCount = computed(() => customSearches.value.length + extractions.value.length)

const itemErrors = (field: "custom_search" | "extractions", index: number) => Object.entries(errors.value)
    .filter(([key]) => key.startsWith(`${field}.${index}.`))
    .map(([, message]) => message)

const modeOptions = [
    { label: ctrans("Spider"), value: "spider" as const },
    { label: ctrans("List"), value: "list" as const },
]

const isListMode = computed(() => mode.value === "list")

const listedUrlCount = computed(() => lines(urls.value).length)

const advancedChanges = computed(() => [
    lines(include.value).length > 0,
    lines(exclude.value).length > 0,
    !respectRobotsTxt.value,
    crawlSubdomains.value,
    !checkExternalLinks.value,
    !checkImages.value,
].filter(Boolean).length)

const isLimited = computed(() => isListMode.value
    || maxPages.value !== null
    || maxDepth.value !== null
    || lines(include.value).length > 0
    || lines(exclude.value).length > 0
    || !respectRobotsTxt.value
    || crawlSubdomains.value
    || !checkExternalLinks.value
    || !checkImages.value)

const submit = () => {
    if (!websiteId.value) {
        errors.value = { website: ctrans("Choose the website to crawl") }

        return
    }

    router.post(route(props.settings.route.name, [websiteId.value]), {
        name: name.value.trim() || null,
        mode: mode.value,
        urls: isListMode.value ? lines(urls.value) : [],
        custom_search: customSearches.value,
        extractions: extractions.value.map((extraction) => ({ ...extraction, attribute: extraction.output === "attribute" ? extraction.attribute : null })),
        max_pages: maxPages.value,
        max_depth: maxDepth.value,
        concurrency: concurrency.value,
        include: lines(include.value),
        exclude: lines(exclude.value),
        respect_robots_txt: respectRobotsTxt.value,
        crawl_subdomains: crawlSubdomains.value,
        check_external_links: checkExternalLinks.value,
        check_images: checkImages.value,
    }, {
        onStart: () => {
            isSaving.value = true
            errors.value = {}
        },
        onError: (responseErrors) => {
            errors.value = responseErrors
            isShowingAdvanced.value = isShowingAdvanced.value || Object.keys(responseErrors).some((key) => key.startsWith("include") || key.startsWith("exclude"))
            isShowingCustom.value = isShowingCustom.value || Object.keys(responseErrors).some((key) => key.startsWith("custom_search") || key.startsWith("extractions"))
        },
        onSuccess: () => visible.value = false,
        onFinish: () => isSaving.value = false,
    })
}
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="ctrans('New crawl')" :style="{ width: '44rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
        <div class="space-y-5 text-sm">
            <fieldset class="space-y-3">
                <legend class="mb-2 text-xs font-semibold text-gray-900">{{ ctrans("What to crawl") }}</legend>
                <div class="space-y-1">
                    <label for="crawl-website" class="block text-xs font-medium text-gray-700">{{ ctrans("Website") }}</label>
                    <Select
                        v-model="websiteId"
                        inputId="crawl-website"
                        :options="settings.websites"
                        optionLabel="domain"
                        optionValue="id"
                        filter
                        :placeholder="ctrans('Choose a website')"
                        :class="fieldFocusClass"
                        fluid
                    />
                    <p v-if="errors.website" class="text-xs text-red-600">{{ errors.website }}</p>
                </div>

                <div class="space-y-1">
                    <SegmentedToggle v-model="mode" :options="modeOptions" :ariaLabel="ctrans('Crawl mode')" />
                    <p class="text-xs text-gray-600">
                        {{ isListMode ? ctrans("Checks only the URLs you paste, without following their links.") : ctrans("Starts at the home page and follows every link, then the URLs in the sitemap.") }}
                    </p>
                </div>

                <div v-if="isListMode" class="space-y-1">
                    <label for="crawl-urls" class="block text-xs font-medium text-gray-700">{{ ctrans("URLs, one per line") }}</label>
                    <Textarea id="crawl-urls" v-model="urls" rows="6" :placeholder="ctrans('https://www.example.com/page')" class="font-mono text-xs" :class="fieldFocusClass" fluid />
                    <p class="text-xs text-gray-600">{{ ctrans(":count URLs, up to :max", { count: listedUrlCount, max: settings.maxListUrls }) }}</p>
                    <p v-if="errors.urls" class="text-xs text-red-600">{{ errors.urls }}</p>
                </div>

                <div class="space-y-1">
                    <label for="crawl-name" class="block text-xs font-medium text-gray-700">{{ ctrans("Name (optional)") }}</label>
                    <InputText id="crawl-name" v-model="name" :placeholder="ctrans('After the menu change')" :class="fieldFocusClass" fluid />
                    <p v-if="errors.name" class="text-xs text-red-600">{{ errors.name }}</p>
                </div>
            </fieldset>

            <fieldset class="space-y-2">
                <legend class="mb-2 text-xs font-semibold text-gray-900">{{ ctrans("Limits") }}</legend>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="space-y-1">
                        <label for="crawl-max-pages" class="block text-xs font-medium text-gray-700">{{ ctrans("Max URLs") }}</label>
                        <InputNumber v-model="maxPages" inputId="crawl-max-pages" :min="1" :useGrouping="false" :placeholder="ctrans('No limit')" :class="fieldFocusClass" fluid />
                        <p v-if="errors.max_pages" class="text-xs text-red-600">{{ errors.max_pages }}</p>
                    </div>
                    <div v-if="!isListMode" class="space-y-1">
                        <label for="crawl-max-depth" class="block text-xs font-medium text-gray-700">{{ ctrans("Max clicks deep") }}</label>
                        <InputNumber v-model="maxDepth" inputId="crawl-max-depth" :min="0" :useGrouping="false" :placeholder="ctrans('No limit')" :class="fieldFocusClass" fluid />
                        <p v-if="errors.max_depth" class="text-xs text-red-600">{{ errors.max_depth }}</p>
                    </div>
                    <div class="space-y-1">
                        <label for="crawl-concurrency" class="block text-xs font-medium text-gray-700">{{ ctrans("URLs at once") }}</label>
                        <InputNumber v-model="concurrency" inputId="crawl-concurrency" :min="1" :max="settings.maxConcurrency" :class="fieldFocusClass" fluid />
                        <p v-if="errors.concurrency" class="text-xs text-red-600">{{ errors.concurrency }}</p>
                    </div>
                </div>
                <p class="text-xs text-gray-600">{{ ctrans("Each URL is rendered by the servers our customers use, and running crawls share at most :max at once.", { max: settings.maxConcurrency }) }}</p>
            </fieldset>

            <div>
                <button
                    type="button"
                    class="flex cursor-pointer items-center gap-1.5 text-xs font-semibold text-gray-900 hover:text-[var(--app-accent)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--app-accent)]"
                    :aria-expanded="isShowingAdvanced"
                    aria-controls="crawl-advanced"
                    @click="isShowingAdvanced = !isShowingAdvanced"
                >
                    <FontAwesomeIcon :icon="isShowingAdvanced ? faChevronDown : faChevronRight" fixed-width aria-hidden="true" />
                    {{ ctrans("Scope and checks") }}
                    <span v-if="!isShowingAdvanced && advancedChanges" class="font-normal text-gray-600">{{ ctrans("(:count changed)", { count: advancedChanges }) }}</span>
                </button>

                <div v-show="isShowingAdvanced" id="crawl-advanced" class="mt-3 space-y-3">
                    <div v-if="!isListMode" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="space-y-1">
                            <label for="crawl-include" class="block text-xs font-medium text-gray-700">{{ ctrans("Only URLs matching") }}</label>
                            <Textarea id="crawl-include" v-model="include" rows="3" :placeholder="ctrans('/collections/')" class="font-mono text-xs" :class="fieldFocusClass" fluid />
                            <p v-for="message in patternErrors('include')" :key="message" class="text-xs text-red-600">{{ message }}</p>
                        </div>
                        <div class="space-y-1">
                            <label for="crawl-exclude" class="block text-xs font-medium text-gray-700">{{ ctrans("Skip URLs matching") }}</label>
                            <Textarea id="crawl-exclude" v-model="exclude" rows="3" :placeholder="ctrans('/blog/')" class="font-mono text-xs" :class="fieldFocusClass" fluid />
                            <p v-for="message in patternErrors('exclude')" :key="message" class="text-xs text-red-600">{{ message }}</p>
                        </div>
                        <p class="text-xs text-gray-600 sm:col-span-2">{{ ctrans("Regular expressions, one per line, matched against the full URL.") }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-x-3 gap-y-2.5 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-center gap-2">
                            <ToggleSwitch v-model="respectRobotsTxt" :class="toggleClass" />
                            <span>{{ ctrans("Respect robots.txt") }}</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2">
                            <ToggleSwitch v-model="crawlSubdomains" :class="toggleClass" />
                            <span>{{ ctrans("Crawl subdomains") }}</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2">
                            <ToggleSwitch v-model="checkExternalLinks" :class="toggleClass" />
                            <span>{{ ctrans("Check external links") }}</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2">
                            <ToggleSwitch v-model="checkImages" :class="toggleClass" />
                            <span>{{ ctrans("Check images") }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <div>
                <button
                    type="button"
                    class="flex cursor-pointer items-center gap-1.5 text-xs font-semibold text-gray-900 hover:text-[var(--app-accent)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--app-accent)]"
                    :aria-expanded="isShowingCustom"
                    aria-controls="crawl-custom"
                    @click="isShowingCustom = !isShowingCustom"
                >
                    <FontAwesomeIcon :icon="isShowingCustom ? faChevronDown : faChevronRight" fixed-width aria-hidden="true" />
                    {{ ctrans("Custom search and extraction") }}
                    <span v-if="!isShowingCustom && customCount" class="font-normal text-gray-600">({{ customCount }})</span>
                </button>

                <div v-show="isShowingCustom" id="crawl-custom" class="mt-3 space-y-4">
                    <div class="space-y-2">
                        <p class="text-xs text-gray-600">{{ ctrans("Search counts how often a text or pattern appears in the HTML of each page.") }}</p>
                        <div v-for="(search, index) in customSearches" :key="`search-${index}`" class="space-y-1">
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-[8rem_9rem_6rem_minmax(0,1fr)_auto]">
                                <InputText v-model="search.name" :placeholder="ctrans('Name')" :aria-label="ctrans('Search name')" :class="fieldFocusClass" fluid />
                                <Select v-model="search.mode" :options="searchModeOptions" optionLabel="label" optionValue="value" :aria-label="ctrans('Match')" :class="fieldFocusClass" fluid />
                                <Select v-model="search.type" :options="searchTypeOptions" optionLabel="label" optionValue="value" :aria-label="ctrans('Search type')" :class="fieldFocusClass" fluid />
                                <InputText v-model="search.value" :placeholder="search.type === 'regex' ? 'gtag\\(' : 'googletagmanager.com'" :aria-label="ctrans('Search for')" class="font-mono text-xs" :class="fieldFocusClass" fluid />
                                <button type="button" class="flex h-10 w-10 cursor-pointer items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-red-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--app-accent)]" :aria-label="ctrans('Remove search')" @click="customSearches.splice(index, 1)">
                                    <FontAwesomeIcon :icon="faTrashAlt" fixed-width aria-hidden="true" />
                                </button>
                            </div>
                            <p v-for="message in itemErrors('custom_search', index)" :key="message" class="text-xs text-red-600">{{ message }}</p>
                        </div>
                        <Button v-if="customSearches.length < settings.maxCustomItems" type="tertiary" size="xs" :label="ctrans('Add search')" @click="addSearch">
                            <template #icon><FontAwesomeIcon :icon="faPlus" class="text-green-600" fixed-width aria-hidden="true" /></template>
                        </Button>
                    </div>

                    <div class="space-y-2">
                        <p class="text-xs text-gray-600">{{ ctrans("Extraction keeps the first values a CSS selector, XPath or regex finds on each page, shown as columns in the Custom tab.") }}</p>
                        <div v-for="(extraction, index) in extractions" :key="`extraction-${index}`" class="space-y-1">
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-[8rem_6rem_minmax(0,1fr)_7rem_auto]">
                                <InputText v-model="extraction.name" :placeholder="ctrans('Name')" :aria-label="ctrans('Extraction name')" :class="fieldFocusClass" fluid />
                                <Select v-model="extraction.type" :options="extractionTypeOptions" optionLabel="label" optionValue="value" :aria-label="ctrans('Extraction type')" :class="fieldFocusClass" fluid />
                                <InputText v-model="extraction.selector" :placeholder="extraction.type === 'xpath' ? '//div[@class=&quot;price&quot;]' : extraction.type === 'regex' ? 'SKU: (\\w+)' : '.product-code'" :aria-label="ctrans('Selector')" class="font-mono text-xs" :class="fieldFocusClass" fluid />
                                <Select v-if="extraction.type !== 'regex'" v-model="extraction.output" :options="extractionOutputOptions" optionLabel="label" optionValue="value" :aria-label="ctrans('Value to keep')" :class="fieldFocusClass" fluid />
                                <span v-else class="hidden sm:block" />
                                <button type="button" class="flex h-10 w-10 cursor-pointer items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-red-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--app-accent)]" :aria-label="ctrans('Remove extraction')" @click="extractions.splice(index, 1)">
                                    <FontAwesomeIcon :icon="faTrashAlt" fixed-width aria-hidden="true" />
                                </button>
                            </div>
                            <InputText v-if="extraction.type !== 'regex' && extraction.output === 'attribute'" v-model="extraction.attribute" :placeholder="ctrans('Attribute name, e.g. data-sku')" :aria-label="ctrans('Attribute name')" class="font-mono text-xs sm:ml-[8.5rem] sm:w-60" :class="fieldFocusClass" />
                            <p v-for="message in itemErrors('extractions', index)" :key="message" class="text-xs text-red-600">{{ message }}</p>
                        </div>
                        <Button v-if="extractions.length < settings.maxCustomItems" type="tertiary" size="xs" :label="ctrans('Add extraction')" @click="addExtraction">
                            <template #icon><FontAwesomeIcon :icon="faPlus" class="text-green-600" fixed-width aria-hidden="true" /></template>
                        </Button>
                    </div>
                </div>
            </div>

            <p v-if="isLimited" class="rounded-md border border-amber-200 bg-amber-50 p-2.5 text-xs text-amber-900">{{ ctrans("This is a limited crawl. You see its results here, while the health score, page scores and content suggestions keep reading the last full crawl.") }}</p>

            <p v-for="message in otherErrors" :key="message" class="text-xs text-red-600">{{ message }}</p>
        </div>

        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="primary" full :label="ctrans('Start crawl')" :loading="isSaving" @click="submit">
                    <template #icon>
                        <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </template>
    </Dialog>
</template>
