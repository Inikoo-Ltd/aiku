<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { faBox, faHandHoldingBox, faPallet, faPencil, faSeedling } from "@fal"
import { faCheckCircle, faTimesCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { useLocaleStore } from "@/Stores/locale"
import Icon from "@/Components/Icon.vue"

library.add(faBox, faHandHoldingBox, faPallet, faPencil, faSeedling, faCheckCircle, faTimesCircle)

defineProps<{
    data: {}
    tab?: string
}>()

const locale = useLocaleStore()

</script>


<template>
    <!-- {{ props.shopsList }} -->
    <Table :resource="data" :name="tab" class="mt-5">

        <template #cell(state)="{ item: paymentAccountShops }">
            <Icon :data="paymentAccountShops.state_icon" />
        </template>

        <template #cell(show_in_checkout)="{ item: paymentAccountShops }">
            {{paymentAccountShops.show_in_checkout}}

        </template>




        <template #cell(number_payments)="{ item: paymentAccountShops }">
            {{ useLocaleStore().number(paymentAccountShops.number_payments) }}
        </template>
        <template #cell(amount_successfully_paid)="{ item: paymentAccountShop }">
            <div class="text-gray-500">{{ locale.currencyFormat(paymentAccountShop.shop_currency_code, paymentAccountShop.amount_successfully_paid) }}</div>
        </template>
    </Table>
</template>
