<script setup lang='ts'>
import ShowcaseContactCard from "@/Components/ShowcaseContactCard.vue"
import { Link } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { useLocaleStore } from "@/Stores/locale"
import { Agent } from '@/types/Grp/Agent'

const props = defineProps<{
    data: {
        contactCard: Agent
        stats: {
            label: string
            icon: string
            count: number
        }[]
        miniCart: {
            count: number
            total: number
            currency: string
            items: { id: number, quantity: number, org_stock_code: string | null, org_stock_name: string | null }[]
            listRoute: { name: string, parameters: (string | number)[] }
        }
    }

}>()
</script>

<template>
   <div class="grid text-gray-600 gap-y-3 md:gap-y-0 md:grid-cols-2 px-4 pt-4 gap-x-6">
        <div>
            <ShowcaseContactCard :data="data.contactCard" />
        </div>

        <div>
            <div class="inline-flex items-center bg-white border border-gray-200 rounded-lg px-3 py-2 shadow-sm text-sm tabular-nums">
                <div
                    v-for="(stat, index) in data.stats"
                    :key="stat.label"
                    v-tooltip="stat.label"
                    class="flex items-center gap-1.5"
                    :class="index ? 'border-l border-gray-200 pl-3 ml-3' : ''"
                >
                    <FontAwesomeIcon :icon="stat.icon" class="text-gray-400" fixed-width aria-hidden="true" />
                    <span class="font-semibold text-gray-700">{{ useLocaleStore().number(stat.count ?? 0) }}</span>
                </div>
            </div>

            <div class="mt-4 max-w-sm rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-2 text-sm font-medium text-gray-700">
                    <FontAwesomeIcon icon="fal fa-shopping-basket" class="text-gray-400" fixed-width aria-hidden="true" />
                    {{ useLocaleStore().number(data.miniCart.count) }} {{ trans("items") }} ·
                    {{ useLocaleStore().currencyFormat(data.miniCart.currency, data.miniCart.total) }}
                </div>
                <div v-if="data.miniCart.items.length" class="mt-2 space-y-1 text-xs text-gray-500 tabular-nums">
                    <div v-for="item in data.miniCart.items" :key="item.id" class="flex justify-between gap-2">
                        <span class="truncate">{{ item.org_stock_code }} · {{ item.org_stock_name }}</span>
                        <span>{{ useLocaleStore().number(item.quantity) }}</span>
                    </div>
                </div>
                <div v-else class="mt-2 text-xs text-gray-400">{{ trans("Empty") }}</div>
                <Link
                    :href="route(data.miniCart.listRoute.name, data.miniCart.listRoute.parameters)"
                    class="mt-3 block w-full rounded-md bg-indigo-600 px-3 py-1.5 text-center text-sm font-medium text-white hover:bg-indigo-500"
                >
                    {{ trans("Go to Shopping list") }}
                </Link>
            </div>
        </div>
    </div>
</template>
