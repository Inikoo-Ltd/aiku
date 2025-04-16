<script setup lang="ts">

import Table from "@/Components/Table/Table.vue";
import { inject } from "vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { Link } from "@inertiajs/vue3";
import { InvoiceTransaction } from "@/types/invoice-transaction";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faStream } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
library.add(
  faStream
)

defineProps<{
  data: object
  tab: string
}>();

const locale = inject("locale", aikuLocaleStructure);


function assetRedirectRoute(transaction: InvoiceTransaction) {
  return route(
    "grp.helpers.redirect_asset",
    [transaction.asset_id]);
}


</script>

<template>
  <div class="h-min">
    <Table :resource="data" :name="tab">
      <template #cell(code)="{ item: transaction }" >
        <Link :href="assetRedirectRoute(transaction)" class="primaryLink">
          {{ transaction["code"] }}
        </Link>
      </template>
      <template #cell(net_amount)="{ item }">
        <div :class="item.net_amount < 0 ? 'text-red-500' : ''">
          {{ locale.currencyFormat(item.currency_code, item.net_amount) }}
        </div>
      </template>
      <template #cell(name)="{ item: transaction }">
        <span>{{ transaction.name }}</span>
        <span v-if="transaction.number_grouped_transactions>1" class="px-3" >
             <FontAwesomeIcon icon="fal fa-stream"  /> {{transaction.number_grouped_transactions}}
        </span>


        <span v-if="transaction.pallet && transaction.handling_date">
                    <br>
                    <span class="text-gray-400 text-xs">Pallet:
                        <Link :href="route(transaction.palletRoute?.name, transaction.palletRoute?.parameters) as unknown as string" class="primaryLink">
                                {{ transaction.pallet }}
                        </Link>
                    </span>
                    <br>
                    <span class="text-gray-400 text-xs">Date: {{ transaction.handling_date }}</span>
                </span>
        <span v-else>
                </span>
      </template>
    </Table>
  </div>
</template>
