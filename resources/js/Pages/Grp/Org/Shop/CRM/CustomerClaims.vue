<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBoxFragile, faHourglassHalf, faCheckCircle, faTimesCircle, faHatWizard, faHeadset } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faBoxFragile)

defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {}
}>()

const locale = useLocaleStore()

const stateIcon: Record<string, { icon: any, class: string }> = {
    in_review: { icon: faHourglassHalf, class: "text-amber-500" },
    settled: { icon: faCheckCircle, class: "text-green-600" },
    rejected: { icon: faTimesCircle, class: "text-red-500" },
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Table :resource="data" class="mt-5">
        <template #cell(state)="{ item }">
            <FontAwesomeIcon v-tooltip="item.state_label" :icon="stateIcon[item.state]?.icon" :class="stateIcon[item.state]?.class" fixed-width aria-hidden="true" />
        </template>
        <template #cell(reference)="{ item }">
            <Link :href="route('grp.org.shops.show.claims.show', { organisation: route().params.organisation, shop: route().params.shop, customerClaim: item.ulid })" class="primaryLink whitespace-nowrap">
                {{ item.reference }}
            </Link>
            <FontAwesomeIcon v-if="item.source === 'merlin'" v-tooltip="ctrans('Made in Merlin')" :icon="faHatWizard" class="ml-1 text-gray-400" fixed-width aria-hidden="true" />
            <FontAwesomeIcon v-else-if="item.source === 'staff'" v-tooltip="ctrans('Recorded by staff')" :icon="faHeadset" class="ml-1 text-gray-400" fixed-width aria-hidden="true" />
        </template>
        <template #cell(customer_name)="{ item }">
            <Link :href="route('grp.org.shops.show.crm.customers.show', { organisation: route().params.organisation, shop: route().params.shop, customer: item.customer_slug })" class="secondaryLink">
                {{ item.customer_name }}
            </Link>
        </template>
        <template #cell(order_reference)="{ item }">
            <Link v-if="item.order_slug" :href="route('grp.org.shops.show.ordering.orders.show', { organisation: route().params.organisation, shop: route().params.shop, order: item.order_slug })" class="secondaryLink whitespace-nowrap">
                {{ item.order_reference }}
            </Link>
        </template>
        <template #cell(amount)="{ item }">
            <span v-if="item.amount !== null && item.currency_code">{{ locale.currencyFormat(item.currency_code, item.amount) }}</span>
        </template>
    </Table>

</template>
