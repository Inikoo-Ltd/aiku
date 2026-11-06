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
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes } from "@fal"
import { ctrans } from "@/Composables/useTrans"

export type CrawlConfig = {
    max_depth: number | null
    include: string[]
    exclude: string[]
    respect_robots_txt: boolean
    crawl_subdomains: boolean
    check_external_links: boolean
    check_images: boolean
    max_pages: number | null
    concurrency: number
}

export type NewCrawlSettings = {
    websites: Array<{ id: number, domain: string, slug: string }>
    defaults: CrawlConfig
    maxConcurrency: number
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
const isSaving = ref(false)

const reset = () => {
    const defaults = props.settings.defaults

    websiteId.value = props.websiteId ?? (props.settings.websites.length === 1 ? props.settings.websites[0].id : null)
    name.value = ""
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
}

watch(visible, (isVisible) => isVisible && reset(), { immediate: true })

const lines = (text: string) => text.split("\n").map((line) => line.trim()).filter((line) => line !== "")

const patternErrors = (field: "include" | "exclude") => Object.entries(errors.value)
    .filter(([key]) => key.startsWith(`${field}.`))
    .map(([, message]) => message)

const otherErrors = computed(() => Object.entries(errors.value)
    .filter(([key]) => !["website", "name", "max_pages", "max_depth", "concurrency"].includes(key) && !key.startsWith("include") && !key.startsWith("exclude"))
    .map(([, message]) => message))

const isLimited = computed(() => maxPages.value !== null
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
        onError: (responseErrors) => errors.value = responseErrors,
        onSuccess: () => visible.value = false,
        onFinish: () => isSaving.value = false,
    })
}
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="ctrans('New crawl')" :style="{ width: '34rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
        <div class="space-y-4 text-sm">
            <div class="space-y-1">
                <label for="crawl-website" class="block text-xs font-medium text-gray-600">{{ ctrans("Website") }}</label>
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
                <p v-if="errors.website" class="text-xs text-red-500">{{ errors.website }}</p>
            </div>

            <div class="space-y-1">
                <label for="crawl-name" class="block text-xs font-medium text-gray-600">{{ ctrans("Name") }}</label>
                <InputText id="crawl-name" v-model="name" :placeholder="ctrans('Optional, e.g. After menu change')" :class="fieldFocusClass" fluid />
                <p v-if="errors.name" class="text-xs text-red-500">{{ errors.name }}</p>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="space-y-1">
                    <label for="crawl-max-pages" class="block text-xs font-medium text-gray-600">{{ ctrans("Max URLs") }}</label>
                    <InputNumber v-model="maxPages" inputId="crawl-max-pages" :min="1" :useGrouping="false" :placeholder="ctrans('No limit')" :class="fieldFocusClass" fluid />
                    <p v-if="errors.max_pages" class="text-xs text-red-500">{{ errors.max_pages }}</p>
                </div>
                <div class="space-y-1">
                    <label for="crawl-max-depth" class="block text-xs font-medium text-gray-600">{{ ctrans("Max depth") }}</label>
                    <InputNumber v-model="maxDepth" inputId="crawl-max-depth" :min="0" :useGrouping="false" :placeholder="ctrans('No limit')" :class="fieldFocusClass" fluid />
                    <p v-if="errors.max_depth" class="text-xs text-red-500">{{ errors.max_depth }}</p>
                </div>
                <div class="space-y-1">
                    <label for="crawl-concurrency" class="block text-xs font-medium text-gray-600">{{ ctrans("Speed") }}</label>
                    <InputNumber v-model="concurrency" inputId="crawl-concurrency" :min="1" :max="settings.maxConcurrency" :suffix="` ${ctrans('at once')}`" :class="fieldFocusClass" fluid />
                    <p v-if="errors.concurrency" class="text-xs text-red-500">{{ errors.concurrency }}</p>
                </div>
            </div>
            <p class="-mt-2 text-xs text-gray-500">{{ ctrans("Each URL is rendered by the same servers as our customers, so the speed is shared with other running crawls.") }}</p>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label for="crawl-include" class="block text-xs font-medium text-gray-600">{{ ctrans("Include") }}</label>
                    <Textarea id="crawl-include" v-model="include" rows="3" :placeholder="ctrans('One regex per line')" class="font-mono text-xs" :class="fieldFocusClass" fluid />
                    <p v-for="message in patternErrors('include')" :key="message" class="text-xs text-red-500">{{ message }}</p>
                </div>
                <div class="space-y-1">
                    <label for="crawl-exclude" class="block text-xs font-medium text-gray-600">{{ ctrans("Exclude") }}</label>
                    <Textarea id="crawl-exclude" v-model="exclude" rows="3" :placeholder="ctrans('One regex per line')" class="font-mono text-xs" :class="fieldFocusClass" fluid />
                    <p v-for="message in patternErrors('exclude')" :key="message" class="text-xs text-red-500">{{ message }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-x-3 gap-y-2">
                <label class="flex items-center gap-2">
                    <ToggleSwitch v-model="respectRobotsTxt" :class="toggleClass" />
                    <span>{{ ctrans("Respect robots.txt") }}</span>
                </label>
                <label class="flex items-center gap-2">
                    <ToggleSwitch v-model="crawlSubdomains" :class="toggleClass" />
                    <span>{{ ctrans("Crawl subdomains") }}</span>
                </label>
                <label class="flex items-center gap-2">
                    <ToggleSwitch v-model="checkExternalLinks" :class="toggleClass" />
                    <span>{{ ctrans("Check external links") }}</span>
                </label>
                <label class="flex items-center gap-2">
                    <ToggleSwitch v-model="checkImages" :class="toggleClass" />
                    <span>{{ ctrans("Check images") }}</span>
                </label>
            </div>

            <p v-if="isLimited" class="rounded border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">{{ ctrans("A limited crawl is shown here only. The health score, the product and family page scores and the content suggestions keep reading the last full crawl.") }}</p>

            <p v-for="message in otherErrors" :key="message" class="text-xs text-red-500">{{ message }}</p>
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
