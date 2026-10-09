<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type CitedPagesData = {
    days: number
    pages: {
        url: string
        webpage_code: string | null
        webpage_route: routeType | null
        prompts: number
        citations: number
        best_position: number
        last_cited: string
    }[]
    domains: {
        domain: string
        prompts: number
        citations: number
        competitor: string | null
        is_ours: boolean
    }[]
}

defineOptions({ inheritAttrs: false })

defineProps<{
    data?: CitedPagesData | null
}>()

const locale = useLocaleStore()
</script>

<template>
    <div v-if="data" class="space-y-4 px-4 py-4">
        <section class="rounded-xl bg-white ring-1 ring-gray-200" :aria-label="ctrans('Our cited pages')">
            <div class="border-b border-gray-100 px-5 py-3">
                <h2 class="text-sm font-medium text-gray-900">{{ ctrans("Our pages ChatGPT cites") }}</h2>
                <p class="text-xs text-gray-500">{{ ctrans("Pages of this website given as a source in the answers of the last :days days, by the number of prompts citing them.", { days: data.days }) }}</p>
            </div>

            <p v-if="!data.pages.length" class="px-5 py-4 text-sm text-gray-600">
                {{ ctrans("No page of ours was cited in the last :days days.", { days: data.days }) }}
            </p>

            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-xs text-gray-500">
                        <th scope="col" class="px-5 py-2 font-medium">{{ ctrans("Page") }}</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium">{{ ctrans("Prompts") }}</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium" v-tooltip="ctrans('Times cited, counting every weekly answer')">{{ ctrans("Citations") }}</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium" v-tooltip="ctrans('Best place among the sources of an answer. 1 is the first source.')">{{ ctrans("Best place") }}</th>
                        <th scope="col" class="px-5 py-2 text-right font-medium">{{ ctrans("Last cited") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="page in data.pages" :key="page.url">
                        <td class="max-w-xl px-5 py-2">
                            <Link v-if="page.webpage_route" :href="route(page.webpage_route.name, page.webpage_route.parameters)" class="primaryLink">{{ page.webpage_code }}</Link>
                            <a :href="page.url" target="_blank" rel="noopener noreferrer" class="block truncate text-xs text-gray-500 underline-offset-2 hover:underline focus-visible:underline">{{ page.url }}</a>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ locale.number(page.prompts) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ locale.number(page.citations) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ locale.number(page.best_position) }}</td>
                        <td class="whitespace-nowrap px-5 py-2 text-right">{{ useFormatTime(page.last_cited) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="rounded-xl bg-white ring-1 ring-gray-200" :aria-label="ctrans('Most cited websites')">
            <div class="border-b border-gray-100 px-5 py-3">
                <h2 class="text-sm font-medium text-gray-900">{{ ctrans("Websites ChatGPT cites most") }}</h2>
                <p class="text-xs text-gray-500">{{ ctrans("The sources ChatGPT trusts for our prompts. Those that are not competitors are places worth being listed or linked from.") }}</p>
            </div>

            <p v-if="!data.domains.length" class="px-5 py-4 text-sm text-gray-600">{{ ctrans("No answer with sources yet.") }}</p>

            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-xs text-gray-500">
                        <th scope="col" class="px-5 py-2 font-medium">{{ ctrans("Website") }}</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium">{{ ctrans("Prompts") }}</th>
                        <th scope="col" class="px-5 py-2 text-right font-medium">{{ ctrans("Citations") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="domain in data.domains" :key="domain.domain" :class="domain.is_ours ? 'bg-[--app-accent-soft]' : ''">
                        <td class="px-5 py-2">
                            {{ domain.domain }}
                            <span v-if="domain.is_ours" class="ml-1 text-xs text-gray-500">{{ ctrans("ours") }}</span>
                            <span v-else-if="domain.competitor" class="ml-1 text-xs text-gray-500">{{ ctrans("competitor :name", { name: domain.competitor }) }}</span>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ locale.number(domain.prompts) }}</td>
                        <td class="px-5 py-2 text-right tabular-nums">{{ locale.number(domain.citations) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
