<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Cleaned & Optimized by ChatGPT
-->

<script setup lang="ts">
import { router, useForm } from "@inertiajs/vue3";
import { ref, computed, inject, toRaw } from "vue";
import Drawer from "primevue/drawer";
import Button from "@/Components/Elements/Buttons/Button.vue";
import PureInput from "@/Components/Pure/PureInput.vue";
import ListSelector from "@/Components/ListSelector.vue";
import { trans } from "laravel-vue-i18n";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { routeType } from "@/types/route";
import { InputNumber } from "primevue";
import TableSetPriceProduct from "@/Components/TableSetPriceProduct.vue";
import axios from "axios";

// FontAwesome
import { library } from "@fortawesome/fontawesome-svg-core";
import {
    faShapes,
    faSortAmountDownAlt,
    faSortAmountDown,
    faHome,
    faPlus,
    faMinus,
    faCircleExclamation,
    faArrowTrendUp,
    faArrowTrendDown,
    faTags,
    faMinimize,
    faExpand
} from "@fortawesome/free-solid-svg-icons";
import { faChevronUp, faChevronDown } from "@far";
import { ulid } from "ulid";

library.add(
    faShapes,
    faSortAmountDownAlt,
    faSortAmountDown,
    faHome,
    faPlus,
    faMinus,
    faCircleExclamation,
    faArrowTrendUp,
    faArrowTrendDown,
    faTags,
    faMinimize,
    faExpand
);

const props = defineProps<{
    storeProductRoute: routeType
    showDialog: boolean,
    masterCurrency?: string
    shopsData? :any
    masterProductCategory : string|number
}>();

const emits = defineEmits(["update:showDialog"]);
const tableData = ref(toRaw(props.shopsData))
const detailsVisible = ref(true);
const tableVisible = ref(true);
const isFull = ref(false); // <-- state untuk toggle full screen
let abortController: AbortController | null = null
let debounceTimer: any = null
const key = ref(ulid())
const layout = inject('layout', {});
const currency = props.masterCurrency ? props.masterCurrency : layout.group.currency;

// Inertia form
const form = useForm({
    code: "",
    name: "",
    unit: 0,
    trade_units: [],
    price: null,
    shop_products : null
});

const getTableData = () => {
    // clear debounce kalau ada
    if (debounceTimer) {
        clearTimeout(debounceTimer)
    }

    // set debounce 500ms
    debounceTimer = setTimeout(async () => {
        // cancel request sebelumnya kalau masih jalan
        if (abortController) {
            abortController.abort()
        }

        // bikin abortController baru
        abortController = new AbortController()

        try {
            console.log("Loading mulai…")
            const response = await axios.post(
                route("grp.models.master_product_category.product_creation_data", {
                    masterProductCategory: props.masterProductCategory,
                }),
                { trade_units : form.trade_units},
                {
                    signal: abortController.signal, // attach abort signal
                }
            )

           
            console.log("Data berhasil diambil:", response)
        } catch (error: any) {
            if (axios.isCancel(error)) {
                console.log("Request dibatalkan")
            } else if (error.name === "CanceledError") {
                console.log("Request dibatalkan (AbortController)")
            } else {
                console.error("Terjadi error:", error)
            }
        } finally {
            console.log("Loading selesai.")
        }
    }, 500) // delay debounce
}

const ListSelectorChange = (value) => {
    getTableData()
    if (value.length >= 1) {
        form.name = value[0].name;
        form.code = value[0].code;
        form.unit = value[0].type;
    }
};



const submitForm = async (redirect = true) => {
    form.processing = true
    form.errors = {}
     
     const finalDataTable: Record<number, { price: number | string }> = {}

    for (const tableDataItem of tableData.value.data) {
        finalDataTable[tableDataItem.id] = {
            price: tableDataItem.product.price,
            create_webpage : tableDataItem.product.create_webpage
        }
    }

    form.shop_products = finalDataTable


    try {
        const response = await axios.post(
            route(props.storeProductRoute.name, props.storeProductRoute.parameters),
            form.data(), // <-- same as inertia form payload
        )

        console.log("pppp", response.data)

        if (redirect) {
            emits("update:showDialog", false)
            // If you need redirect, you can call router.visit() here
            // router.visit(route('grp.masters.master_shops.show.master_families.master_products.show', {...}))
        } else {
            form.reset()
            tableData.value = props.shopsData
            key.value = ulid()
        }
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            // Laravel validation errors → hydrate into inertia form
            form.errors = error.response.data.errors || {}
            if (form.errors.code || form.errors.unit || form.errors.name) {
                detailsVisible.value = true
            }
        } else {
            console.error("Unexpected error:", error)
        }
    } finally {
        form.processing = false
    }
}

