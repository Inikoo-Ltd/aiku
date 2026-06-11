<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 9 September 2024 16:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
-->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import type { Table as TableTS } from "@/types/Table"
import { RouteParams } from "@/types/route-params"
import { inject, ref } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faAbacus } from "@fad"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue";
import { faSkull } from "@fal"
import { notify } from "@kyvg/vue3-notification"

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

const terminateOfferLoading = ref<Record<number, boolean>>({})

const getFirstErrorMessage = (errors: Record<string, unknown> = {}) => {
    const firstError = Object.values(errors)[0]
    if (Array.isArray(firstError) && firstError.length > 0) return String(firstError[0])
    if (typeof firstError === "string") return firstError
    return trans("Failed to terminate offer")
}

const terminateOffer = (item: { id: number, code?: string, name?: string }) => {
    if (terminateOfferLoading.value[item.id]) return

    router.post(route("grp.models.offer.finish", { offer: item.id }), {}, {
        preserveScroll: true,
        onStart: () => {
            terminateOfferLoading.value[item.id] = true
        },
        onSuccess: () => {
            notify({
                title: trans("Success"),
                text: trans("Offer :offer has been terminated", {
                    offer: item.code || item.name || `#${item.id}`
                }),
                type: "success"
            })
        },
        onError: (errors) => {
            notify({
                title: trans("Something went wrong"),
                text: getFirstErrorMessage(errors),
                type: "error"
            })
        },
        onFinish: () => {
            delete terminateOfferLoading.value[item.id]
        }
    })
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(name)="{ item: offer }">
            <div v-if="['Voucher Any Order', 'Voucher Amount Ordered'].includes(offer.type)">
                <Link :href="offerRoute(offer)" class="primaryLink">
                    {{ offer.code }}
                </Link>
                <div class="text-xs opacity-60 italic">
                    {{ offer.name }}
                </div>
            </div>
            <Link v-else :href="offerRoute(offer)" class="primaryLink">
                {{ offer.name }}
            </Link>
        </template>

        <template #cell(type_icon)="{ item: offer }">
            <Icon :data="offer.type_icon" />
        </template>

        <template #cell(duration)="{ item: offer }">
            <div v-if="offer.duration == 'interval'" class="grid text-left">
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

        <template #cell(actions)="{ item }">
            <Button
                v-if="['in_process', 'active'].includes(item.state_value)"
                v-tooltip="ctrans('Terminate offer immediately')"
                :type="'negative'"
                :loading="Boolean(terminateOfferLoading[item.id])"
                @click="terminateOffer(item)"
                icon="fal fa-skull"
            />
        </template>
    </Table>
</template>
