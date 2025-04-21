<script setup lang="ts">

import Table from "@/Components/Table/Table.vue";
import { inject } from "vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { Link } from "@inertiajs/vue3";
import { InvoiceTransaction } from "@/types/invoice-transaction";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faStream } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { trans } from "laravel-vue-i18n";

library.add(
  faStream
);

defineProps<{
  data: object
  tab: string
}>();

const locale = inject("locale", aikuLocaleStructure);


function assetRedirectRoute(transaction: InvoiceTransaction) {
  console.log(route().current());
  if(route().current()=='retina.fulfilment.billing.invoices.show'){
    return ''
  }else{
    return route(
      "grp.helpers.redirect_asset",
      [transaction.asset_id]);
  }


}


</script>

<template>
  <div class="h-min">
    <Table :resource="data" :name="tab">
      <template #cell(code)="{ item: transaction }">
        <template v-if="assetRedirectRoute(transaction)">
          <Link :href="assetRedirectRoute(transaction)" class="primaryLink">
            {{ transaction["code"] }}
          </Link>
        </template>
        <span v-else>{{ transaction["code"] }}</span>


      </template>
      <template #cell(net_amount)="{ item }">
        <div :class="item.net_amount < 0 ? 'text-red-500' : ''">
          {{ locale.currencyFormat(item.currency_code, item.net_amount) }}
        </div>
      </template>
      <template #cell(description)="{ item: transaction }">


        <span>{{ transaction.description }}</span>
        <span v-if="transaction.number_grouped_transactions" class="px-3">
             <FontAwesomeIcon icon="fal fa-stream" /> {{ transaction.number_grouped_transactions }}
        </span>
        <span v-if="transaction.fulfilment_info" class="pl-2">
            <span v-if="transaction.fulfilment_info.servicePalletInfo">
              <span class="px-2">{{ trans("Pallet") }}:
                <Link :href="route(transaction.fulfilment_info.servicePalletInfo.palletRoute?.name, transaction.fulfilment_info.servicePalletInfo.palletRoute?.parameters) as unknown as string" class="primaryLink">
                  {{ transaction.fulfilment_info.servicePalletInfo.palletReference }}
                </Link>
              </span>
              <span v-if="transaction.fulfilment_info.servicePalletInfo.handling_date" class="text-gray-400 text-xs">
                {{ trans("Date") }}: {{ transaction.fulfilment_info.servicePalletInfo.handling_date }}
              </span>

            </span>
            <span v-if="transaction.fulfilment_info.rentedScopeInfo">
              <span v-if="transaction.fulfilment_info.rentedScopeInfo.model">{{ transaction.fulfilment_info.rentedScopeInfo.model }}:</span>
					<Link
            v-if="transaction.fulfilment_info.rentedScopeInfo.title && transaction.fulfilment_info.rentedScopeInfo.route?.name"
            :href="route(transaction.fulfilment_info.rentedScopeInfo.route?.name, transaction.fulfilment_info.rentedScopeInfo.route?.parameters) as unknown as string"
            class="primaryLink">
						{{ transaction.fulfilment_info.rentedScopeInfo.title }}
					</Link>
					<span v-else>&nbsp;{{ transaction.fulfilment_info.rentedScopeInfo.title }}</span>

					<span v-if="transaction.fulfilment_info.rentedScopeInfo.after_title" class="text-gray-400">
						({{ transaction.fulfilment_info.rentedScopeInfo.after_title }})
					</span>
				</span>

        </span>


      </template>
    </Table>
  </div>
</template>
