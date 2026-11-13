<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck } from "@fas"
import { faSearch, faTachometerAlt, faUser, faComments, faTerminal, faUserHeadset } from "@fal"

library.add(faCheck, faSearch, faTachometerAlt, faUser, faComments, faTerminal, faUserHeadset)

const entryPointIcons: Record<string, object> = {
    search: faSearch,
    panel: faTachometerAlt,
    profile: faUser,
    chat: faComments,
    command: faTerminal,
}

const props = defineProps<{
    data: object
    tab?: string
    routes: { conversation: { name: string; parameters: Record<string, any> } }
    currency: string
    costCurrency: string
}>()

const locale = useLocaleStore()

const conversationHref = (ulid: string) => route(props.routes.conversation.name, { ...props.routes.conversation.parameters, conversation: ulid })

const customerHref = (customerRoute: { name: string; parameters: Record<string, any> } | null) => (customerRoute ? route(customerRoute.name, customerRoute.parameters) : null)
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-2">
        <template #cell(created_at)="{ item }">
            <Link :href="conversationHref(item.ulid)" class="primaryLink whitespace-nowrap">{{ useFormatTime(item.created_at, { formatTime: "hm" }) }}</Link>
        </template>

        <template #cell(customer_name)="{ item }">
            <Link v-if="customerHref(item.customer_route)" :href="customerHref(item.customer_route) ?? ''" class="secondaryLink inline-block max-w-40 truncate align-bottom" :title="item.customer_name">{{ item.customer_name }}</Link>
            <span v-else-if="item.customer_name" class="inline-block max-w-40 truncate align-bottom" :title="item.customer_name">{{ item.customer_name }}</span>
            <span v-else class="text-gray-400">{{ ctrans("Guest") }}</span>
        </template>

        <template #cell(website)="{ item }">
            <span class="whitespace-nowrap text-gray-600">{{ item.website }}</span>
        </template>

        <template #cell(first_question)="{ item }">
            <div class="flex max-w-md items-start gap-2">
                <FontAwesomeIcon :icon="entryPointIcons[item.entry_point] ?? faTerminal" class="mt-1 shrink-0 text-gray-400" fixed-width v-tooltip="item.entry_point_label" :aria-label="item.entry_point_label" />
                <span class="line-clamp-2">{{ item.first_question || ctrans("(no question)") }}</span>
                <FontAwesomeIcon v-if="item.handed_over" :icon="faUserHeadset" class="mt-1 shrink-0 text-green-600" fixed-width v-tooltip="ctrans('Handed over')" :aria-label="ctrans('Handed over')" />
                <span v-if="item.status === 'open'" class="mt-1.5 inline-block size-2 shrink-0 rounded-full bg-green-500" v-tooltip="ctrans('Open')" :aria-label="ctrans('Open')" />
            </div>
        </template>

        <template #cell(messages_count)="{ item }">
            <span class="tabular-nums">{{ item.messages_count }}</span>
        </template>

        <template #cell(shown)="{ item }">
            <span class="inline-block w-8 text-right tabular-nums">{{ item.shown ?? 0 }}</span>
        </template>

        <template #cell(clicked)="{ item }">
            <span class="inline-block w-8 text-right tabular-nums">{{ item.clicked ?? 0 }}</span>
        </template>

        <template #cell(added)="{ item }">
            <span class="inline-block w-8 text-right tabular-nums">{{ item.added ?? 0 }}</span>
        </template>

        <template #cell(ordered)="{ item }">
            <span v-if="item.ordered" class="whitespace-nowrap tabular-nums text-green-700">
                <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width :aria-label="ctrans('Ordered')" />
                <template v-if="item.revenue > 0">{{ locale.currencyFormat(currency, item.revenue) }}</template>
            </span>
            <span v-else />
        </template>

        <template #cell(cost)="{ item }">
            <span class="whitespace-nowrap tabular-nums text-gray-600">{{ locale.currencyFormatUnitCost(costCurrency, item.cost ?? 0) }}</span>
        </template>
    </Table>
</template>
