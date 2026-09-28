<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import MailshotJourney from "@/Components/Navigation/MailshotJourney.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faGoogle } from "@fortawesome/free-brands-svg-icons"
import { faCheckCircle, faExclamationTriangle, faTimesCircle, faCloudUpload, faArrowLeft } from "@fal"
import { capitalize } from "@/Composables/capitalize"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { ctrans } from "@/Composables/useTrans"

library.add(faGoogle, faCheckCircle, faExclamationTriangle, faTimesCircle, faCloudUpload, faArrowLeft)

type Image = { id: number; name: string; thumbnail: string }

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    journey: { key: string; label: string; current: boolean; done?: boolean; disabled?: boolean; route: routeType }[]
    verdict: { status: "incomplete" | "refused" | "accepted"; messages: string[] }
    campaign: {
        name: string
        channel_type: string
        type_label: string
        data: Record<string, any>
        countries: string[]
    }
    images: Record<"marketing_images" | "square_marketing_images" | "logos", Image[]>
    currency: string
    compose_route: routeType
}>()

const locale = useLocaleStore()

const data = computed(() => props.campaign.data ?? {})
const isSearch = computed(() => props.campaign.channel_type === "SEARCH")
const isPmax = computed(() => props.campaign.channel_type === "PERFORMANCE_MAX")
const isDisplay = computed(() => props.campaign.channel_type === "DISPLAY")
const isDemandGen = computed(() => props.campaign.channel_type === "DEMAND_GEN")

const money = (value: number | string | null | undefined): string =>
    value === null || value === undefined || value === "" ? "—" : locale.currencyFormat(props.currency, Number(value))

const matchTypeLabel = computed(() => {
    switch (data.value.match_type) {
        case "BROAD":
            return ctrans("Broad")
        case "EXACT":
            return ctrans("Exact")
        default:
            return ctrans("Phrase")
    }
})

const settings = computed(() =>
    [
        { label: ctrans("Type"), value: props.campaign.type_label },
        { label: ctrans("Daily budget"), value: money(data.value.budget_amount) },
        isSearch.value ? { label: ctrans("Highest cost per click"), value: data.value.max_cpc ? money(data.value.max_cpc) : ctrans("No cap") } : null,
        isDisplay.value ? { label: ctrans("Cost per click bid"), value: money(data.value.max_cpc) } : null,
        isDemandGen.value ? { label: ctrans("Target cost per conversion"), value: money(data.value.target_cpa) } : null,
        { label: ctrans("Landing page"), value: data.value.final_url || "—", isUrl: !!data.value.final_url },
        {
            label: ctrans("Shown in"),
            value: props.campaign.countries.length ? props.campaign.countries.join(", ") : ctrans("Everywhere Google reaches"),
        },
        {
            label: isPmax.value ? ctrans("Asset group") : ctrans("Ad group"),
            value: (isPmax.value ? data.value.asset_group_name : data.value.ad_group_name) || "—",
        },
        !isSearch.value ? { label: ctrans("Business name"), value: data.value.business_name || "—" } : null,
    ].filter(Boolean) as { label: string; value: string; isUrl?: boolean }[]
)

const imageRoles = computed(() =>
    [
        { key: "marketing_images", label: ctrans("Landscape images") },
        { key: "square_marketing_images", label: ctrans("Square images") },
        { key: "logos", label: ctrans("Logos") },
    ].filter((role) => (props.images[role.key as keyof typeof props.images] ?? []).length)
)

