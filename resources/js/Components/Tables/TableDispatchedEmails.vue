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
    faVirus,
    faEyeEvil
} from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import Icon from "../Icon.vue";
import { inject, ref } from "vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { useFormatTime } from "@/Composables/useFormatTime";
import { RouteParams } from "@/types/route-params";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import Modal from "@/Components/Utils/Modal.vue";

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
    faTimesCircle,
    faEyeEvil
);
defineProps<{
    data: object,
    tab?: string
}>();

const showEmailPreview = ref(false);

function dispatchedEmailRoute(dispatchedEmail: DispatchedEmailResource) {
    switch (route().current()) {
        case "grp.org.fulfilments.show.operations.comms.outboxes.show":
            const fulfilmentParams = (route().params as RouteParams);
            if (!fulfilmentParams.organisation || !fulfilmentParams.fulfilment || !fulfilmentParams.outbox || !dispatchedEmail.id) {
                return null;
            }
            return route(
                "grp.org.fulfilments.show.operations.comms.outboxes.dispatched-email.show",
                [
                    fulfilmentParams.organisation,
                    fulfilmentParams.fulfilment,
                    fulfilmentParams.outbox,

                    dispatchedEmail.id]);
        case "grp.org.shops.show.dashboard.comms.outboxes.show":
            const outboxParam = (route().params as RouteParams);
            if (!outboxParam.organisation || !dispatchedEmail.shop_slug || !dispatchedEmail.id || !outboxParam.outbox) {
                return null;
            }
            return route(
                "grp.org.shops.show.dashboard.comms.outboxes.dispatched-email.show",
                [
                    outboxParam.organisation,
                    dispatchedEmail.shop_slug,
                    outboxParam.outbox,
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
    <div>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: dispatchedEmail }">
            <Icon v-if="dispatchedEmail.state" :data="dispatchedEmail.state" />
            <Icon v-if="dispatchedEmail.state_icon" :data="dispatchedEmail.state_icon" />
        </template>
        <template #cell(email_address)="{ item: dispatchedEmail }">
            <Link v-if="dispatchedEmailRoute(dispatchedEmail)" :href="dispatchedEmailRoute(dispatchedEmail) as string"
                class="primaryLink">
                {{ dispatchedEmail["email_address"] }}
            </Link>
            <span v-else>
                {{ dispatchedEmail["email_address"] }}
            </span>
            <Icon :data="dispatchedEmail.mask_as_spam" class="pl-1" />
            <span v-if="dispatchedEmail.has_email_preview" @click="() => { dispatchedEmailRoute(dispatchedEmail); }"
                  class="ml-2 inline-flex items-center px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-600 rounded hover:bg-slate-200 hover:text-slate-800 cursor-pointer transition">
                  <FontAwesomeIcon :icon="faEyeEvil" class="mr-1" />
                  {{ trans("Preview") }}
            </span>

        </template>

        <template #cell(customer_name)="{ item: dispatchedEmail }">
            <Link v-if="dispatchedEmailCustomerRoute(dispatchedEmail)"
                :href="dispatchedEmailCustomerRoute(dispatchedEmail) as string" class="primaryLink">
                {{ dispatchedEmail["customer_name"] }}
            </Link>
            <span v-else>
                {{ dispatchedEmail["customer_name"] }}
            </span>
        </template>

        <template #cell(order_slug)="{ item: dispatchedEmail }">
            <Link v-if="dispatchedEmailOrderRoute(dispatchedEmail)"
                :href="dispatchedEmailOrderRoute(dispatchedEmail) as string" class="primaryLink">
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

       <!-- Email Preview Modal -->
    <Modal :show="showEmailPreview" @close="showEmailPreview = false" width="w-auto max-w-4xl px-4">
      <div class="p-4">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Email Preview</h3>
        <div v-if="selectedEmail" class="space-y-4">
          <div>
            <p class="text-sm text-gray-500">To: {{ selectedEmail?.email_address }}</p>
            <p class="text-sm text-gray-500">Sent: {{ formatDate(selectedEmail?.sent_at) }}</p>
          </div>
          <div class="border-t border-gray-200 pt-4">
            <div>Hello Test</div>
            <!-- <div class="bg-gray-50 p-4 rounded" v-html="selectedEmail?.body_preview"></div> -->
          </div>
        </div>
      </div>
    </Modal>
    </div>
</template>
