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
import Icon from "@/Components/Icon.vue"
import type { Table as TableTS } from "@/types/Table"
import { RouteParams } from "@/types/route-params"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faAbacus } from "@fad"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import Offer from "@/Pages/Grp/Org/Discounts/Offer.vue"

const locale = inject("locale", aikuLocaleStructure)

const props = defineProps<{
    data: TableTS
    tab?: string
    offerCampaign?: {}
}>()

library.add(
    faAbacus
)

function offerRoute(offer: {}) {
    switch (route().current()) {                
        case "grp.org.shops.show.catalogue.departments.show":
            return route(
                "grp.org.shops.show.catalogue.departments.show.offers.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).department,
                    offer.slug])
        case "grp.org.shops.show.catalogue.departments.show.sub_departments.show":
        case "grp.org.shops.show.catalogue.sub_departments.show":
            return route(
                "grp.org.shops.show.catalogue.sub_departments.show.offers.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).subDepartment,
                    offer.slug])
        case "grp.org.shops.show.catalogue.departments.show.families.show":
        case "grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show":
        case "grp.org.shops.show.catalogue.sub_departments.show.families.show":
        case "grp.org.shops.show.catalogue.families.show":
            return route(
                "grp.org.shops.show.catalogue.families.show.offers.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).family,
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
        case "grp.org.shops.show.discounts.offers.index":
            return route(
                "grp.org.shops.show.discounts.offers.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    offer.slug])
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
            <!-- {{ route().current() }}
            {{ offerRoute(offer) }} -->
            <Link :href="offerRoute(offer)" class="primaryLink">
                {{ offer.name }}
            </Link>
        </template>

        <template #cell(type_icon)="{ item: offer }">
            <Icon :data="offer.type_icon" />
        </template>

        <template #cell(duration)="{ item: offer }">
            <div v-if="offer.duration == 'interval'" class="grid">
                <div>
                    <span class="font-medium">
                        {{ trans("Start Date") }}
                    </span>:  {{ useFormatTime(offer.start_at, { localeCode: locale.language.code, formatTime: "aiku" }) }}
                </div>
                <div>
                    <span class="font-medium">
                        {{ trans("End Date") }}
                    </span>:  {{ useFormatTime(offer.end_at, { localeCode: locale.language.code, formatTime: "aiku" }) }} 
                </div>
            </div>
            <div v-else>
                {{ useFormatTime(offer.start_at, { localeCode: locale.language.code, formatTime: "aiku" }) }} 
            </div>
        </template>

        <template #cell(sales_grp_currency_external)="{ item: collection }">
            <span class="tabular-nums">{{ locale.currencyFormat('GBP', collection.sales_grp_currency_external) }}</span>
        </template>
    </Table>
</template>
