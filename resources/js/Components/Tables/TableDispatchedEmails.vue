<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { DispatchedEmailResource } from "@/types/dispatched-email"
import {
    faCheck,
    faDumpster,
    faEnvelopeOpen,
    faExclamationCircle,
    faExclamationTriangle,
    faHandPaper,
    faInboxIn,
    faMousePointer,
    faPaperPlane,
    faSpellCheck,
    faSquare,
    faTimesCircle,
    faVirus
} from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import Icon from "../Icon.vue";
import { inject } from "vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { useFormatTime } from "@/Composables/useFormatTime";
import { RouteParams } from "@/types/route-params";

library.add(
    faSpellCheck,
    faPaperPlane,
    faExclamationCircle,
    faVirus,
    faInboxIn,
    faMousePointer,
    faExclamationTriangle,
    faSquare,
    faEnvelopeOpen,
    faMousePointer,
    faDumpster,
    faHandPaper,
    faCheck,
    faTimesCircle
);
defineProps<{
    data: object,
    tab?: string
}>();


function dispatchedEmailRoute(dispatchedEmail: DispatchedEmailResource) {
    switch (route().current()) {
        case "grp.org.fulfilments.show.operations.comms.outboxes.show":
            return route(
                "grp.org.fulfilments.show.operations.comms.outboxes.dispatched-email.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).fulfilment,
                    (route().params as RouteParams).outbox,

                    dispatchedEmail.id]);
        case "grp.org.shops.show.dashboard.comms.outboxes.show":
            return route(
                "grp.org.shops.show.dashboard.comms.outboxes.dispatched-email.show",
                [
                    (route().params as RouteParams).organisation,
                    dispatchedEmail.shop_slug,
                    (route().params as RouteParams).outbox,
                    dispatchedEmail.id]);
        default:
            return null;
    }
}


function dispatchedEmailCustomerRoute(dispatchedEmail: DispatchedEmailResource) {

    switch (route().current()) {
        case "grp.org.fulfilments.show.operations.comms.outboxes.show":
            const fulfilmentParams = (route().params as RouteParams);
            if (!fulfilmentParams.organisation || !fulfilmentParams.fulfilment || !dispatchedEmail.fulfilment_customer_slug) {
                return null;
            }
            return route(
                "grp.org.fulfilments.show.crm.customers.show",
                [
                    fulfilmentParams.organisation,
                    fulfilmentParams.fulfilment,
                    dispatchedEmail.fulfilment_customer_slug]);
        case "grp.org.shops.show.dashboard.comms.outboxes.show":
            const shopParams = (route().params as RouteParams);
            if (!shopParams.organisation || !dispatchedEmail.shop_slug || !dispatchedEmail.customer_slug) {
                return null;
            }
            return route(
                "grp.org.shops.show.crm.customers.show",
                [
                    shopParams.organisation,
                    dispatchedEmail.shop_slug,
                    dispatchedEmail.customer_slug]);
        default:
            return null;
    }
}

function dispatchedEmailOrderRoute(dispatchedEmail: DispatchedEmailResource) {

    switch (route().current()) {
        case "grp.org.shops.show.dashboard.comms.outboxes.show":
            const shopParams = (route().params as RouteParams);
            if (!shopParams.organisation || !dispatchedEmail.shop_slug || !dispatchedEmail.order_slug) {
                return null;
            }
            return route(
                "grp.org.shops.show.ordering.orders.show",
                [
                    shopParams.organisation,
                    dispatchedEmail.shop_slug,
                    dispatchedEmail.order_slug]);
        default:
            return null;
    }
}

const locale = inject("locale", aikuLocaleStructure);


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: dispatchedEmail }">
            <Icon v-if="dispatchedEmail.state" :data="dispatchedEmail.state" />
            <Icon v-if="dispatchedEmail.state_icon" :data="dispatchedEmail.state_icon" />
        </template>
        <template #cell(email_address)="{ item: dispatchedEmail }">
            <Link v-if="dispatchedEmailRoute(dispatchedEmail)" :href="dispatchedEmailRoute(dispatchedEmail) as string" class="primaryLink">
                {{ dispatchedEmail["email_address"] }}
            </Link>
            <span v-else>
                {{ dispatchedEmail["email_address"] }}
            </span>
            <Icon :data="dispatchedEmail.mask_as_spam" class="pl-1" />
        </template>

         <template #cell(customer_name)="{ item: dispatchedEmail }">
            <Link v-if="dispatchedEmailCustomerRoute(dispatchedEmail)" :href="dispatchedEmailCustomerRoute(dispatchedEmail) as string" class="primaryLink">
                {{ dispatchedEmail["customer_name"] }}
            </Link>
            <span v-else>
                {{ dispatchedEmail["customer_name"] }}
            </span>
        </template>
        
        <template #cell(order_slug)="{ item: dispatchedEmail }">
            <Link v-if="dispatchedEmailOrderRoute(dispatchedEmail)" :href="dispatchedEmailOrderRoute(dispatchedEmail) as string" class="primaryLink">
                {{ dispatchedEmail["order_slug"] }}
            </Link>
            <span v-else>
                {{ dispatchedEmail["order_slug"] }}
            </span>
        </template>

        <template #cell(sent_at)="{ item: dispatchedEmail }">
            {{ useFormatTime(dispatchedEmail.sent_at, { localeCode: locale.language.code, formatTime: "aiku" }) }}
        </template>

    </Table>
</template>


