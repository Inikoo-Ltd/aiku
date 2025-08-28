<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue";
import { Link } from "@inertiajs/vue3"
import { RouteParams } from "@/types/route-params"
import { CreditTransaction } from "@/types/credit-transaction"
import Button from "@/Components/Elements/Buttons/Button.vue";
import RefundModal from "@/Components/RefundModal.vue";
import { faUndo } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { ref, inject } from "vue";

library.add(faUndo)

const props = defineProps<{
    data: object,
    tab?: string
}>();

const layout = inject("layout");

// Modal state
const isRefundModalVisible = ref(false)
const selectedTransaction = ref<CreditTransaction | null>(null)

function paymentRoute(credit_transaction?: CreditTransaction) {

  if(!credit_transaction?.payment_id) return '';

    if(route().current()=='grp.org.shops.show.crm.customers.show'){
        return route(
            "grp.org.shops.show.crm.customers.show.payments.show",
            {
              payment: credit_transaction.payment_id,
              organisation: (route().params as RouteParams).organisation,
              shop: (route().params as RouteParams).shop,
              customer: (route().params as RouteParams).customer
            }
        );
    }

    return '';
}

// Function to open refund modal
function openRefundModal(transaction: CreditTransaction) {
    selectedTransaction.value = transaction
    isRefundModalVisible.value = true
}

// Function to close refund modal
function closeRefundModal() {
    isRefundModalVisible.value = false
    selectedTransaction.value = null
}

// Create showcase object for RefundModal
function createShowcase(transaction: CreditTransaction) {
    return {
        amount: transaction.amount?.toString() || '0',
        state: 'completed', // Assuming completed state for refund eligibility
        currency: {
            data: {
                id: 1, // Default values - adjust based on your data structure
                code: transaction.currency_code || 'USD',
                name: transaction.currency_code || 'USD',
                symbol: '$' // Default symbol - adjust based on your needs
            }
        }
    }
}

console.log(props.data)

// Create refund route for RefundModal
function createRefundRoute(transaction: CreditTransaction) {
    if (!transaction.payment_id) return undefined

    return {
      name: "grp.models.org.payment_refund.store",
        parameters: {
            organisation: (route().params as RouteParams).organisation, // waiting data from BE
            payment: transaction.payment_id
        }
    }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(payment_reference)="{ item: credit_transaction }">
            <Link v-if="credit_transaction?.payment_id" :href="(paymentRoute(credit_transaction) as string)" class="primaryLink">
                {{ credit_transaction.payment_reference }}
            </Link>
            <div v-else>
              {{ credit_transaction.payment_reference }}
            </div>
        </template>
        <template #cell(actions)="{item}">
          <Button 
            v-if="item.payment_id !== null && item.payment_reference !== null" 
            :icon="faUndo" 
            v-tooltip="'Proceed Refund'"
            @click="openRefundModal(item)"
          />
        </template>
    </Table>

    <!-- Refund Modal -->
    <RefundModal
        v-if="selectedTransaction && layout?.app?.environment !== 'production'"
        :showcase="createShowcase(selectedTransaction)"
        :refund-route="createRefundRoute(selectedTransaction)"
        :is-visible="isRefundModalVisible"
        @close="closeRefundModal"
    />
</template>