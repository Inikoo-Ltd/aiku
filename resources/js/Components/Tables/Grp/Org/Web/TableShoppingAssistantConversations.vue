<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck } from "@fas"

library.add(faCheck)

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
            <Link v-if="customerHref(item.customer_route)" :href="customerHref(item.customer_route) ?? ''" class="primaryLink">{{ item.customer_name }}</Link>
            <span v-else-if="item.customer_name">{{ item.customer_name }}</span>
            <span v-else class="text-gray-400">{{ ctrans("Guest") }}</span>
            <div v-if="item.customer_reference" class="whitespace-nowrap text-xs text-gray-500">{{ item.customer_reference }}</div>
        </template>

        <template #cell(website)="{ item }">
            <span class="whitespace-nowrap text-gray-600">{{ item.website }}</span>
        </template>

        <template #cell(entry_point)="{ item }">
            <span class="whitespace-nowrap">{{ item.entry_point_label ?? item.entry_point }}</span>
        </template>

        <template #cell(first_question)="{ item }">
            <Link :href="conversationHref(item.ulid)" class="primaryLink line-clamp-2 max-w-md" :title="item.first_question">
                {{ item.first_question || ctrans("(no question)") }}
            </Link>
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
