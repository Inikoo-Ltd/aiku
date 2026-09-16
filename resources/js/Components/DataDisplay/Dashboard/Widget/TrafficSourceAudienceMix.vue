<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { ctrans } from "@/Composables/useTrans"

/**
 * Who the advertising reached, split by what each person was at the moment they clicked.
 *
 * Shares are taken over the people we could identify, not over every click. Most arrivals are never
 * signed in, so a share of the total would render every bucket as a sliver and the honest reading of
 * the chart would be that nothing happens. The coverage is stated underneath instead, where it can be
 * read as the limit it is rather than mistaken for a finding.
 */
const props = defineProps<{
    mix: {
        buckets: {
            key: string
            label: string
            description: string
            colour: string
            count: number
            share: number
            is_acquisition: boolean
        }[]
        total: number
        identified: number
        acquisition: number
        window_days: number
        measured_from: string | null
    } | null
}>()

const identified = computed(() => props.mix?.identified ?? 0)

const known = computed(() =>
    (props.mix?.buckets ?? [])
        .filter((bucket) => bucket.key !== "anonymous" && bucket.count > 0)
        .map((bucket) => ({
            ...bucket,
            portion: identified.value > 0 ? (bucket.count / identified.value) * 100 : 0,
        }))
)

const unidentified = computed(
    () => props.mix?.buckets.find((bucket) => bucket.key === "anonymous")?.count ?? 0
)

const acquisitionShare = computed(() =>
    identified.value > 0 ? Math.round(((props.mix?.acquisition ?? 0) / identified.value) * 100) : null
)

const coverage = computed(() =>
    props.mix && props.mix.total > 0 ? Math.round((identified.value / props.mix.total) * 100) : 0
)
</script>

<template>
    <div v-if="mix">
        <div class="flex items-baseline justify-between gap-4">
            <h3 class="text-sm font-medium text-gray-700">{{ trans("Who the ads reached") }}</h3>
            <span class="text-xs text-gray-500">
                {{ ctrans("Counted within :days days of the click", { days: mix.window_days }) }}
            </span>
        </div>

        <template v-if="identified > 0">
            <p class="mt-1 text-xs text-gray-500">
                {{
                    ctrans("Of the :identified arrivals we could put a name to", {
                        identified: identified.toLocaleString(),
                    })
                }}
            </p>

            <div
                class="mt-3 flex h-2.5 w-full overflow-hidden rounded-full bg-gray-100"
                role="img"
                :aria-label="trans('Audience split')">
                <div
                    v-for="bucket in known"
                    :key="bucket.key"
                    class="h-full"
                    :style="{ width: `${bucket.portion}%`, backgroundColor: bucket.colour }"
                    :title="`${bucket.label}: ${bucket.count}`" />
            </div>

            <dl class="mt-4 space-y-2.5">
                <div v-for="bucket in known" :key="bucket.key" class="flex items-start gap-2.5">
                    <span
                        class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                        :style="{ backgroundColor: bucket.colour }" />
                    <div class="min-w-0 flex-1">
                        <dt class="flex items-baseline justify-between gap-3">
                            <span class="text-xs font-medium text-gray-700">{{ bucket.label }}</span>
                            <span class="shrink-0 text-xs tabular-nums text-gray-500">
                                {{ bucket.count.toLocaleString() }}
                                <span class="text-gray-400">· {{ Math.round(bucket.portion) }}%</span>
                            </span>
                        </dt>
                        <dd class="mt-0.5 text-xs leading-snug text-gray-500">{{ bucket.description }}</dd>
                    </div>
                </div>
            </dl>

            <p v-if="acquisitionShare !== null" class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-600">
                {{
                    ctrans(":share% of the people you can identify were not customers when they clicked.", {
                        share: acquisitionShare,
                    })
                }}
            </p>
        </template>

        <p v-else class="mt-3 text-xs text-gray-500">
            {{
                trans(
                    "Nobody who clicked was signed in, so there is no split to show yet. It fills in as visitors sign in or register after an ad."
                )
            }}
        </p>

        <p class="mt-2 text-xs text-gray-400">
            {{
                ctrans(":unidentified of :total clicks were never signed in and cannot be classified.", {
                    unidentified: unidentified.toLocaleString(),
                    total: (mix.total ?? 0).toLocaleString(),
                })
            }}
            <span v-if="coverage < 25">{{ trans("Read the split as a sample, not a census.") }}</span>
        </p>

        <p v-if="mix.measured_from" class="mt-1 text-xs text-gray-400">
            {{ ctrans("Clicks are kept for 90 days. This covers arrivals since :date.", { date: mix.measured_from }) }}
        </p>
    </div>
</template>
