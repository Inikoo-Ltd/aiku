<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { useLocaleStore } from "@/Stores/locale"
import { youtubeUrl } from "@/Composables/googleAdsCriteria"

type Asset = {
    field_type: string
    primary_status: string | null
    text: string | null
    name: string | null
    url: string | null
    video_id: string | null
    video_title: string | null
    call_to_action: string | null
}

/**
 * A Performance Max campaign's asset groups: the creative Google assembles ads from, the search
 * themes and audience signals that steer where it shows them, and what each group earned. Nothing
 * here can be edited from Aiku; the signals in particular are read-only because they are hints to
 * Google's targeting, not rules, and a page that offered to "remove" one would promise more than
 * Google delivers.
 */
const props = defineProps<{
    assetGroups: {
        id: string
        name: string | null
        status: string | null
        ad_strength: string | null
        primary_status: string | null
        final_urls: string[]
        search_themes: string[]
        audience_signals: string[]
        assets: { texts: Asset[]; images: Asset[]; logos: Asset[]; videos: Asset[]; other: Asset[] }
        metrics: {
            impressions: number
            clicks: number
            cost: number
            conversions: number
            conversions_value: number
            roas: number | null
        } | null
    }[]
    currency: string
    windowLabel: string | null
}>()

const locale = useLocaleStore()

const money = (value: number) => locale.currencyFormat(props.currency, value)

const enumLabel = (value: string | null) => (value ? value.replace(/_/g, " ").toLowerCase() : "")

const strengthClass = (strength: string | null) => {
    if (strength === "POOR") return "bg-[#fdeaea] text-[#d03b3b]"
    if (strength === "EXCELLENT" || strength === "GOOD") return "bg-[#eaf5ea] text-[#006300]"
    if (strength === "AVERAGE") return "bg-amber-50 text-[#a15c00]"
    return "bg-gray-100 text-gray-500"
}

const textBuckets = [
    { field: "HEADLINE", label: trans("Headlines") },
    { field: "LONG_HEADLINE", label: trans("Long headlines") },
    { field: "DESCRIPTION", label: trans("Descriptions") },
    { field: "BUSINESS_NAME", label: trans("Business name") },
    { field: "CALL_TO_ACTION_SELECTION", label: trans("Button") },
]

const textsByField = (texts: Asset[], field: string) => texts.filter((asset) => asset.field_type === field)

const textOf = (asset: Asset) => asset.text ?? asset.call_to_action ?? asset.name ?? ""

const notRunning = (asset: Asset) => asset.primary_status !== null && asset.primary_status !== "ELIGIBLE"

const groups = computed(() => props.assetGroups)
</script>

