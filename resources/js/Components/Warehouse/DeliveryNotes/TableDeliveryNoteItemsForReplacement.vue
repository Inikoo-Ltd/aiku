<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 10 Sept 2025 15:09:19 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import type { Table as TableTS } from "@/types/Table";
import { trans } from "laravel-vue-i18n";
import { ref, onMounted, reactive, computed } from "vue";
import { faArrowDown, faDebug, faClipboardListCheck, faUndoAlt, faHandHoldingBox, faListOl } from "@fal";
import { faSkull } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { InputNumber } from "primevue"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"

library.add(faSkull, faArrowDown, faDebug, faClipboardListCheck, faUndoAlt, faHandHoldingBox, faListOl);


defineProps<{
    data: TableTS
    tab?: string
    state: string
}>();

const emit = defineEmits<{
    'update:quantity-to-resend': [itemId: string | number, value: number]
    'validation-error': [itemId: string | number, hasError: boolean]
}>();



function orgStockRoute(deliveryNoteItem: DeliverNoteItem) {

    switch (route().current()) {
        case "grp.org.warehouses.show.dispatching.delivery_notes.show":
            return route(
                "grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show",
                [route().params["organisation"], route().params["warehouse"], deliveryNoteItem.org_stock_slug])
        default:
            return "";
    }
}


const isMounted = ref(false);
onMounted(() => {
    isMounted.value = true;
});


const innerWidth = ref(0)
onMounted(() => {
    innerWidth.value = window.innerWidth
})

// Section: Validation for quantity_to_resend
const validationErrors = reactive<{ [key: string]: string[] }>({});

const validateQuantityToResend = (item: any, value: number) => {
    const errors: string[] = [];
    const itemId = item.id;
    
    // Clear previous errors
    delete validationErrors[itemId];
   
    
    // Validation rules
    if (value < 0) {
        errors.push(trans('Quantity cannot be negative'));
    }
    
    if (value > item.quantity_dispatched) {
        errors.push(trans('Quantity cannot exceed dispatched quantity'));
    }
    
    // // Store errors if any
    if (errors.length > 0) {
        validationErrors[itemId] = errors;
    }
    
    return errors.length === 0;
};

const isQuantityToResendInvalid = computed(() => {
    return (item: any) => {
        // Safe guard: pastikan validationErrors dan item.id ada
        if (!validationErrors.value || !item?.id) {
            return false;
        }

        const errors = validationErrors.value[item.id];
        return errors && errors.length > 0;
    };
});

const onQuantityToResendInput = (item: any, value: number) => {
    emit('update:quantity-to-resend', item.id, value);
    const isValid = validateQuantityToResend(item, value);
    emit('validation-error', item.id, !isValid);
};

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" rowAlignTop>


        <!-- Column: Reference -->
        <template #cell(org_stock_code)="{ item: deliveryNoteItem }">
            <Link :href="orgStockRoute(deliveryNoteItem)" class="primaryLink">
            {{ deliveryNoteItem.org_stock_code }}
            </Link>
        </template>

        <template #cell(quantity_dispatched)="{ item: item, proxyItem }">
            <FractionDisplay v-if="item.quantity_dispatched_fractional"
                             :fractionData="item.quantity_dispatched_fractional" />
            <span v-else>{{ item.quantity_dispatched }}</span>

        </template>


        <template #cell(quantity_to_resend)="{ item: item, proxyItem }">
            <div class="space-y-1">
                <InputNumber :min="0"  :invalid="isQuantityToResendInvalid(item)"
                    mode="decimal" showButtons size="small"
                    @input="(event: any) => onQuantityToResendInput(item, event.value)" />
            </div>
        </template>


    </Table>


</template>
