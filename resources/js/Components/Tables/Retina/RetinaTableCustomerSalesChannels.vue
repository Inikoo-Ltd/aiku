<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 May 2025 09:42:22 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import { CustomerSalesChannel } from "@/types/customer-sales-channel"
import { ctrans } from "@/Composables/useTrans"
import CloseSalesChannel from "@/Components/Retina/Platform/CloseSalesChannel.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSyncAlt } from "@fortawesome/free-solid-svg-icons"
import { faStoreAltSlash } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import axios from "axios"
library.add(faSyncAlt, faStoreAltSlash)

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


const wooStatus = ref<Record<number, { isLive: boolean, text: string }>>({})

async function checkCustomerSalesChannel(customerSalesChannel: CustomerSalesChannel) {
    await axios.post(route('retina.dropshipping.platform.wc.check_status', [customerSalesChannel.slug]))
        .then((response) => {
            wooStatus.value[customerSalesChannel.id] = response.data
                ? { isLive: true, text: ctrans('WooCommerce Website is live') }
                : { isLive: false, text: ctrans('WooCommerce Website is down') }
        })
        .catch(() => {
            wooStatus.value[customerSalesChannel.id] = { isLive: false, text: ctrans('Failed to check WooCommerce Website status') }
        })

    router.reload();
}

</script>
<template>
    <Table :resource="data">
        <template #cell(platform_name)="{ item: customerSalesChannel }">
            <div class="flex items-center gap-2 w-7">
                <img v-tooltip="customerSalesChannel.platform_name" :src="customerSalesChannel.platform_image" :alt="customerSalesChannel.platform_name"
                     class="w-6 h-6" loading="lazy" decoding="async" />
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
                <CloseSalesChannel :customerSalesChannel="customerSalesChannel">
                    <template #default="{ open }">
                        <Button
                            v-tooltip="ctrans('Close channel')"
                            @click="open"
                            type="tertiary"
                            size="s"
                            :key="1"
                        >
                            <template #icon>
                                <FontAwesomeIcon icon="fal fa-store-alt-slash" class="text-red-500" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </template>
                </CloseSalesChannel>

                <Button
                    v-if="customerSalesChannel.platform_code === 'woocommerce'"
                    v-tooltip="ctrans('Check WooCommerce Website status')"
                    @click="checkCustomerSalesChannel(customerSalesChannel)"
                    type="tertiary"
                    size="s"
                    :key="0"
                >
                    <FontAwesomeIcon icon="sync-alt" fixed-width />
                </Button>

                <span
                    v-if="wooStatus[customerSalesChannel.id]"
                    class="text-xs whitespace-nowrap"
                    :class="wooStatus[customerSalesChannel.id].isLive ? 'text-green-600' : 'text-red-600'"
                >
                    {{ wooStatus[customerSalesChannel.id].text }}
                </span>

                <span class="text-red-500" v-if="customerSalesChannel.is_down && customerSalesChannel.platform_code === 'woocommerce'" v-tooltip="ctrans('The selected WooCommerce Website is down')">
                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width />
                </span>
            </div>
        </template>
    </Table>
</template>
