<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { Mailshot } from "@/types/mailshot";
import icon from "@/Components/Icon.vue";
import { faSpellCheck, faSeedling, faPaperPlane, faStop, faCheckDouble, faCheck, faSkull, faDungeon, faEnvelopeOpen, faHandPointer, faEyeSlash } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { RouteParams } from "@/types/route-params";

library.add(faSpellCheck, faSeedling, faPaperPlane, faStop, faCheckDouble, faCheck, faSkull, faDungeon, faEnvelopeOpen, faHandPointer, faEyeSlash);

defineProps<{
    data: object,
    tab?: string
}>();


function mailshotRoute(mailshot: Mailshot) {
    console.log(route().current())
    switch (route().current()) {
        case "grp.org.shops.show.marketing.mailshots.index":
            return route(
                "grp.org.shops.show.marketing.mailshots.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    mailshot.slug]);
        case "grp.org.shops.show.marketing.newsletters.index":
            return route(
                "grp.org.shops.show.marketing.newsletters.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    mailshot.slug]);
        case "grp.org.shops.show.web.websites.outboxes.show":
            return route(
                "grp.org.shops.show.web.websites.outboxes.mailshots.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).website,
                    (route().params as RouteParams).outbox,
                    mailshot.slug]);
        default:
            return null;
    }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(subject)="{ item: mailshot }">
            <Link :href="mailshotRoute(mailshot) as string" class="primaryLink">
                {{ mailshot["subject"] }}
            </Link>
        </template>
        <template #cell(state)="{ item: mailshot }">
            <div class="flex justify-center">
                <icon :data="mailshot.state_icon" />
            </div>
        </template>
    </Table>
</template>


