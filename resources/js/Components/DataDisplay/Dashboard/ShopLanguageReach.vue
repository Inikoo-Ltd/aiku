<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faGlobe } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

library.add(faGlobe)

interface LanguageRow {
    code: string
    language: string
    is_main: boolean
    is_preview: boolean
    is_offered: boolean
    customers: number
    customers_new_30d: number
    customers_route: routeType
}

interface Platform {
    type: string
    label: string
    logo: string | null
}

interface Counts {
    channels: number
    products: number
}

interface LanguageReach {
    channel_languages: boolean
    languages: LanguageRow[]
    channels: {
        platforms: Platform[]
        by_platform: (Platform & Counts)[]
        by_language: ({ code: string, language: string } & Counts)[]
        matrix: ({ type: string, code: string } & Counts)[]
    }
}

const props = defineProps<{
    data?: LanguageReach
}>()

const locale = useLocaleStore()
const MAX_LANGUAGE_CARDS = 6

const totalCustomers = computed(() => (props.data?.languages ?? []).reduce((sum, row) => sum + row.customers, 0))
const mainLanguage = computed(() => props.data?.languages.find(row => row.is_main))

const languageCards = computed(() => {
    const used = (props.data?.languages ?? []).filter(row => row.customers > 0)
    if (used.length <= MAX_LANGUAGE_CARDS) {
        return { cards: used, other: 0 }
    }
    const cards = used.slice(0, MAX_LANGUAGE_CARDS - 1)

    return { cards, other: used.slice(MAX_LANGUAGE_CARDS - 1).reduce((sum, row) => sum + row.customers, 0) }
})

const unusedLanguages = computed(() => (props.data?.languages ?? [])
    .filter(row => row.is_offered && row.customers === 0)
    .map(row => row.is_preview ? `${row.language} (${ctrans("staff only")})` : row.language)
    .join(", "))

const share = (customers: number) => {
    const value = totalCustomers.value ? (customers / totalCustomers.value) * 100 : 0

    return value > 0 && value < 1 ? value.toFixed(1) : Math.round(value).toString()
}

const platformCounts = (type: string): Counts => {
    const row = props.data?.channels.by_platform.find(platform => platform.type === type)

    return { channels: row?.channels ?? 0, products: row?.products ?? 0 }
}

const platformCards = computed(() => (props.data?.channels.platforms ?? [])
    .map(platform => ({ ...platform, ...platformCounts(platform.type) }))
    .filter(platform => platform.channels > 0))

const matrixColumns = computed(() => props.data?.channel_languages
    ? [...props.data.channels.by_language.map(row => ({ code: row.code, language: row.language })), { code: "total", language: ctrans("Total") }]
    : [{ code: "total", language: ctrans("Total") }])

const matrixCell = (type: string, code: string): Counts => {
    if (code === "total") {
        return platformCounts(type)
    }
    const row = props.data?.channels.matrix.find(item => item.type === type && item.code === code)

    return { channels: row?.channels ?? 0, products: row?.products ?? 0 }
}

const connectedTotal = (code: string): Counts => (props.data?.channels.platforms ?? [])
    .filter(platform => platform.type !== "manual")
    .reduce((total, platform) => {
        const counts = matrixCell(platform.type, code)

        return { channels: total.channels + counts.channels, products: total.products + counts.products }
    }, { channels: 0, products: 0 })
</script>

