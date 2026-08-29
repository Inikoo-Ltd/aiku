<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"

const props = defineProps<{
    miniCart: {
        partner_name: string
        count: number
        total: number
        currency: string
        items: { id: number, quantity: number, org_stock_code: string | null, org_stock_name: string | null, family_name: string | null }[]
        listRoute: { name: string, parameters: (string | number)[] }
    }
}>()

const groupedItems = computed(() => {
    const groups = new Map<string, typeof props.miniCart.items>()
    for (const item of props.miniCart.items) {
        const key = item.family_name ?? ""
        if (!groups.has(key)) {
            groups.set(key, [])
        }
        groups.get(key)!.push(item)
    }
    return [...groups.entries()]
})
</script>

<template>
    <div class="w-full rounded-sm border border-gray-200 bg-white px-5 py-4 font-mono text-xs text-gray-700 shadow-md">
        <div class="text-center">
            <div class="text-sm font-semibold tracking-widest uppercase">{{ miniCart.partner_name }}</div>
            <div class="mt-1 text-gray-400">{{ trans("Shopping list") }}</div>
        </div>

        <div class="my-3 border-t border-dashed border-gray-300" />

        <table v-if="miniCart.items.length" class="w-full table-fixed tabular-nums">
            <colgroup>
                <col class="w-24" />
                <col />
                <col class="w-12" />
            </colgroup>
            <tbody v-for="[family, items] in groupedItems" :key="family">
                <tr>
                    <td colspan="3" class="pt-2 pb-0.5 uppercase tracking-wide text-[10px] font-semibold text-indigo-500">
                        {{ family || trans("Other") }}
                    </td>
                </tr>
                <tr v-for="item in items" :key="item.id">
                    <td class="py-0.5 pr-2 align-baseline text-gray-400">{{ item.org_stock_code }}</td>
                    <td class="py-0.5 align-baseline"><div class="truncate">{{ item.org_stock_name }}</div></td>
                    <td class="py-0.5 pl-2 text-right align-baseline">{{ useLocaleStore().number(item.quantity) }}</td>
                </tr>
            </tbody>
            <tbody v-if="miniCart.count > miniCart.items.length">
                <tr>
                    <td colspan="3" class="pt-1">
                        <Link
                            :href="route(miniCart.listRoute.name, miniCart.listRoute.parameters)"
                            class="text-gray-400 underline decoration-dotted underline-offset-2 hover:text-indigo-600"
                        >
                            … + {{ useLocaleStore().number(miniCart.count - miniCart.items.length) }} {{ trans("more, see full list") }}
                        </Link>
                    </td>
                </tr>
            </tbody>
        </table>
        <div v-else class="text-center text-gray-400">{{ trans("Empty") }}</div>

        <div class="my-3 border-t border-dashed border-gray-300" />

        <div class="flex items-baseline justify-between text-sm font-semibold tabular-nums">
            <span>{{ trans("Total") }} · {{ useLocaleStore().number(miniCart.count) }} {{ trans("items") }}</span>
            <span>{{ useLocaleStore().currencyFormat(miniCart.currency, miniCart.total) }}</span>
        </div>

        <Link
            :href="route(miniCart.listRoute.name, miniCart.listRoute.parameters)"
            class="mt-4 flex w-full items-center justify-center gap-2 rounded-md bg-indigo-600 px-3 py-1.5 font-sans text-sm font-medium text-white hover:bg-indigo-500"
        >
            <FontAwesomeIcon icon="fal fa-shopping-basket" fixed-width aria-hidden="true" />
            {{ trans("Go to Shopping list") }}
        </Link>
    </div>
</template>
