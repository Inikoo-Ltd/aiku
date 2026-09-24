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
import { trans } from 'laravel-vue-i18n'
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue";
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

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
            <FontAwesomeIcon v-if="webUser.is_root?.icon" :icon="webUser.is_root.icon" v-tooltip="webUser.is_root.tooltip" fixed-width aria-hidden="true" />
        </template>

        <template #cell(status)="{ item: webUser }">
            <FontAwesomeIcon :icon="webUser.status.icon" :class="webUser.status.class" v-tooltip="webUser.status.tooltip" fixed-width aria-hidden="true" />
        </template>

        <template #cell(last_login_at)="{ item: webUser }">
            {{ webUser.last_login_at ? useFormatTime(webUser.last_login_at) : trans('never') }}
        </template>

        <template #cell(number_failed_logins)="{ item: webUser }">
            {{ webUser.number_failed_logins }}
            <span v-if="webUser.last_failed_login_at" class="text-gray-400 text-xs">({{ useFormatTime(webUser.last_failed_login_at) }})</span>
        </template>

        <!-- Column: Created at -->
        <template #cell(created_at)="{ item: webUser }">
            {{ useFormatTime(webUser.created_at) }}
        </template>
        <template #cell(action)="{ item: webUser }">
            <div class='flex items-center'>
                <Link :href="webUserEditRoute(webUser) as string">
                    <Button :style="'edit'" size="xs" v-tooltip="'Edit'" />
                </Link>
                <ModalConfirmationDelete
                        v-if="webUser.delete_route"
                        :routeDelete="webUser.delete_route"
                        :title="trans('Delete this login?')"
                        :description="trans('The customer will no longer be able to log in with it. This can not be undone. Their orders are not affected.')"
                        isFullLoading
                    >
                        <template #default="{ isOpenModal, changeModel }">
                            <Button
                                icon="fal fa-trash-alt"
                                type="negative"
                                @click="changeModel"
                                size="xs"
                            />
                        </template>
                    </ModalConfirmationDelete>
            </div>
        </template>
    </Table>


</template>


