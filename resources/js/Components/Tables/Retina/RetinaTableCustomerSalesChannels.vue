<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 May 2025 09:42:22 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import { CustomerSalesChannel } from "@/types/customer-sales-channel"
import { trans } from "laravel-vue-i18n"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSyncAlt } from "@fortawesome/free-solid-svg-icons"
import { library } from "@fortawesome/fontawesome-svg-core"
import axios from "axios"
import { notify } from '@kyvg/vue3-notification'
library.add(faSyncAlt)

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


async function checkCustomerSalesChannel(customerSalesChannel: CustomerSalesChannel) {
    await axios.post(route('retina.dropshipping.platform.wc.check_status', [customerSalesChannel.slug]))
        .then((response) => {
            if (response.data) { 
                notify({
                    type: 'success',
                    title: 'Success',
                    text: 'WooCommerce Website is live',
                })
            } else {
                notify({
                    type: 'error',
                    title: 'Error',
                    text: 'WooCommerce Website is down',
                })
            }
        })
        .catch((exception) => {
            console.log(exception);
            notify({
                type: 'error',
                title: 'Error',
                text: 'Failed to check WooCommerce Website status',
            })
        })

    router.reload();
}

</script>
<template>
    <Table :resource="data">
        <template #cell(platform_name)="{ item: customerSalesChannel }">
            <div class="flex items-center gap-2 w-7">
                <img v-tooltip="customerSalesChannel.platform_name" :src="customerSalesChannel.platform_image" :alt="customerSalesChannel.platform_name"
                     class="w-6 h-6" />
            </div>
        </template>

        <template #cell(name)="{ item: customerSalesChannel }">
            <Link :href="(platformRoute(customerSalesChannel) as string)" class="primaryLink">
                {{ customerSalesChannel["name"] }}
            </Link>
        </template>

        <template #cell(number_portfolios)="{ item: customerSalesChannel }">
            <Link :href="(portfoliosRoute(customerSalesChannel) as string)" class="secondaryLink">
                {{ customerSalesChannel["number_portfolios"] }}
            </Link>
        </template>
        <template #cell(number_customer_clients)="{ item: customerSalesChannel }">
            <Link v-if="customerSalesChannel['platform_code'] == 'manual'" :href="(clientsRoute(customerSalesChannel) as string)" class="secondaryLink">
                {{ customerSalesChannel["number_customer_clients"] }}
            </Link>
        </template>
        <template #cell(number_orders)="{ item: customerSalesChannel }">
            <Link :href="(ordersRoute(customerSalesChannel) as string)" class="secondaryLink">
                {{ customerSalesChannel["number_orders"] }}
            </Link>
        </template>


        <template #cell(action)="{ item: customerSalesChannel, proxyItem }">
            <div class="flex items-center gap-2">
                <ModalConfirmationDelete
                    :routeDelete="customerSalesChannel.delete_route"
                    :title="trans('Are you sure you want to close this channel?')"
                    :description="customerSalesChannel.delete_msg"
                    isFullLoading
                    :noLabel="trans('Close')"
                    :noIcon="'fal fa-store-alt-slash'"
                >
                    <template #beforeTitle>
                        <div class="text-center font-semibold text-xl mb-4">
                            {{ `${customerSalesChannel.platform_name} (${customerSalesChannel.reference})` }}
                        </div>
                    </template>

                    <template #default="{ isOpenModal, changeModel }">
                        <Button
                            v-tooltip="trans('Close channel')"
                            @click="() => changeModel()"
                            type="negative"
                            icon="fal fa-store-alt-slash"
                            size="s"
                            :key="1"
                        />
                    </template>
                </ModalConfirmationDelete>

                <Button
                    v-if="customerSalesChannel.platform_code === 'woocommerce'"
                    v-tooltip="trans('Check WooCommerce Website status')"
                    @click="checkCustomerSalesChannel(customerSalesChannel)"
                    type="secondary"
                    size="s"
                    class="hover:bg-gray-100 ring-1 ring-gray-200"
                    :key="0"
                >
                    <FontAwesomeIcon icon="sync-alt" />
                </Button>

                <span class="text-red-500" v-if="customerSalesChannel.is_down && customerSalesChannel.platform_code === 'woocommerce'" v-tooltip="trans('The selected WooCommerce Website is down')">
                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" />
                </span>
            </div>
        </template>
    </Table>
</template>
