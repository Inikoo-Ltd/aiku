<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExternalLink, faSlidersH } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import WorkflowSteps from "@/Components/DataDisplay/WorkflowSteps.vue"
import WebpageSeoPreviewCard from "@/Components/DataDisplay/WebpageSeoPreviewCard.vue"
import ProductSettingForm from "@/Components/Catalogue/ProductSetting/ProductSettingForm.vue"
import SeoReviewSuggestions from "@/Components/Catalogue/ProductSetting/SeoReviewSuggestions.vue"
import SeoWebmasterValidation from "@/Components/Catalogue/ProductSetting/SeoWebmasterValidation.vue"
import WebpageIssues from "@/Components/Catalogue/ProductSetting/WebpageIssues.vue"
import { productSettingActionsId, type MasterProductData, type ProductSettingTab, type ShopProductSetting } from "@/Components/Catalogue/ProductSetting/types"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

library.add(faSlidersH)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tab: ProductSettingTab
    canEdit: boolean
    overview_route: routeType
    master: MasterProductData
    shop: ShopProductSetting
}>()

const currentTab = ref<ProductSettingTab>(props.tab)
watch(() => props.tab, (tab) => (currentTab.value = tab))

const settingUrl = (tab: ProductSettingTab) => {
    const url = new URL(window.location.href)
    url.searchParams.set("tab", tab)

    return `${url.pathname}?${url.searchParams.toString()}`
}

const selectTab = (tab: ProductSettingTab) => {
    currentTab.value = tab
    router.get(settingUrl(tab), {}, { preserveState: true, preserveScroll: true, replace: true, only: ["tab"] })
}

const subMenu = computed(() => {
    const issueCount = props.shop?.audit?.issue_count || undefined

    return [
        { value: "setting" as ProductSettingTab | "workshop", label: ctrans("Setting"), help: ctrans("Edit the product's webpage content and the fields search engines read, such as the meta title and description. The preview beside it shows how search engines and social media show the page.") },
        ...(props.shop?.webpage?.workshop_route ? [{ value: "workshop" as const, label: ctrans("Workshop"), icon: faExternalLink, help: ctrans("Opens the webpage editor in a new window, to change the layout and blocks of the page.") }] : []),
        { value: "seo_review" as const, label: ctrans("SEO review & suggestions"), help: ctrans("The SEO team reviews the page and writes suggested changes, then sends them to the webmaster.") },
        { value: "validation" as const, label: ctrans("Webmaster validation"), help: ctrans("The webmaster implements the SEO team's suggestions one by one, then validates them to close the review.") },
        { value: "issues" as const, label: ctrans("Issues"), count: issueCount, tone: issueCount ? "red" as const : undefined, help: ctrans("Problems the site audit or Crawl page found on the live page, such as broken links, missing meta tags or an error response.") },
    ]
})

const onSelectTab = (tab?: ProductSettingTab | "workshop") => {
    if (tab === "workshop") {
        openWorkshop()
    } else if (tab) {
        selectTab(tab)
    }
}

const openWorkshop = () => {
    const workshopRoute = props.shop?.webpage?.workshop_route
    if (workshopRoute) {
        window.open(route(workshopRoute.name, workshopRoute.parameters), "_blank")
    }
}

const settingForm = ref<InstanceType<typeof ProductSettingForm> | null>(null)

const typed = computed<Record<string, string>>(() => {
    const form = settingForm.value?.form

    return form ? { title: form.title, description: form.description, url: form.url } : {}
})

const selectWorkflowStep = (stepKey: string) => selectTab(stepKey === "seo_review" ? "seo_review" : "validation")

const goToOverview = () => router.visit(route(props.overview_route.name, props.overview_route.parameters))

const reloadShop = () => router.reload({ only: ["shop"] })
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="space-y-4 p-4">
        <nav class="flex flex-wrap items-center gap-2 border-b border-gray-200 pb-2" :aria-label="ctrans('Product setting')">
            <SegmentedToggle :modelValue="currentTab" :options="subMenu" :ariaLabel="ctrans('Product setting')" @update:modelValue="onSelectTab" />
        </nav>

        <WorkflowSteps
            v-if="currentTab === 'seo_review' || currentTab === 'validation'"
            :steps="shop.workflow.steps"
            :current="shop.workflow.current"
            :selectableKeys="['seo_review', 'suggestions_implemented']"
            @select="selectWorkflowStep"
        />

        <WebpageIssues
            v-if="currentTab === 'issues'"
            :audit="shop.audit ?? { last_crawl_at: null, issue_count: 0, issue_groups: [] }"
            :crawlRoute="shop.webpage?.crawl_route ?? null"
        />

        <div v-if="currentTab !== 'issues'" :id="productSettingActionsId" class="empty:hidden" />

        <div v-if="currentTab !== 'issues'" class="grid grid-cols-1 gap-4" :class="currentTab === 'setting' && shop.webpage?.preview ? 'lg:grid-cols-[minmax(0,1fr)_minmax(24rem,40%)]' : ''">
            <div>
                <ProductSettingForm
                    v-if="currentTab === 'setting'"
                    ref="settingForm"
                    :master="master"
                    :shop="shop"
                    :canEdit="canEdit"
                    @saved="reloadShop"
                />

                <template v-else-if="!shop.review">
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500">
                        {{ ctrans("This product has no webpage in the shop") }}
                    </div>
                </template>

                <SeoReviewSuggestions
                    v-else-if="currentTab === 'seo_review'"
                    :review="shop.review"
                    :canEdit="shop.can_edit_seo"
                    :shopCode="shop.shop.code"
                    :structuredDataSource="shop.webpage?.structured_data_source ?? null"
                    :internalLinkRoute="shop.webpage?.internal_link_route"
                    @sent="goToOverview"
                />

                <SeoWebmasterValidation
                    v-else
                    :review="shop.review"
                    :internalLinkRoute="shop.webpage?.internal_link_route"
                    @validated="goToOverview"
                />
            </div>

            <aside v-if="currentTab === 'setting' && shop.webpage?.preview" class="h-fit lg:sticky lg:top-4">
                <WebpageSeoPreviewCard :preview="shop.webpage.preview" :typed="typed" :subtitle="ctrans('Updates as you type')" />
            </aside>
        </div>
    </div>
</template>
