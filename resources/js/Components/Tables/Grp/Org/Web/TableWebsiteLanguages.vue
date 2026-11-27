<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimesCircle, faBroom } from "@fal"
import Table from "@/Components/Table/Table.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

library.add(faTimesCircle, faBroom)

interface Readiness {
    products: number
    names: number
    descriptions: number
    categories: number
    category_names: number
    failed: number
    to_clean: number
}

interface LanguageRow {
    code: string
    language: string
    is_main: boolean
    is_preview: boolean
    is_offered: boolean
    customers: number
    customers_new_30d: number
    web_users: number
    web_users_new_30d: number
    invoices_30d: number
    sales_30d: number
    invoices_12m: number
    sales_12m: number
    stores: number
    stores_products: number
    readiness: Readiness
    customers_route: routeType
    currency_code: string
}

defineProps<{
    data: object
    tab?: string
}>()

const locale = useLocaleStore()

const percent = (filled: number, total: number) => Math.floor((filled / total) * 100)

const readinessFields = ["names", "descriptions", "categories"] as const

const readiness = (item: LanguageRow, field: typeof readinessFields[number]): [number, number] => ({
    names: [item.readiness.names, item.readiness.products],
    descriptions: [item.readiness.descriptions, item.readiness.products],
    categories: [item.readiness.category_names, item.readiness.categories],
} as Record<string, [number, number]>)[field]
</script>

<template>
    <div>
    <p class="px-4 pt-4 text-xs text-gray-500">
        {{ ctrans("Who uses each language. A customer's language is the one of its main website login, or the shop's.") }}
    </p>
    <Table :resource="data" :name="tab" class="mt-3">
        <template #cell(language)="{ item }: { item: LanguageRow }">
            <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
                {{ item.language }}
                <span class="text-xs uppercase text-gray-400">{{ item.code }}</span>
                <span v-if="item.is_main" class="rounded-full border border-gray-300 bg-gray-50 px-1.5 py-px text-[10px] font-medium text-gray-600">
                    {{ ctrans("Main") }}
                </span>
                <span
                    v-else-if="item.is_preview"
                    v-tooltip="ctrans('Only staff can see this language on the website')"
                    class="rounded-full border border-amber-300 bg-amber-50 px-1.5 py-px text-[10px] font-medium text-amber-700"
                >
                    {{ ctrans("Staff only") }}
                </span>
                <span
                    v-else-if="!item.is_offered"
                    v-tooltip="ctrans('Customers use this language but the website does not offer it')"
                    class="rounded-full border border-gray-200 px-1.5 py-px text-[10px] font-medium text-gray-400"
                >
                    {{ ctrans("Not offered") }}
                </span>
            </span>
        </template>

        <template #cell(customers)="{ item }: { item: LanguageRow }">
            <div class="whitespace-nowrap tabular-nums">
                <Link v-if="item.customers" :href="route(item.customers_route.name, item.customers_route.parameters)" class="primaryLink font-semibold">
                    {{ locale.number(item.customers) }}
                </Link>
                <span v-else class="text-gray-300">–</span>
                <div v-if="item.customers_new_30d" v-tooltip="ctrans('New in the last 30 days')" class="text-xs text-green-600">
                    +{{ locale.number(item.customers_new_30d) }}
                </div>
            </div>
        </template>

        <template #cell(web_users)="{ item }: { item: LanguageRow }">
            <div class="whitespace-nowrap tabular-nums">
                <span v-if="item.web_users">{{ locale.number(item.web_users) }}</span>
                <span v-else class="text-gray-300">–</span>
                <div v-if="item.web_users_new_30d" v-tooltip="ctrans('New in the last 30 days')" class="text-xs text-green-600">
                    +{{ locale.number(item.web_users_new_30d) }}
                </div>
            </div>
        </template>

        <template #cell(invoices_30d)="{ item }: { item: LanguageRow }">
            <span v-if="item.invoices_30d" class="tabular-nums">{{ locale.number(item.invoices_30d) }}</span>
            <span v-else class="text-gray-300">–</span>
        </template>

        <template #cell(sales_30d)="{ item }: { item: LanguageRow }">
            <span v-if="item.sales_30d" class="whitespace-nowrap tabular-nums">{{ locale.currencyFormat(item.currency_code, item.sales_30d) }}</span>
            <span v-else class="text-gray-300">–</span>
        </template>

        <template #cell(invoices_12m)="{ item }: { item: LanguageRow }">
            <span v-if="item.invoices_12m" class="tabular-nums">{{ locale.number(item.invoices_12m) }}</span>
            <span v-else class="text-gray-300">–</span>
        </template>

        <template #cell(sales_12m)="{ item }: { item: LanguageRow }">
            <span v-if="item.sales_12m" class="whitespace-nowrap font-medium tabular-nums">{{ locale.currencyFormat(item.currency_code, item.sales_12m) }}</span>
            <span v-else class="text-gray-300">–</span>
        </template>

        <template #cell(stores)="{ item }: { item: LanguageRow }">
            <span v-if="item.stores" v-tooltip="ctrans(':count products listed', { count: locale.number(item.stores_products) })" class="tabular-nums">
                {{ locale.number(item.stores) }}
            </span>
            <span v-else class="text-gray-300">–</span>
        </template>

        <template v-for="field in readinessFields" :key="field" #[`cell(${field})`]="{ item }: { item: LanguageRow }">
            <span v-if="item.is_main || !readiness(item, field)[1]" class="text-gray-300">–</span>
            <div v-else class="inline-grid grid-cols-[auto_2.5rem] items-baseline gap-x-2 whitespace-nowrap tabular-nums">
                <span class="text-gray-700">{{ locale.number(readiness(item, field)[0]) }} <span class="text-gray-400">/ {{ locale.number(readiness(item, field)[1]) }}</span></span>
                <span class="text-right" :class="percent(...readiness(item, field)) >= 100 ? 'text-green-600' : 'text-gray-500'">{{ percent(...readiness(item, field)) }}%</span>
            </div>
        </template>

        <template #cell(issues)="{ item }: { item: LanguageRow }">
            <span v-if="!item.readiness.failed && !item.readiness.to_clean" class="text-gray-300">–</span>
            <div v-else class="inline-flex items-baseline gap-x-3 whitespace-nowrap tabular-nums">
                <span v-if="item.readiness.failed" v-tooltip="ctrans('Translations that failed')" class="font-medium text-red-600">
                    <FontAwesomeIcon icon="fal fa-times-circle" fixed-width aria-hidden="true" /> {{ locale.number(item.readiness.failed) }}
                </span>
                <span v-if="item.readiness.to_clean" v-tooltip="ctrans('Translations still to clean')" class="text-amber-600">
                    <FontAwesomeIcon icon="fal fa-broom" fixed-width aria-hidden="true" /> {{ locale.number(item.readiness.to_clean) }}
                </span>
            </div>
        </template>
    </Table>
    </div>
</template>
