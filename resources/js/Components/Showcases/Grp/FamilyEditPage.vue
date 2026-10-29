<script setup lang="ts">
import { computed, nextTick, reactive, ref } from "vue"
import { Message } from "primevue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExternalLink } from "@fal"
import FamilyEditCard, { type FamilyEditCardData } from "@/Components/Showcases/Grp/FamilyEditCard.vue"
import FamilySeoReview from "@/Components/Showcases/Grp/FamilySeoReview.vue"
import FamilyWebmasterValidation from "@/Components/Showcases/Grp/FamilyWebmasterValidation.vue"
import FamilySeoIssues from "@/Components/Showcases/Grp/FamilySeoIssues.vue"
import HelpTip from "@/Components/Utils/HelpTip.vue"
import FamilySeoPreview, { type FamilySeoPreviewData } from "@/Components/Showcases/Grp/FamilySeoPreview.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

const props = defineProps<{
    data: {
        notice: string | null
        workshop_route: routeType | null
        sections: { title: string, subtitle: string, cards: FamilyEditCardData[] }[]
        preview: FamilySeoPreviewData | null
        page_score: any | null
        seo_review: any | null
        webmaster_validation: any | null
        seo_issues: any | null
    }
}>()

type EditPageSection = "page_setting" | "seo_review" | "webmaster_validation" | "issues"

const canOpenValidation = computed(() => !!props.data.webmaster_validation
    && (props.data.webmaster_validation.tasks.length > 0 || props.data.webmaster_validation.is_validated))

const sectionFromUrl = new URLSearchParams(window.location.search).get("section")
const isSectionAvailable: Record<EditPageSection, boolean> = {
    page_setting: true,
    seo_review: !!props.data.seo_review,
    webmaster_validation: canOpenValidation.value,
    issues: !!props.data.seo_issues,
}
const currentSection = ref<EditPageSection>(sectionFromUrl && isSectionAvailable[sectionFromUrl as EditPageSection] ? sectionFromUrl as EditPageSection : "page_setting")

const typed = reactive<Record<string, string | boolean>>({})

const onFieldChange = (field: string, value: string | boolean) => {
    typed[field] = value
}

const navClass = (section: string) => currentSection.value === section
    ? "bg-[--app-accent-soft] text-[--app-accent-strong]"
    : "text-gray-700 hover:bg-gray-100"

const openSetting = async (cardKey: string) => {
    currentSection.value = "page_setting"
    await nextTick()
    document.getElementById(`edit-page-card-${cardKey}`)?.scrollIntoView({ behavior: "smooth", block: "start" })
}
</script>

<template>
    <div class="flex flex-col gap-4 p-4">
        <nav :aria-label="ctrans('Edit page sections')" class="flex flex-wrap gap-1 border-b border-gray-200 pb-2">
            <span class="inline-flex items-center">
                <button type="button" :aria-current="currentSection === 'page_setting' ? 'page' : undefined" @click="currentSection = 'page_setting'"
                    class="flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-[--app-accent]" :class="navClass('page_setting')">
                    {{ ctrans("Page setting") }}
                </button>
                <HelpTip :text="ctrans('Edit the family\'s webpage content and the fields search engines read, such as the meta title and description. The page score beside it shows what is still missing.')" />
            </span>
            <span v-if="data.workshop_route" class="inline-flex items-center">
                <a :href="route(data.workshop_route.name, data.workshop_route.parameters)" target="_blank" rel="noopener noreferrer"
                    class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-[--app-accent]">
                    {{ ctrans("Workshop") }}
                    <FontAwesomeIcon :icon="faExternalLink" class="text-xs text-gray-500" fixed-width aria-hidden="true" />
                    <span class="sr-only">({{ ctrans("opens in a new window") }})</span>
                </a>
                <HelpTip :text="ctrans('Opens the webpage editor in a new window, to change the layout and blocks of the page.')" />
            </span>
            <span v-if="data.seo_review" class="inline-flex items-center">
                <button type="button" :aria-current="currentSection === 'seo_review' ? 'page' : undefined" @click="currentSection = 'seo_review'"
                    class="flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-[--app-accent]" :class="navClass('seo_review')">
                    {{ ctrans("SEO reviews and suggestions") }}
                </button>
                <HelpTip :text="ctrans('The SEO team reviews the page and writes suggested changes, then sends them to the webmaster.')" />
            </span>
            <span v-if="data.webmaster_validation" class="inline-flex items-center">
                <button type="button" :aria-current="currentSection === 'webmaster_validation' ? 'page' : undefined" @click="currentSection = 'webmaster_validation'"
                    :disabled="!canOpenValidation" v-tooltip="canOpenValidation ? '' : ctrans('Opens once the SEO team has saved suggestions')"
                    class="flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-[--app-accent] disabled:opacity-50 disabled:cursor-not-allowed" :class="navClass('webmaster_validation')">
                    {{ ctrans("Webmaster validation") }}
                </button>
                <HelpTip :text="ctrans('The webmaster implements the SEO team\'s suggestions one by one, then validates them to close the review.')" />
            </span>
            <span v-if="data.seo_issues" class="inline-flex items-center">
                <button type="button" :aria-current="currentSection === 'issues' ? 'page' : undefined" @click="currentSection = 'issues'"
                    class="inline-flex items-center gap-1.5 min-h-10 px-3.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[--app-accent]" :class="navClass('issues')">
                    {{ ctrans("Issues") }}
                    <span v-if="data.seo_issues.total" class="rounded-full bg-red-700 px-1.5 text-xs font-semibold text-white">{{ data.seo_issues.total }}</span>
                </button>
                <HelpTip :text="ctrans('Problems the site audit or Crawl page found on the live page, such as broken links, missing meta tags or an error response.')" />
            </span>
        </nav>

        <Message v-if="data.notice" severity="warn">{{ data.notice }}</Message>

        <FamilySeoReview v-if="currentSection === 'seo_review' && data.seo_review" :data="data.seo_review" :pageScore="data.page_score" :preview="data.preview"
            :canOpenValidation="canOpenValidation" @openWebmasterValidation="currentSection = 'webmaster_validation'" />

        <FamilyWebmasterValidation v-else-if="currentSection === 'webmaster_validation' && canOpenValidation" :data="data.webmaster_validation"
            :sections="data.sections" :workshopRoute="data.workshop_route" :pageScore="data.page_score" :preview="data.preview" @openSetting="openSetting" />

        <FamilySeoIssues v-else-if="currentSection === 'issues' && data.seo_issues" :data="data.seo_issues" />

        <div v-else class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_24rem]">
            <div class="flex min-w-0 flex-col gap-4">
                <section v-for="(section, sectionIndex) in data.sections" :key="sectionIndex"
                    class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-md">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-semibold text-gray-900">{{ section.title }}</h2>
                        <span class="text-xs text-gray-500">{{ section.subtitle }}</span>
                    </div>

                    <FamilyEditCard v-for="(card, cardIndex) in section.cards" :key="cardIndex" :id="card.key ? `edit-page-card-${card.key}` : undefined"
                        :card="card" :fieldKey="`${sectionIndex}-${cardIndex}`" @fieldChange="onFieldChange" />
                </section>
            </div>

            <aside v-if="data.preview" class="flex h-fit flex-col gap-4 lg:sticky lg:top-4">
                <FamilySeoPreview :preview="data.preview" :typed="typed" :subtitle="ctrans('Updates as you type')" />
            </aside>
        </div>
    </div>
</template>
