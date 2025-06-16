
<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 May 2025 09:42:22 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from '@/Components/Table/Table.vue'
import type { Table as TableTS } from "@/types/Table"
import { CustomerSalesChannel } from "@/types/customer-sales-channel";
import {trans} from "laravel-vue-i18n";
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue";
import Toggle from "primevue/toggleswitch";
import Button from "@/Components/Elements/Buttons/Button.vue";

defineProps<{
    data: TableTS,
}>()



function customerSalesChannelRoute(customerSalesChannel: CustomerSalesChannel) {
  return route(
    "retina.fulfilment.dropshipping.customer_sales_channels.show",
    [customerSalesChannel.slug])
}

function portfoliosRoute(customerSalesChannel: CustomerSalesChannel) {
  return route(
    "retina.fulfilment.dropshipping.customer_sales_channels.portfolios.index",
    [
      customerSalesChannel.slug])
}
function clientsRoute(customerSalesChannel: CustomerSalesChannel) {
  return route(
    "retina.fulfilment.dropshipping.customer_sales_channels.client.index",
    [
      customerSalesChannel.slug])
}
function ordersRoute(customerSalesChannel: CustomerSalesChannel) {
  return route(
    "retina.fulfilment.dropshipping.customer_sales_channels.orders.index",
    [

      customerSalesChannel.slug])
}

</script>
<template>
      <Table :resource="data" >
        <template #cell(platform_name)="{ item: customerSalesChannel }">
            <div class="flex items-center gap-2">
                <img :src="customerSalesChannel.platform_image" :alt="customerSalesChannel.platform_name" class="w-6 h-6" />
                {{ customerSalesChannel.platform_name }}
            </div>
        </template>
        <template #cell(reference)="{ item: customerSalesChannel }">
            <Link :href="customerSalesChannelRoute(customerSalesChannel) as string" class="primaryLink">
                {{ customerSalesChannel["reference"] }}
            </Link>
        </template>
        <template #cell(number_portfolios)="{ item: customerSalesChannel }">
            <Link :href="portfoliosRoute(customerSalesChannel) as string" class="secondaryLink">
                {{ customerSalesChannel["number_portfolios"] }}
            </Link>
        </template>
        <template #cell(number_clients)="{ item: customerSalesChannel }">
            <Link :href="clientsRoute(customerSalesChannel) as string" class="secondaryLink">
                {{ customerSalesChannel["number_clients"] }}
            </Link>
        </template>
        <template #cell(number_orders)="{ item: customerSalesChannel }">
            <Link :href="ordersRoute(customerSalesChannel) as string" class="secondaryLink">
                {{ customerSalesChannel["number_orders"] }}
            </Link>
        </template>

          <template #cell(status)="{ proxyItem }">
              <Toggle
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
          </template>

          <template #cell(action)="{ item: customerSalesChannel }">
              <!-- <pre>{{ customerSalesChannel.platform_name }} ({{ customerSalesChannel.reference }})</pre> -->
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