<template>
    <div class="space-y-4">
        <div v-for="group in groups" :key="group.id" class="rounded-lg p-3 ring-1 ring-gray-100">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="font-medium text-gray-700">{{ group.name ?? group.id }}</span>
                <span class="capitalize text-gray-500">{{ enumLabel(group.status) }}</span>
                <span
                    v-if="group.primary_status && group.primary_status !== 'ELIGIBLE'"
                    class="rounded bg-amber-50 px-1.5 py-0.5 text-[11px] capitalize text-[#a15c00]">
                    {{ enumLabel(group.primary_status) }}
                </span>
                <span class="rounded px-1.5 py-0.5 text-[11px] capitalize" :class="strengthClass(group.ad_strength)">
                    {{ group.ad_strength ? enumLabel(group.ad_strength) : trans("not rated yet") }}
                </span>
            </div>

            <div v-if="group.metrics" class="mt-2 grid grid-cols-3 gap-2 text-xs sm:grid-cols-6">
                <div>
                    <div class="text-gray-500">{{ trans("Impressions") }}</div>
                    <div class="tabular-nums text-gray-800">{{ locale.number(group.metrics.impressions) }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ trans("Clicks") }}</div>
                    <div class="tabular-nums text-gray-800">{{ locale.number(group.metrics.clicks) }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ trans("Cost") }}</div>
                    <div class="tabular-nums text-gray-800">{{ money(group.metrics.cost) }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ trans("Conversions") }}</div>
                    <div class="tabular-nums text-gray-800">{{ locale.number(group.metrics.conversions) }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ trans("Conv. value") }}</div>
                    <div class="tabular-nums text-gray-800">{{ money(group.metrics.conversions_value) }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ trans("ROAS") }}</div>
                    <div class="tabular-nums" :class="group.metrics.roas === null ? 'text-gray-400' : group.metrics.roas >= 1 ? 'text-[#006300]' : 'text-[#d03b3b]'">
                        {{ group.metrics.roas === null ? "—" : group.metrics.roas.toFixed(2) + "×" }}
                    </div>
                </div>
            </div>
            <p v-if="group.metrics && windowLabel" class="mt-0.5 text-[11px] text-gray-400">{{ windowLabel }}</p>

            <dl class="mt-3 space-y-2 text-xs">
                <div v-if="group.search_themes.length" class="flex flex-wrap gap-x-3 gap-y-1">
                    <dt class="w-36 shrink-0 text-gray-500">{{ trans("Search themes") }}</dt>
                    <dd class="flex flex-wrap gap-1">
                        <span v-for="theme in group.search_themes" :key="theme" class="rounded bg-gray-100 px-2 py-0.5 text-gray-700">{{ theme }}</span>
                    </dd>
                </div>
                <div v-if="group.audience_signals.length" class="flex flex-wrap gap-x-3 gap-y-1">
                    <dt class="w-36 shrink-0 text-gray-500">{{ trans("Audience signals") }}</dt>
                    <dd class="flex flex-wrap gap-1">
                        <span v-for="signal in group.audience_signals" :key="signal" class="rounded bg-gray-100 px-2 py-0.5 text-gray-700">{{ signal }}</span>
                    </dd>
                </div>

                <template v-for="bucket in textBuckets" :key="bucket.field">
                    <div v-if="textsByField(group.assets.texts, bucket.field).length" class="flex flex-wrap gap-x-3 gap-y-1">
                        <dt class="w-36 shrink-0 text-gray-500">{{ bucket.label }}</dt>
                        <dd class="flex flex-wrap gap-1">
                            <span
                                v-for="asset in textsByField(group.assets.texts, bucket.field)"
                                :key="asset.text ?? asset.name ?? ''"
                                class="rounded px-2 py-0.5"
                                :class="notRunning(asset) ? 'bg-gray-50 text-gray-400 line-through' : 'bg-gray-100 text-gray-700'"
                                :title="notRunning(asset) ? enumLabel(asset.primary_status) : ''">
                                {{ textOf(asset) }}
                            </span>
                        </dd>
                    </div>
                </template>

                <div v-if="group.assets.images.length" class="flex flex-wrap gap-x-3 gap-y-1">
                    <dt class="w-36 shrink-0 text-gray-500">{{ trans("Images") }}</dt>
                    <dd class="flex flex-wrap gap-2">
                        <a
                            v-for="asset in group.assets.images"
                            :key="asset.url ?? asset.name ?? ''"
                            :href="asset.url ?? undefined"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="block rounded ring-1 ring-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            :class="notRunning(asset) ? 'opacity-40' : ''"
                            :title="(asset.name ?? '') + (notRunning(asset) ? ' · ' + enumLabel(asset.primary_status) : '')">
                            <img v-if="asset.url" :src="asset.url" :alt="asset.name ?? trans('Image')" loading="lazy" class="h-20 w-auto max-w-[12rem] rounded object-contain" />
                        </a>
                    </dd>
                </div>

                <div v-if="group.assets.logos.length" class="flex flex-wrap gap-x-3 gap-y-1">
                    <dt class="w-36 shrink-0 text-gray-500">{{ trans("Logos") }}</dt>
                    <dd class="flex flex-wrap items-center gap-2">
                        <img
                            v-for="asset in group.assets.logos"
                            :key="asset.url ?? asset.name ?? ''"
                            :src="asset.url ?? undefined"
                            :alt="asset.name ?? trans('Logo')"
                            loading="lazy"
                            class="h-8 w-auto rounded ring-1 ring-gray-100"
                            :class="notRunning(asset) ? 'opacity-40' : ''" />
                    </dd>
                </div>

                <div v-if="group.assets.videos.length" class="flex flex-wrap gap-x-3 gap-y-1">
                    <dt class="w-36 shrink-0 text-gray-500">{{ trans("Videos") }}</dt>
                    <dd class="space-y-0.5">
                        <div v-for="asset in group.assets.videos" :key="asset.video_id ?? asset.name ?? ''">
                            <a v-if="asset.video_id" :href="youtubeUrl(asset.video_id)" target="_blank" rel="noopener noreferrer" class="primaryLink" :class="notRunning(asset) ? 'line-through opacity-60' : ''">
                                {{ asset.video_title ?? asset.name ?? asset.video_id }}
                            </a>
                            <span v-else class="text-gray-500">{{ asset.name ?? trans("Video") }}</span>
                        </div>
                    </dd>
                </div>

                <div v-if="group.final_urls.length" class="flex flex-wrap gap-x-3 gap-y-1">
                    <dt class="w-36 shrink-0 text-gray-500">{{ trans("Lands on") }}</dt>
                    <dd>
                        <a v-for="url in group.final_urls" :key="url" :href="url" target="_blank" rel="noopener noreferrer" class="primaryLink block truncate">{{ url }}</a>
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</template>
