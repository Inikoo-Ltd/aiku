<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 May 2025 09:42:22 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Link} from "@inertiajs/vue3"
import Table from '@/Components/Table/Table.vue'
import type {Table as TableTS} from "@/types/Table"
import {CustomerSalesChannel} from "@/types/customer-sales-channel";
import {trans} from "laravel-vue-i18n";
import Toggle from "primevue/toggleswitch"
import axios from "axios"
import { routeType } from "@/types/route"
import { notify } from "@kyvg/vue3-notification"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"

import { faUnlink } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
library.add(faUnlink)

defineProps<{
    data: TableTS,
}>()


function platformRoute(customerSalesChannel: CustomerSalesChannel) {
    return route(
        "retina.dropshipping.customer_sales_channels.show",
        [customerSalesChannel.slug])
}

function portfoliosRoute(customerSalesChannel: CustomerSalesChannel) {
    return route(
        "retina.dropshipping.customer_sales_channels.portfolios.index",
        [customerSalesChannel.slug])
}

function clientsRoute(customerSalesChannel: CustomerSalesChannel) {
    return route(
        "retina.dropshipping.customer_sales_channels.client.index",
        [customerSalesChannel.slug])
}

function ordersRoute(customerSalesChannel: CustomerSalesChannel) {
    return route(
        "retina.dropshipping.customer_sales_channels.orders.index",
        [customerSalesChannel.slug])
}


const onChangeToggle = async (routeUpdate: routeType, proxyItem: {status: string}, oldValue: string, newVal: string) => {
    const data = await axios.patch(
        route(routeUpdate.name, routeUpdate.parameters),
        {
            
        }
    )
    // console.log('oldValue', oldValue, newVal)
    if (data.status === 200) {
        proxyItem.status = newVal
        notify({
            title: trans("Success"),
            text: trans("Successfully update the platform status"),
            type: "success",
        })
    } else {
        proxyItem.status = oldValue
    }
}
</script>
<template>
    <Table :resource="data">
        <template #cell(platform_name)="{ item: customerSalesChannel }">
            <div class="flex items-center gap-2 w-7">
                <img v-tooltip="customerSalesChannel.platform_name" :src="customerSalesChannel.platform_image" :alt="customerSalesChannel.platform_name"
                    class="w-6 h-6"/>
            </div>
        </template>

        <template #cell(name)="{ item: customerSalesChannel }">
            <Link :href="(platformRoute(customerSalesChannel) as string)" class="primaryLink">
                {{ customerSalesChannel["name"] }}
            </Link>

            <!-- Button: Reconnect -->
            <template v-if="customerSalesChannel.platform_code !== 'manual'">
                <FontAwesomeIcon
                    v-if="customerSalesChannel.connection === 'connected'"
                    v-tooltip="trans('Connected')"
                    icon="fal fa-check"
                    class="text-green-500"
                    fixed-width
                    aria-hidden="true"
                />

                <template v-else>
                    <FontAwesomeIcon v-tooltip="trans('Not connected to the platform yet')" icon="far fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                    <ButtonWithLink
                        v-if="customerSalesChannel.reconnect_route?.name"
                        :routeTarget="customerSalesChannel.reconnect_route"
                        icon=""
                        :label="trans('Reconnect')"
                        size="xxs"
                        type="tertiary"
                        class="ml-2"
                    />
                </template>

            </template>
        </template>

        <template #cell(number_portfolios)="{ item: customerSalesChannel }">
            <Link :href="(portfoliosRoute(customerSalesChannel) as string)" class="secondaryLink">
                {{ customerSalesChannel["number_portfolios"] }}
            </Link>
        </template>
        <template #cell(number_clients)="{ item: customerSalesChannel }">
            <Link :href="(clientsRoute(customerSalesChannel) as string)" class="secondaryLink">
                {{ customerSalesChannel["number_clients"] }}
            </Link>
        </template>
        <template #cell(number_orders)="{ item: customerSalesChannel }">
            <Link :href="(ordersRoute(customerSalesChannel) as string)" class="secondaryLink">
                {{ customerSalesChannel["number_orders"] }}
            </Link>
        </template>


        <template #cell(action)="{ item: customerSalesChannel, proxyItem }">
            <!-- <pre>{{ customerSalesChannel.platform_name }} ({{ customerSalesChannel.reference }})</pre> -->
            <Toggle
                v-tooltip="trans('Change platform to open/closed')"
                :routeTarget="proxyItem.toggle_route"
                :modelValue="proxyItem.status"
                @update:modelValue="(newVal: string) => {
                    onChangeToggle(
                        proxyItem.toggle_route,
                        proxyItem,
                        proxyItem.status,
                        newVal
                    )
                }"
                true-value="open"
                false-value="closed"
            />

            <ModalConfirmationDelete
                :routeDelete="customerSalesChannel.unlink_route"
                :title="trans('Are you sure you want to unlink platform') + ` ${customerSalesChannel.platform_name} (${customerSalesChannel.reference})?`"
                isFullLoading
            >
                <template #default="{ isOpenModal, changeModel }">
                    <Button
                        v-tooltip="trans('Unlink') + ' ' + customerSalesChannel.platform_name"
                        @click="() => changeModel()"
                        type="negative"
                        icon="fal fa-unlink"
                        size="s"
                        :key="1"
                    />
                </template>
            </ModalConfirmationDelete>
        </template>
    </Table>
</template>

<style lang="scss">
:root {
    --p-toggleswitch-checked-background: #22c55e;
}
</style>