// Tabs
const selectorTab = [
    {
        label: "Recommended",
        routeFetch: {
            name: "grp.json.master-product-category.recommended-trade-units",
            parameters: { masterProductCategory: route().params["masterFamily"] },
        },
    },
    {
        label: "Taken",
        routeFetch: {
            name: "grp.json.master-product-category.taken-trade-units",
            parameters: { masterProductCategory: route().params["masterFamily"] },
        },
    },
    {
        label: "All",
        routeFetch: {
            name: "grp.json.master-product-category.all-trade-units",
            parameters: { masterProductCategory: route().params["masterFamily"] },
        },
    },
];

const profitMargin = computed(() => {
    if (!form.price || !form.trade_units.length) return null;
    const totalCost = form.trade_units.reduce((sum, unit) => {
        const unitPrice = Number(unit.cost_price) || 0;
        const unitQty = Number(unit.quantity) || 0;
        return sum + (unitPrice * unitQty);
    }, 0);
    if (totalCost <= 0) return 0;
    return ((form.price - totalCost) / totalCost) * 100;
});

const drawerVisible = computed({
    get: () => props.showDialog,
    set: (val: boolean) => emits("update:showDialog", val),
});

const toggleFull = () => {
    isFull.value = !isFull.value;
};

console.log(props)
</script>

