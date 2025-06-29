<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 15 Feb 2024 19:17:38 CEST Time, Plane Madrid - Mexico City
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { WebUser } from "@/types/web-user";
import { useFormatTime } from "@/Composables/useFormatTime";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { RouteParams } from "@/types/route-params";

defineProps<{
    data: object
}>();


function webUserRoute(webUser: WebUser) {
    console.log(route().current());
    switch (route().current()) {
        case "grp.org.fulfilments.show.crm.customers.show.web_users.index":
            return route(
                "grp.org.fulfilments.show.crm.customers.show.web_users.show",

                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).fulfilment,
                    (route().params as RouteParams).fulfilmentCustomer,
                    webUser.slug]
            );
        case "grp.org.fulfilments.show.web.web-users.index":
            return route(
                "grp.org.fulfilments.show.web.web-users.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).fulfilment,

                    webUser.slug]);

        case "grp.org.shops.show.crm.customers.show.web_users.index":
            return route(
                "grp.org.shops.show.crm.customers.show.web_users.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).customer,

                    webUser.slug]
            );
        case "grp.org.shops.show.crm.web_users.index":
            return route(
                "grp.org.shops.show.crm.web_users.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    webUser.slug
                ]
            );
    }
}

function webUserEditRoute(webUser: WebUser) {
    console.log(route().current());
    switch (route().current()) {
        case "grp.org.fulfilments.show.crm.customers.show.web_users.index":
            return route(
                "grp.org.fulfilments.show.crm.customers.show.web_users.edit",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).fulfilment,
                    (route().params as RouteParams).fulfilmentCustomer,
                    webUser.slug]
            );
        case "grp.org.shops.show.crm.customers.show.web_users.index":
            return route(
                "grp.org.shops.show.crm.customers.show.web_users.edit",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).customer,
                    webUser.slug]
            );
    }
}

</script>

<template>

    <Table :resource="data"  class="mt-5">
        <template #cell(username)="{ item: webUser }">
            <Link :href="webUserRoute(webUser) as string" class="primaryLink">
                {{ webUser["username"] }}
            </Link>
        </template>

        <template #cell(is_root)="{ item: webUser }">
            {{ webUser["is_root"] }}
        </template>

        <!-- Column: Created at -->
        <template #cell(created_at)="{ item: webUser }">
            {{ useFormatTime(webUser.created_at) }}
        </template>
        <template #cell(action)="{ item: webUser }">
            <Link :href="webUserEditRoute(webUser) as string">
                <Button :style="'edit'" size="xs" v-tooltip="'Edit'" />
            </Link>
        </template>
    </Table>


</template>


