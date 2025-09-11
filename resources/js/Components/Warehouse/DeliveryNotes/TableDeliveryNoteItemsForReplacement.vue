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
const inputRefs = ref<{ [key: string]: HTMLInputElement }>({});

// Helper function to set input ref
const setInputRef = (itemId: string | number, el: HTMLInputElement | null) => {
    if (el) {
        inputRefs.value[itemId] = el;
    } else {
        delete inputRefs.value[itemId];
    }
};

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

    // Store errors if any
    if (errors.length > 0) {
        validationErrors[itemId] = errors;
    }

    return errors.length === 0;
};

const isQuantityToResendInvalid = computed(() => {
    return (item: any) => {
        // Safe guard: pastikan validationErrors dan item.id ada
        if (!validationErrors || !item?.id) {
            return false;
        }

        const errors = validationErrors[item.id];
        return errors && errors.length > 0;
    };
});

const onQuantityToResendInput = (item: any, event: Event) => {
    const target = event.target as HTMLInputElement;
    const value = parseFloat(target.value) || 0;

    emit('update:quantity-to-resend', item.id, value);
    const isValid = validateQuantityToResend(item, value);
    emit('validation-error', item.id, !isValid);
};

const onFractionClick = (item: any) => {
    const maxValue = parseFloat(item.quantity_dispatched) || 0;
    const itemId = item.id;


    // Update the specific input field using its ref
    const inputElement = inputRefs.value[itemId];
    if (inputElement) {
        inputElement.value = maxValue.toString();
    }
    
    emit('update:quantity-to-resend', item.id, maxValue);
    const isValid = validateQuantityToResend(item, maxValue);
    emit('validation-error', item.id, !isValid);
};

// Dynamic classes for input
const getInputClasses = computed(() => {
    return (item: any) => {
        const baseClasses = "w-full px-3 py-2 text-sm border rounded-l-md focus:outline-none focus:ring-2 focus:ring-opacity-50";
        const invalidClasses = "bg-red-50 border-red-500 text-red-900 placeholder-red-700 focus:ring-red-500 focus:border-red-500";
        const validClasses = "bg-white border-gray-300 text-gray-900 focus:ring-blue-500 focus:border-blue-500";

        return `${baseClasses} ${isQuantityToResendInvalid.value(item) ? invalidClasses : validClasses}`;
    };
})

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" rowAlignTop>

        <!-- Column: Reference -->
        <template #cell(org_stock_code)="{ item: deliveryNoteItem }">
            <Link :href="orgStockRoute(deliveryNoteItem)" class="primaryLink">
            {{ deliveryNoteItem.org_stock_code }}
            </Link>
        </template>

        <template #cell(quantity_dispatched)="{ item: item }">
            <FractionDisplay v-if="item.quantity_dispatched_fractional" @click="onFractionClick(item)" class="cursor-pointer"
                :fractionData="item.quantity_dispatched_fractional" />
            <span v-else>{{ item.quantity_dispatched }}</span>
        </template>

        <template #cell(quantity_to_resend)="{ item: item }">
            <div class="space-y-1">
                <div class="flex items-center justify-end">
                    <input 
                        :ref="(el) => setInputRef(item.id, el as HTMLInputElement)"
                        type="number" 
                        :min="0" 
                        :class="getInputClasses(item)" 
                        class="rounded-md !w-28"
                        @input="onQuantityToResendInput(item, $event)" 
                        placeholder="0" 
                    />
                </div>
            </div>
        </template>

    </Table>
</template>