<template>
    <Drawer 
        v-model:visible="drawerVisible" 
        position="right" 
        :class="[isFull ? '!w-full' : '!w-full md:!w-1/2']"
    >
        <!-- Header -->
        <template #header>
            <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2 flex-1">
                {{ trans("Create Product") }}
            </h2>
            <!-- Tombol Toggle Fullscreen -->
            <button
                @click="toggleFull"
                class="text-gray-500 hover:text-gray-700 mx-3"
            >
                <FontAwesomeIcon :icon="isFull ? faMinimize : faExpand" />
            </button>
        </template>

        <!-- Content -->
        <div class="p-4 pt-0 space-y-6 overflow-y-auto">
            <!-- Trade Unit Selector -->
            <div>
                <ListSelector
                    :key="key"
                    v-model="form.trade_units"
                    :withQuantity="true"
                    :tabs="selectorTab"
                    head_label="Select Trade Units"
                    @update:model-value="ListSelectorChange"
                    key_quantity="quantity"
                    :routeFetch="{
                        name: 'grp.json.master-product-category.recommended-trade-units',
                        parameters: { masterProductCategory: route().params['masterFamily'] }
                    }"
                />
                <small v-if="form.errors.trade_units" class="text-red-500 text-xs mt-1 flex items-center gap-1">
                    <FontAwesomeIcon :icon="faCircleExclamation" />
                    {{ form.errors.trade_units }}
                </small>
            </div>

            <!-- Product Details & Price -->
            <div v-if="form.trade_units.length" class="pt-4">
                <!-- Toggle -->
                <button
                    class="w-full flex items-center justify-between border-b pb-2 text-sm font-semibold text-gray-600 hover:text-gray-800"
                    @click="detailsVisible = !detailsVisible">
                    <span>
                        {{ trans("Product Details") }}
                    </span>
                    <FontAwesomeIcon :icon="detailsVisible ? faChevronUp : faChevronDown" class="text-xs" />
                </button>

                <!-- Details Form -->
                <div
                    v-if="detailsVisible"
                    class="grid grid-cols-2 gap-6 mt-4 p-4 rounded-xl border border-gray-200 bg-white shadow-sm"
                >
                    <!-- Code -->
                    <div>
                        <label class="font-medium block mb-1 text-sm">Code</label>
                        <PureInput type="text" v-model="form.code" @update:model-value="form.errors.code = null"
                            class="w-full" />
                        <small v-if="form.errors.code" class="text-red-500 text-xs flex items-center gap-1 mt-1">
                            <FontAwesomeIcon :icon="faCircleExclamation" />
                            {{ form.errors.code.join(", ") }}
                        </small>
                    </div>

                    <!-- Name -->
                    <div>
                        <label class="font-medium block mb-1 text-sm">Name</label>
                        <PureInput type="text" v-model="form.name" @update:model-value="form.errors.name = null"
                            class="w-full" />
                        <small v-if="form.errors.name" class="text-red-500 text-xs flex items-center gap-1 mt-1">
                            <FontAwesomeIcon :icon="faCircleExclamation" />
                             {{ form.errors.name.join(", ") }}
                        </small>
                    </div>

                    <!-- Unit -->
                    <div>
                        <label class="font-medium block mb-1 text-sm">Unit</label>
                        <PureInput v-model="form.unit" @update:model-value="form.errors.unit = null"
                            class="w-full" />
                        <small v-if="form.errors.unit" class="text-red-500 text-xs flex items-center gap-1 mt-1">
                            <FontAwesomeIcon :icon="faCircleExclamation" />
                            {{ form.errors.unit.join(", ") }}
                        </small>
                    </div>

                    <!-- Price -->
                    <div>
                        <label class="font-semibold text-gray-700 text-sm flex items-center gap-2">
                            <FontAwesomeIcon :icon="faTags" class="text-blue-500" />
                            Price ({{ currency.symbol }})
                        </label>
                        <InputNumber
                            v-model="form.price"
                            showButtons
                            buttonLayout="horizontal"
                            :step="0.25"
                            mode="currency"
                            :currency="currency.code"
                            fluid
                            :min="0"
                            :allowEmpty="true"
                            class="w-full rounded-lg border border-gray-300 shadow-sm"
                        >
                            <template #incrementbuttonicon>
                                <FontAwesomeIcon :icon="faPlus" />
                            </template>
                            <template #decrementbuttonicon>
                                <FontAwesomeIcon :icon="faMinus" />
                            </template>
                        </InputNumber>

                        <div class="flex justify-between items-center text-xs mt-2">
                            <small v-if="form.errors.price" class="text-red-500 flex items-center gap-1">
                                {{ form.errors.price }}
                            </small>
                            <span v-if="profitMargin !== null" :class="[
                                profitMargin > 0 ? 'text-green-600' : profitMargin < 0 ? 'text-red-600' : 'text-gray-500',
                                'font-medium flex items-center gap-1'
                            ]">
                                <FontAwesomeIcon :icon="profitMargin > 0 ? faArrowTrendUp : faArrowTrendDown" />
                                Profit Margin: {{ profitMargin.toFixed(2) }}%
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Product Section -->
            <div v-if="form.trade_units.length" class="">
                <!-- Toggle -->
                <button
                    class="w-full flex items-center justify-between border-b pb-2 text-sm font-semibold text-gray-600 hover:text-gray-800"
                    @click="tableVisible = !tableVisible">
                    <span>
                        {{ trans("Shop Product") }}
                    </span>
                    <FontAwesomeIcon :icon="tableVisible ? faChevronUp : faChevronDown" class="text-xs" />
                </button>

                <!-- Table -->
                <div v-if="tableVisible" class="mt-4">
                    <TableSetPriceProduct  :key="key" :data="tableData" :master_price="form.price"/>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <template #footer>
            <div class="flex justify-end gap-3 border-t pt-3">
                <Button label="Cancel" type="negative" class="!px-5" @click="emits('update:showDialog', false)" />
                <Button
                    type="create"
                    :loading="form.processing"
                    :disabled="form.trade_units.length < 1"
                    class="!px-6"
                    :label="'save & create'"
                    @click="submitForm(false)"
                />
                <Button
                    type="save"
                    :loading="form.processing"
                    :disabled="form.trade_units.length < 1"
                    class="!px-6"
                    @click="submitForm"
                />
            </div>
        </template>
    </Drawer>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: all 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    transform: translateY(-5px);
}
</style>
