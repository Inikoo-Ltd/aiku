<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 May 2024 18:46:51 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faPlus } from "@fas";
import TagPallet from "@/Components/TagPallet.vue";
import Icon from "@/Components/Icon.vue";
import { inject } from "vue";
import { useFormatTime } from "@/Composables/useFormatTime";
import { RouteParams } from "@/types/route-params";
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"

library.add(faPlus);

defineProps<{
  data: object,
  currency: {
    code: string
    symbol: string
    name: string
  }
}>();

const locale = inject("locale", null);

function orderRoute(order) {
  console.log(route().current());
  switch (route().current()) {
    case "retina.dropshipping.orders.index":
      return route(
        "retina.dropshipping.orders.show",
        {
          order: order.slug
        });
    case "retina.dropshipping.customer_sales_channels.orders.index":
      return route(
        "retina.dropshipping.customer_sales_channels.orders.show",
        {
          customerSalesChannel: (route().params as RouteParams).customerSalesChannel,
          order: order.slug
        });
    case "retina.dropshipping.customer_sales_channels.basket.index":
      return route(
        "retina.dropshipping.customer_sales_channels.basket.show",
        {
          customerSalesChannel: (route().params as RouteParams).customerSalesChannel,
          order: order.slug
        });

  }
}

function clientRoute(order) {
    return route(
        "retina.dropshipping.customer_sales_channels.client.show",
        {
            customerSalesChannel: (route().params as RouteParams).customerSalesChannel,
            customerClient: order.client_ulid
        });
}
</script>

<template>
  <div>
    <Table :resource="data" class="mt-5">

      <!-- Column: Reference -->
      <template #cell(reference)="{ item }">
        <Link :href="(orderRoute(item) as string)" class="primaryLink">
          {{ item["reference"] }}
        </Link>
      </template>

      <template #cell(client_name)="{ item }">
        <Link :href="(clientRoute(item) as string)" class="secondaryLink">
          {{ item["client_name"] }}
        </Link>
      </template>


      <template #cell(shopify_order_id)="{ item: palletReturn }">
        <div class="tabular-nums">
          {{ palletReturn.shopify_order_id }}
        </div>
      </template>

      <template #cell(tiktok_order_id)="{ item: palletReturn }">
        <div class="tabular-nums">
          {{ palletReturn.tiktok_order_id }}
        </div>
      </template>

      <template #cell(shopify_fulfilment_id)="{ item: palletReturn }">
        <div class="tabular-nums">
          {{ palletReturn.shopify_fulfilment_id }}
        </div>
      </template>

      <!-- Column: State -->
      <template #cell(state)="{ item: order }">
        <Icon :data="order['type_icon']" class="px-1" />
        <TagPallet :stateIcon="order.state_icon" />
      </template>


      <template #cell(total_amount)="{ item }">
        <!-- {{ currency?.code }} -->
        {{ locale?.currencyFormat(currency?.code, item.total_amount || 0) }}
      </template>

      <template #cell(date)="{ item: order }">
        {{ useFormatTime(order.date) }}
      </template>

      <template #cell(actions)="{ item: order }">
        <ModalConfirmationDelete
            v-if="order.delete_route"
            :routeDelete="order.delete_route"
            :title="trans('Are you sure you want to delete this order?')"
            isFullLoading
        >
            <template #default="{ isOpenModal, changeModel }">
                <div class="w-fit mx-auto">
                  <Button
                      v-tooltip="trans('Delete basket')"
                      @click="() => changeModel()"
                      type="negative"
                      icon="fal fa-trash-alt"
                      :label="trans('Delete')"
                      size="s"
                      :key="1"
                  />
                </div>
            </template>
        </ModalConfirmationDelete>
      </template>

    </Table>
  </div>
</template>