const verdictStyle = computed(() => {
    switch (props.verdict.status) {
        case "accepted":
            return { icon: "fal fa-check-circle", box: "bg-green-50 ring-green-200", text: "text-[#006300]" }
        case "refused":
            return { icon: "fal fa-times-circle", box: "bg-red-50 ring-red-200", text: "text-[#d03b3b]" }
        default:
            return { icon: "fal fa-exclamation-triangle", box: "bg-amber-50 ring-amber-200", text: "text-[#a15c00]" }
    }
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #afterTitle2>
            <MailshotJourney :steps="journey" class="ml-4" />
        </template>
    </PageHeading>

    <div class="space-y-4 px-4 py-4">
        <section class="rounded-xl p-5 ring-1" :class="verdictStyle.box" role="status">
            <div class="flex items-start gap-3">
                <FontAwesomeIcon :icon="verdictStyle.icon" fixed-width aria-hidden="true" class="mt-0.5 text-lg" :class="verdictStyle.text" />

                <div class="min-w-0 flex-1">
                    <template v-if="verdict.status === 'accepted'">
                        <h2 class="text-sm font-medium" :class="verdictStyle.text">{{ ctrans("Google accepts this campaign as written") }}</h2>
                        <p class="mt-1 text-sm text-gray-700">
                            {{ ctrans("Google checked everything below without creating anything. Publishing creates it at Google paused, so nothing is shown and nothing is spent until you switch it on from the campaign's page.") }}
                        </p>
                    </template>

                    <template v-else-if="verdict.status === 'refused'">
                        <h2 class="text-sm font-medium" :class="verdictStyle.text">{{ ctrans("Google would refuse this campaign") }}</h2>
                        <p class="mt-1 text-sm text-gray-700">{{ ctrans("Nothing was created. Change what Google names here, then review it again.") }}</p>
                        <ul class="mt-2 list-inside list-disc space-y-0.5 text-sm text-gray-800">
                            <li v-for="message in verdict.messages" :key="message">{{ message }}</li>
                        </ul>
                    </template>

                    <template v-else>
                        <h2 class="text-sm font-medium" :class="verdictStyle.text">{{ ctrans("Not ready for Google yet") }}</h2>
                        <p class="mt-1 text-sm text-gray-700">{{ ctrans("Google was not asked, because these are still empty:") }}</p>
                        <ul class="mt-2 list-inside list-disc space-y-0.5 text-sm text-gray-800">
                            <li v-for="message in verdict.messages" :key="message">{{ message }}</li>
                        </ul>
                    </template>

                    <Link
                        v-if="verdict.status !== 'accepted'"
                        :href="route(compose_route.name, compose_route.parameters)"
                        class="mt-3 inline-flex items-center gap-1.5 text-sm text-indigo-600 underline-offset-2 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                        <FontAwesomeIcon icon="fal fa-arrow-left" fixed-width aria-hidden="true" />
                        {{ ctrans("Back to compose") }}
                    </Link>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200">
                <h2 class="text-sm font-medium text-gray-800">{{ ctrans("The campaign") }}</h2>

                <dl class="mt-3 space-y-2.5 text-sm">
                    <div v-for="row in settings" :key="row.label">
                        <dt class="text-xs text-gray-500">{{ row.label }}</dt>
                        <dd class="mt-0.5 break-words text-gray-800">
                            <a v-if="row.isUrl" :href="row.value" target="_blank" rel="noopener" class="text-indigo-600 underline-offset-2 hover:underline">
                                {{ row.value }}
                            </a>
                            <template v-else>{{ row.value }}</template>
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-2">
                <h2 class="text-sm font-medium text-gray-800">{{ ctrans("What the ad says") }}</h2>

                <div class="mt-3 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <h3 class="text-xs text-gray-500">{{ ctrans("Headlines") }} ({{ (data.headlines ?? []).length }})</h3>
                        <ol class="mt-1 space-y-1 text-sm text-gray-800">
                            <li v-for="(headline, index) in data.headlines ?? []" :key="index" class="flex justify-between gap-3">
                                <span class="break-words">{{ headline }}</span>
                                <span class="shrink-0 tabular-nums text-xs text-gray-400">{{ headline.length }}/30</span>
                            </li>
                        </ol>
                        <p v-if="data.long_headline" class="mt-3 text-xs text-gray-500">{{ ctrans("Long headline") }}</p>
                        <p v-if="data.long_headline" class="mt-0.5 text-sm text-gray-800">{{ data.long_headline }}</p>
                    </div>

                    <div>
                        <h3 class="text-xs text-gray-500">{{ ctrans("Descriptions") }} ({{ (data.descriptions ?? []).length }})</h3>
                        <ol class="mt-1 space-y-2 text-sm text-gray-800">
                            <li v-for="(description, index) in data.descriptions ?? []" :key="index">
                                <span class="break-words">{{ description }}</span>
                                <span class="ml-2 tabular-nums text-xs text-gray-400">{{ description.length }}/90</span>
                            </li>
                        </ol>
                    </div>

                    <div v-if="isSearch" class="sm:col-span-2">
                        <h3 class="text-xs text-gray-500">
                            {{ ctrans("Keywords") }} ({{ (data.keywords ?? []).length }}) · {{ ctrans("match type") }} {{ matchTypeLabel }}
                        </h3>
                        <ul class="mt-1.5 flex flex-wrap gap-1.5">
                            <li v-for="keyword in data.keywords ?? []" :key="keyword" class="rounded bg-gray-100 px-2 py-0.5 text-sm text-gray-800">
                                {{ keyword }}
                            </li>
                        </ul>
                    </div>

                    <div v-if="isPmax && (data.search_themes ?? []).length" class="sm:col-span-2">
                        <h3 class="text-xs text-gray-500">{{ ctrans("Search themes") }} ({{ data.search_themes.length }})</h3>
                        <ul class="mt-1.5 flex flex-wrap gap-1.5">
                            <li v-for="theme in data.search_themes" :key="theme" class="rounded bg-gray-100 px-2 py-0.5 text-sm text-gray-800">
                                {{ theme }}
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <section v-if="imageRoles.length" class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-3">
                <h2 class="text-sm font-medium text-gray-800">{{ ctrans("Images") }}</h2>

                <div class="mt-3 space-y-4">
                    <div v-for="role in imageRoles" :key="role.key">
                        <h3 class="text-xs text-gray-500">{{ role.label }} ({{ images[role.key].length }})</h3>
                        <ul class="mt-1.5 flex flex-wrap gap-2">
                            <li v-for="image in images[role.key]" :key="image.id" class="h-20 w-20 overflow-hidden rounded ring-1 ring-gray-200">
                                <img :src="image.thumbnail" :alt="image.name" :title="image.name" loading="lazy" class="h-full w-full object-cover" />
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
