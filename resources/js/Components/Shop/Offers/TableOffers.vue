<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 9 September 2024 16:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
-->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Order } from "@/types/order"
import type { Table as TableTS } from "@/types/Table"
import { RouteParams } from "@/types/route-params"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

const locale = inject("locale", aikuLocaleStructure)

const props = defineProps<{
    data: TableTS
    tab?: string
    offerCampaign?: {}
}>()

function offerRoute(offer: {}) {
    switch (route().current()) {
        case "grp.org.shops.show.discounts.offers.index":
        case "grp.org.shops.show.catalogue.families.show":
            return route(
                "grp.org.shops.show.discounts.offers.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    offer.slug])
        case "grp.org.shops.show.discounts.campaigns.show":
            return route(
                returnRouteOffer(offer),
                {
                    organisation: (route().params as RouteParams).organisation,
                    shop: (route().params as RouteParams).shop,
                    offerCampaign: props.offerCampaign?.slug ?? offer.offer_campaign_slug,
                    offer: offer.slug
                })
        default:
            return "#"
    }
}

function returnRouteOffer(offer: any) {
    switch (offer.type) {
        case 'VolGr Gift':
            return "grp.org.shops.show.discounts.campaigns.gift.show";
        case 'GR Amnesty':
            return "grp.org.shops.show.discounts.campaigns.amnesty.show";
        case 'Category Quantity Ordered':
            return "grp.org.shops.show.discounts.campaigns.offer.show";
        default:
            return "grp.org.shops.show.discounts.campaigns.offer.show";
    }
}

console.log("Curr Route", route().current())


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(name)="{ item: offer }">
            <Link :href="offerRoute(offer)" class="primaryLink">
                {{ offer.name }}
            </Link>
        </template>

        <template #cell(sales_grp_currency_external)="{ item: collection }">
            <span class="tabular-nums">{{ locale.currencyFormat('GBP', collection.sales_grp_currency_external) }}</span>
        </template>
    </Table>
</template>
