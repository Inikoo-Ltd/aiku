<script setup lang='ts'>
import ShowcaseContactCard from "@/Components/ShowcaseContactCard.vue"
import PartnerMiniShoppingList from "@/Components/Procurement/PartnerMiniShoppingList.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
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
        customerAccounts: {
            shop: string
            reference: string
            name: string
            route: { name: string, parameters: string[] }
        }[]
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

            <div class="mt-4 max-w-md xl:max-w-lg bg-white border border-gray-200 rounded-lg px-3 py-2 shadow-sm text-sm">
                <div class="font-semibold text-gray-700 mb-1">{{ ctrans("Customer accounts") }}</div>
                <div v-if="!data.customerAccounts?.length" class="text-gray-400">{{ ctrans("No customer account in our shops") }}</div>
                <div v-for="account in data.customerAccounts" :key="account.reference + account.shop" class="flex gap-2 py-0.5">
                    <span class="text-gray-400 w-32 shrink-0 truncate">{{ account.shop }}</span>
                    <Link :href="route(account.route.name, account.route.parameters)" class="primaryLink">{{ account.reference }}</Link>
                    <span class="truncate">{{ account.name }}</span>
                </div>
            </div>

            <div class="mt-4 max-w-md xl:max-w-lg">
                <PartnerMiniShoppingList :miniCart="data.miniCart" />
            </div>
        </div>
    </div>
</template>
