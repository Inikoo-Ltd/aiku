<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { DispatchedEmail } from "@/types/dispatched-email";
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


function dispatchedEmailRoute(dispatchedEmail: DispatchedEmail) {
    console.log(route().current());
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
                    (route().params as RouteParams).fulfilment,
                    (route().params as RouteParams).outbox,
                    dispatchedEmail.id]);
        default:
            return null;
    }
}

const locale = inject("locale", aikuLocaleStructure);


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: dispatchedEmail }">
            <Icon :data="dispatchedEmail.state" />
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
        <template #cell(sent_at)="{ item: dispatchedEmail }">
            {{ useFormatTime(dispatchedEmail.sent_at, { localeCode: locale.language.code, formatTime: "aiku" }) }}
        </template>

    </Table>
</template>


