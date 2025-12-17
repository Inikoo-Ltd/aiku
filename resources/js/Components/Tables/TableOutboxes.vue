<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Link} from '@inertiajs/vue3';
import Table from '@/Components/Table/Table.vue';
import {Outbox} from "@/types/outbox";
import Icon from '@/Components/Icon.vue';
import {
  faBell,
  faBells,
  faNewspaper,
  faBullhorn,
  faRadio,
  faThermometerEmpty,
  faVial,
  faPhoneVolume,
  faSortAlt,
  faProjectDiagram,
} from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { trans } from 'laravel-vue-i18n'

library.add(
  faBell,
  faBells,
  faNewspaper,
  faBullhorn,
  faRadio,
  faThermometerEmpty,
  faVial,
  faPhoneVolume,
  faSortAlt,
  faProjectDiagram
);

const props = defineProps<{
    data: object,
    tab?: string
}>()


function outboxRoute(outbox: Outbox) {
    switch (route().current()) {
        case 'grp.overview.comms-marketing.outboxes.index':
            return route(
                'grp.org.shops.show.dashboard.comms.outboxes.show',
                [outbox.organisation_slug, outbox.shop_slug, outbox.slug])
        case 'grp.org.shops.show.dashboard.comms.outboxes.index':
        case 'grp.org.shops.show.dashboard.comms.cold_email_outboxes.index':
        case 'grp.org.shops.show.dashboard.comms.customer_notification_outboxes.index':
        case 'grp.org.shops.show.dashboard.comms.marketing_notification_outboxes.index':
        case 'grp.org.shops.show.dashboard.comms.marketing_outboxes.index':
        case 'grp.org.shops.show.dashboard.comms.newsletter_outboxes.index':
        case 'grp.org.shops.show.dashboard.comms.push_outboxes.index':
        case 'grp.org.shops.show.dashboard.comms.test_outboxes.index':
        case 'grp.org.shops.show.dashboard.comms.user_notification_outboxes.index':
        return route(
                'grp.org.shops.show.dashboard.comms.outboxes.show',
                [route().params['organisation'], route().params['shop'], outbox.slug])
        case 'grp.org.shops.show.web.websites.outboxes':
        return route(
                'grp.org.shops.show.web.websites.outboxes.show',
                [route().params['organisation'], route().params['shop'], route().params['website'], outbox.slug])
        case 'grp.org.fulfilments.show.operations.comms.outboxes':
        return route(
                'grp.org.fulfilments.show.operations.comms.outboxes.show',
                [route().params['organisation'], route().params['fulfilment'], outbox.slug])
        default:
            return ''
    }
}

function getOutboxNameDisplay(outbox: Outbox) {
    const isReorderReminder = outbox.sub_type === 'reorder_reminder' && outbox.days_after !== null;

    return {
        name: outbox.name,
        showSchedule: isReorderReminder,
        scheduleText: isReorderReminder ? `${outbox.days_after} days` : '',
        scheduleTooltip: trans(`Sent after ${outbox.days_after} days from last invoice`)
    };
}

</script>

<template>
    <Table :resource="data" :name="tab">
        <template #cell(name)="{ item: outbox }">
            <Link v-if="outboxRoute(outbox)" :href="outboxRoute(outbox)" class="primaryLink">
                {{ outbox["name"] }}
            </Link>
        </template>
        <template #cell(type)="{ item: outbox }">
            <Icon :data="outbox.type" />
        </template>
    </Table>
</template>


