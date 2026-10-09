<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPaperPlane, faShoppingBasket } from "@fal"
library.add(faPaperPlane, faShoppingBasket)

interface MiniCartLine {
    id: number
    quantity: number
    org_stock_code: string | null
    org_stock_name: string | null
    family_name: string | null
    created_at?: string
}

interface MiniCartRoute {
    name: string
    parameters: (string | number)[] | Record<string, string | number>
}

const props = defineProps<{
    miniCart: {
        partner_name: string
        title?: string
        list_label?: string
        count: number
        total: number
        currency: string
        items: MiniCartLine[]
        listRoute: MiniCartRoute
        ordered?: {
            count: number
            total: number
            items: MiniCartLine[]
            listRoute: MiniCartRoute
        }
    }
}>()

const locale = useLocaleStore()

const groupedItems = computed(() => {
    const groups = new Map<string, MiniCartLine[]>()
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
            <div class="mt-1 text-gray-400">{{ miniCart.title ?? ctrans("Shopping list") }}</div>
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
                    <td colspan="3" class="pt-2 pb-0.5 text-[10px] font-semibold uppercase tracking-wide text-[--app-accent]">
                        {{ family || ctrans("Other") }}
                    </td>
                </tr>
                <tr v-for="item in items" :key="item.id">
                    <td class="py-0.5 pr-2 align-baseline text-gray-400">{{ item.org_stock_code }}</td>
                    <td class="py-0.5 align-baseline"><div class="truncate">{{ item.org_stock_name }}</div></td>
                    <td class="py-0.5 pl-2 text-right align-baseline">{{ locale.number(item.quantity) }}</td>
                </tr>
            </tbody>
            <tbody v-if="miniCart.count > miniCart.items.length">
                <tr>
                    <td colspan="3" class="pt-1">
                        <Link
                            :href="route(miniCart.listRoute.name, miniCart.listRoute.parameters)"
                            class="text-gray-400 underline decoration-dotted underline-offset-2 hover:text-[--app-accent]"
                        >
                            … + {{ locale.number(miniCart.count - miniCart.items.length) }} {{ ctrans("more, see full list") }}
                        </Link>
                    </td>
                </tr>
            </tbody>
        </table>
        <div v-else class="text-center text-gray-400">{{ ctrans("Empty") }}</div>

        <div class="my-3 border-t border-dashed border-gray-300" />

        <div class="flex items-baseline justify-between text-sm font-semibold tabular-nums">
            <span>{{ miniCart.title ? ctrans(":title total", { title: miniCart.title }) : ctrans("Total") }} · {{ ctrans(":count lines", { count: locale.number(miniCart.count) }) }}</span>
            <span v-if="miniCart.total">{{ locale.currencyFormat(miniCart.currency, miniCart.total) }}</span>
        </div>

        <Link :href="route(miniCart.listRoute.name, miniCart.listRoute.parameters)" class="mt-4 block">
            <Button full :label="miniCart.list_label ?? ctrans('Go to Shopping list')" type="tertiary" icon="fal fa-shopping-basket" />
        </Link>

        <template v-if="miniCart.ordered">
            <div class="mt-5 border-t-2 border-double border-gray-300 pt-4">
                <div class="flex items-baseline justify-between">
                    <span class="flex items-center gap-1.5 font-semibold uppercase tracking-wide">
                        <FontAwesomeIcon icon="fal fa-paper-plane" fixed-width aria-hidden="true" />
                        {{ ctrans("Already ordered") }}
                    </span>
                    <span class="tabular-nums text-gray-500">{{ ctrans(":count lines", { count: locale.number(miniCart.ordered.count) }) }}</span>
                </div>
                <p class="mt-1 font-sans text-[11px] text-gray-400">
                    {{ ctrans("Submitted to :partner and not delivered yet, check here before ordering again", { partner: miniCart.partner_name }) }}
                </p>
            </div>

            <table v-if="miniCart.ordered.items.length" class="mt-2 w-full table-fixed tabular-nums">
                <colgroup>
                    <col class="w-24" />
                    <col />
                    <col class="w-12" />
                </colgroup>
                <tbody>
                    <tr v-for="item in miniCart.ordered.items" :key="item.id">
                        <td class="py-0.5 pr-2 align-baseline text-gray-400">{{ item.org_stock_code }}</td>
                        <td class="py-0.5 align-baseline">
                            <div class="truncate">{{ item.org_stock_name }}</div>
                            <div v-if="item.created_at" class="text-[10px] text-gray-400">
                                {{ useFormatTime(item.created_at, { formatTime: "d MMM" }) }}
                            </div>
                        </td>
                        <td class="py-0.5 pl-2 text-right align-baseline">{{ locale.number(item.quantity) }}</td>
                    </tr>
                </tbody>
                <tbody v-if="miniCart.ordered.count > miniCart.ordered.items.length">
                    <tr>
                        <td colspan="3" class="pt-1">
                            <Link
                                :href="route(miniCart.ordered.listRoute.name, miniCart.ordered.listRoute.parameters)"
                                class="text-gray-400 underline decoration-dotted underline-offset-2 hover:text-[--app-accent]"
                            >
                                … + {{ locale.number(miniCart.ordered.count - miniCart.ordered.items.length) }} {{ ctrans("more, see Orders") }}
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="mt-2 text-center text-gray-400">{{ ctrans("Nothing ordered yet") }}</div>

            <div v-if="miniCart.ordered.total" class="mt-2 flex items-baseline justify-between border-t border-dashed border-gray-300 pt-2 tabular-nums text-gray-500">
                <span>{{ ctrans("Ordered value") }}</span>
                <span>{{ locale.currencyFormat(miniCart.currency, miniCart.ordered.total) }}</span>
            </div>
        </template>
    </div>
</template>