<template>
    <div v-if="!data" class="space-y-4 p-4" aria-busy="true">
        <div class="grid grid-cols-2 gap-4 xl:grid-cols-6">
            <div v-for="index in 6" :key="index" class="skeleton h-24 rounded-lg" />
        </div>
        <div class="skeleton h-48 rounded-lg" />
    </div>

    <div v-else class="space-y-4 p-4">
        <div>
            <p class="flex items-center gap-2 text-lg font-bold">
                <FontAwesomeIcon icon="fal fa-globe" class="text-gray-400" fixed-width aria-hidden="true" />
                {{ ctrans("Languages and stores") }}
            </p>
            <p class="text-xs text-gray-500">{{ ctrans("Which languages customers use and where their stores are") }}</p>
        </div>

        <div>
            <p class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("Customers per language") }}</p>
            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
                <div v-for="row in languageCards.cards" :key="row.code" class="rounded-lg border bg-white p-4 shadow-sm">
                    <p class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-sm font-semibold text-gray-700">
                        {{ row.language }}
                        <span class="text-xs font-normal uppercase text-gray-400">{{ row.code }}</span>
                        <span v-if="row.is_preview" class="rounded-full border border-amber-300 bg-amber-50 px-1.5 py-px text-[10px] font-medium text-amber-700">
                            {{ ctrans("Staff only") }}
                        </span>
                        <span v-else-if="!row.is_offered" class="rounded-full border border-gray-200 px-1.5 py-px text-[10px] font-medium text-gray-400">
                            {{ ctrans("Not offered") }}
                        </span>
                    </p>
                    <Link :href="route(row.customers_route.name, row.customers_route.parameters)" class="text-2xl font-bold tabular-nums text-gray-900 hover:underline">
                        {{ locale.number(row.customers) }}
                    </Link>
                    <p class="text-xs text-gray-500">
                        {{ ctrans(":percent% of customers", { percent: share(row.customers) }) }}
                        <template v-if="row.customers_new_30d"> · {{ ctrans("+:count in 30 days", { count: locale.number(row.customers_new_30d) }) }}</template>
                    </p>
                </div>
                <div v-if="languageCards.other" class="rounded-lg border bg-white p-4 shadow-sm">
                    <p class="text-sm font-semibold text-gray-700">{{ ctrans("Other") }}</p>
                    <p class="text-2xl font-bold tabular-nums text-gray-900">{{ locale.number(languageCards.other) }}</p>
                    <p class="text-xs text-gray-500">{{ ctrans(":percent% of customers", { percent: share(languageCards.other) }) }}</p>
                </div>
            </div>
            <p v-if="unusedLanguages" class="mt-2 text-xs text-gray-500">
                {{ ctrans("Offered with no customers yet: :languages", { languages: unusedLanguages }) }}
            </p>
        </div>

        <p v-if="!platformCards.length" class="text-sm italic text-gray-400">{{ ctrans("No connected stores yet") }}</p>

        <template v-else>
            <div>
                <p class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("Connected stores per platform") }}</p>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-7">
                    <div v-for="platform in platformCards" :key="platform.type" class="rounded-lg border bg-white p-4 shadow-sm">
                        <p class="flex items-center gap-2 whitespace-nowrap text-sm font-semibold text-gray-700">
                            <img v-if="platform.logo" :src="platform.logo" :alt="platform.label" class="h-5 w-5 object-contain" />
                            {{ platform.label }}
                        </p>
                        <p class="text-2xl tabular-nums" :class="platform.type === 'manual' ? 'font-semibold text-gray-500' : 'font-bold text-gray-900'">
                            {{ locale.number(platform.channels) }}
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ platform.type === "manual"
                                ? ctrans("By hand or API, not a store")
                                : ctrans(":count products listed", { count: locale.number(platform.products) }) }}
                        </p>
                    </div>
                </div>
            </div>

            <div>
                <p class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("Stores per language") }}</p>
                <p v-if="!data.channel_languages" class="text-xs text-gray-500">
                    {{ ctrans("All stores use :language. Store languages are switched off for this shop.", { language: mainLanguage?.language ?? "" }) }}
                </p>
                <div v-else class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
                    <div v-for="row in data.channels.by_language.filter(item => connectedTotal(item.code).channels > 0)" :key="row.code" class="rounded-lg border bg-white p-4 shadow-sm">
                        <p class="flex items-center gap-1.5 whitespace-nowrap text-sm font-semibold text-gray-700">
                            {{ row.language }} <span class="text-xs font-normal uppercase text-gray-400">{{ row.code }}</span>
                        </p>
                        <p class="text-2xl font-bold tabular-nums text-gray-900">{{ locale.number(connectedTotal(row.code).channels) }}</p>
                        <p class="text-xs text-gray-500">{{ ctrans(":count products listed", { count: locale.number(connectedTotal(row.code).products) }) }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border bg-white p-4 shadow-sm">
                <p class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("Stores by platform and language") }}</p>
                <div class="overflow-x-auto">
                    <table class="text-sm tabular-nums">
                        <thead class="whitespace-nowrap">
                            <tr v-if="data.channel_languages" class="text-left">
                                <th />
                                <th v-for="column in matrixColumns" :key="column.code" colspan="2" class="px-3 pb-1 text-center text-xs font-normal text-gray-600">
                                    {{ column.language }} <span v-if="column.code !== 'total'" class="uppercase text-gray-400">{{ column.code }}</span>
                                </th>
                            </tr>
                            <tr class="text-left">
                                <th class="pb-1 pr-6 text-xs font-normal text-gray-400">{{ ctrans("Platform") }}</th>
                                <template v-for="column in matrixColumns" :key="column.code">
                                    <th class="px-3 pb-1 text-right text-xs font-normal text-gray-400">{{ ctrans("Stores") }}</th>
                                    <th class="px-3 pb-1 text-right text-xs font-normal text-gray-400">{{ ctrans("Products") }}</th>
                                </template>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="platform in data.channels.platforms" :key="platform.type">
                                <td class="whitespace-nowrap py-1.5 pr-6">
                                    <span class="flex items-center gap-2">
                                        <img v-if="platform.logo" :src="platform.logo" :alt="platform.label" class="h-4 w-4 object-contain" />
                                        {{ platform.label }}
                                    </span>
                                </td>
                                <template v-for="column in matrixColumns" :key="column.code">
                                    <td class="px-3 py-1.5 text-right font-semibold">
                                        <span v-if="matrixCell(platform.type, column.code).channels">{{ locale.number(matrixCell(platform.type, column.code).channels) }}</span>
                                        <span v-else class="font-normal text-gray-300">–</span>
                                    </td>
                                    <td class="px-3 py-1.5 text-right text-gray-500">
                                        <span v-if="matrixCell(platform.type, column.code).products">{{ locale.number(matrixCell(platform.type, column.code).products) }}</span>
                                        <span v-else class="text-gray-300">–</span>
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-gray-200 font-semibold">
                            <tr>
                                <td class="whitespace-nowrap py-1.5 pr-6">{{ ctrans("Total connected") }}</td>
                                <template v-for="column in matrixColumns" :key="column.code">
                                    <td class="px-3 py-1.5 text-right">{{ locale.number(connectedTotal(column.code).channels) }}</td>
                                    <td class="px-3 py-1.5 text-right text-gray-500">{{ locale.number(connectedTotal(column.code).products) }}</td>
                                </template>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>
