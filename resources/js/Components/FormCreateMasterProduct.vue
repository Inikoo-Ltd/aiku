<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Cleaned & Optimized by ChatGPT
-->

<script setup lang="ts">
import { router, useForm } from "@inertiajs/vue3";
import { ref, computed, inject } from "vue";
import Drawer from "primevue/drawer";
import Button from "@/Components/Elements/Buttons/Button.vue";
import PureInput from "@/Components/Pure/PureInput.vue";
import ListSelector from "@/Components/ListSelector.vue";
import { trans } from "laravel-vue-i18n";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { routeType } from "@/types/route";
import TableSetPriceProduct from "@/Components/TableSetPriceProduct.vue";
import axios from "axios";
import SideEditorInputHTML from "./CMS/Fields/SideEditorInputHTML.vue";
import PureInputDimension from "./Pure/PureInputDimension.vue";
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
    faExpand,
    faXmark,
    faCamera
} from "@fortawesome/free-solid-svg-icons";
import { faChevronUp, faChevronDown } from "@far";
import { ulid } from "ulid";
import { notify } from "@kyvg/vue3-notification";
import { cloneDeep } from "lodash";
import PureInputNumber from "./Pure/PureInputNumber.vue";
import Image from "./Image.vue";
import search from "@/Pages/Iris/Search.vue"

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
    faExpand,
    faXmark,
    faCamera
);

const props = defineProps<{
    storeProductRoute: routeType
    showDialog: boolean,
    masterCurrency?: string
    shopsData?: any
    masterProductCategory: string | number
}>();

const emits = defineEmits(["update:showDialog"]);
const tableData = ref(cloneDeep(props.shopsData));
const detailsVisible = ref(true);
const tableVisible = ref(true);
const isFull = ref(false); // <-- state untuk toggle full screen
let abortController: AbortController | null = null
let debounceTimer: any = null
const key = ref(ulid())
const layout = inject('layout', {});
const currency = props.masterCurrency ? props.masterCurrency : layout.group.currency;
const loading = ref(null)

// Inertia form
const form = useForm({
    code: "",
    name: "",
    unit: '',
    units: null,
    trade_units: [],
    image: null,
    shop_products: null,
    marketing_weight: 0,
    description: "",
    description_title: "",
    description_extra: "",
    dimensions: {
        h: 0,
        l: 0,
        w: 0,
        type: "sphere",
        units: "cm"
    }
});

// Image upload states
const fileInput = ref<HTMLInputElement | null>(null)
const previewUrl = ref<string | null>(null)

const chooseImage = () => {
    fileInput.value?.click()
}

const previewImage = (e: Event) => {
    const target = e.target as HTMLInputElement
    const file = target.files?.[0]
    if (file) {
        previewUrl.value = URL.createObjectURL(file)
        form.image = file // hanya kirim file kalau user pilih
    }
}

const resetImage = () => {
    previewUrl.value = null
    form.image = null
    if (fileInput.value) fileInput.value.value = ""
}

const getTableData = (data) => {
    if (debounceTimer) clearTimeout(debounceTimer)

    debounceTimer = setTimeout(async () => {
        if (abortController) abortController.abort()
        abortController = new AbortController()

        const finalDataTable: Record<number, { price: number | string }> = {}
        for (const tableDataItem of data.data) {
            finalDataTable[tableDataItem.id] = {
                price: tableDataItem.product.price || null,
                has_org_stocks: tableDataItem.product.has_org_stocks,
                rrp: tableDataItem.product.rrp || null
            }
        }

        try {
            const response = await axios.post(
                route("grp.models.master_product_category.product_creation_data", {
                    masterProductCategory: props.masterProductCategory,
                }),
                { trade_units: form.trade_units, shop_products: finalDataTable },
                { signal: abortController.signal }
            )

            console.log("Response data:", response.data)
            for (const item of response.data) {
                const index = tableData.value.data.findIndex((row: any) => row.id === item.id)
                if (index !== -1) {
                    tableData.value.data[index].product = {
                        ...tableData.value.data[index].product,
                        ...item.org_stocks_data,
                    }
                }
            }
        } catch (error: any) {
            if (!(axios.isCancel(error) || error.name === "CanceledError")) {
                console.error("Terjadi error:", error)
            }
        }
    }, 500)
}

const ListSelectorChange = (value) => {
    if (value.length >= 1) {
        form.name = value[0].name
        form.code = value[0].code
        form.unit = value[0].type
        form.image = value[0]?.image?.source || null
        form.marketing_weight = value[0].weight
        form.description = value[0].description
        form.description_title = value[0].description_title
        form.description_extra = value[0].description_extra
        form.units = value[0]?.units || 1
    }
    getTableData(tableData.value)
}

const submitForm = async (redirect = true) => {
    form.processing = true
    form.errors = {}

    if(redirect) loading.value = 'save'
    else loading.value = 'create'

    const finalDataTable: Record<number, { price: number | string }> = {}
    for (const tableDataItem of tableData.value.data) {
        finalDataTable[tableDataItem.id] = {
            price: tableDataItem.product.price,
            create_webpage: tableDataItem.product.has_org_stocks,
            rrp: tableDataItem.product.rrp
        }
    }

    // Build payload manual
    const payload: any = {
        ...form.data(),
        shop_products: finalDataTable
    }

    // Hapus image kalau tidak diganti user
    if (!(form.image instanceof File)) {
        delete payload.image
    }

    console.log("Payload to submit:", payload)
    try {
        const response = await axios.post(
            route(props.storeProductRoute.name, props.storeProductRoute.parameters),
            payload,
            { headers: { "Content-Type": "multipart/form-data" } }
        )

        if (redirect) {
            router.visit(route('grp.masters.master_shops.show.master_families.master_products.show', {
                masterShop: route().params['masterShop'],
                masterFamily: route().params['masterFamily'],
                masterProduct: response.data.slug
            }))
        } else {
            tableData.value.data = cloneDeep(props.shopsData.data);
            form.reset()
            key.value = ulid()
            previewUrl.value = null
            notify({ title: trans("success"), text: "success to create product", type: "success" })
        }
    } catch (error: any) {
        console.log("Error response:", error.response)
        if (error.response && error.response.status === 422) {
            form.errors = error.response.data.errors || {}
            if (form.errors.code || form.errors.unit || form.errors.name) {
                detailsVisible.value = true
            }
             notify({
                title: trans("Something went wrong"),
                text: error.response.data.message || trans("Please try again"),
                type: 'error'
            })
        } else {
            notify({
                title: trans("Something went wrong"),
                text: error.message || trans("Please try again"),
                type: 'error'
            })
        }
    } finally {
        form.processing = false
        loading.value = null
    }
}

const selectorTab = [
    {
        label: trans("To do"),
        routeFetch: {
            name: "grp.json.master-product-category.recommended-trade-units",
            parameters: { masterProductCategory: route().params["masterFamily"] },
        },
    },
    {
        label: trans("Done"),
        routeFetch: {
            name: "grp.json.master-product-category.taken-trade-units",
            parameters: { masterProductCategory: route().params["masterFamily"] },
        },
    },
    {
        label: trans("All"),
        search: true,
        routeFetch: {
            name: "grp.json.master-product-category.all-trade-units",
            parameters: { masterProductCategory: route().params["masterFamily"] },
        },
    },
];

const drawerVisible = computed({
    get: () => props.showDialog,
    set: (val: boolean) => emits("update:showDialog", val),
});

const toggleFull = () => {
    isFull.value = !isFull.value;
};
</script>

<template>
    <Drawer v-model:visible="drawerVisible" position="right" :class="[isFull ? '!w-full' : '!w-full md:!w-3/4']">
        <!-- Header -->
        <template #header>
            <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2 flex-1">
                {{ trans("Create Product") }}
            </h2>
            <button @click="toggleFull" class="text-gray-500 hover:text-gray-700 mx-3">
                <FontAwesomeIcon :icon="isFull ? faMinimize : faExpand" />
            </button>
        </template>

        <!-- Content -->
        <div class="p-4 pt-0 space-y-6 overflow-y-auto">
            <!-- Trade Unit Selector -->
            <div>
                <ListSelector :key="key" v-model="form.trade_units" :withQuantity="true" :tabs="selectorTab"
                    head_label="Select Trade Units" @update:model-value="ListSelectorChange" key_quantity="quantity"
                    :routeFetch="{
                        name: 'grp.json.master-product-category.recommended-trade-units',
                        parameters: { masterProductCategory: route().params['masterFamily'] }
                    }" />
                <small v-if="form.errors.trade_units" class="text-red-500 text-xs mt-1 flex items-center gap-1">
                    <FontAwesomeIcon :icon="faCircleExclamation" />
                    {{ form.errors.trade_units }}
                </small>
            </div>

            <!-- Product Details & Price -->
            <div v-if="form.trade_units.length" class="pt-4">
                <button
                    class="w-full flex items-center justify-between border-b pb-2 text-sm font-semibold text-gray-600 hover:text-gray-800"
                    @click="detailsVisible = !detailsVisible">
                    <span>{{ trans("Product Details") }}</span>
                    <FontAwesomeIcon :icon="detailsVisible ? faChevronUp : faChevronDown" class="text-xs" />
                </button>

                <div v-if="detailsVisible" class="grid grid-cols-[140px_1fr] gap-6 mt-4 bg-white">
                    <!-- Image Upload Box -->

                    <div class="flex">
                        <div class="relative w-32 h-32 border-2 border-dashed border-gray-300 rounded-xl flex items-center justify-center bg-gray-50 hover:bg-gray-100 cursor-pointer transition shadow-sm"
                            @click="(e)=> form.trade_units.length > 1 ?  chooseImage(e) : null">

                            <!-- Gambar baru (upload preview) -->
                            <img v-if="previewUrl" :src="previewUrl" alt="Preview"
                                class="w-full h-full object-cover rounded-xl" />

                            <!-- Gambar lama dari DB (string link) -->
                            <Image v-else-if="form.image" :src="form.image"
                                alt="Existing" class="w-full h-full object-cover rounded-xl" />

                            <!-- Kalau kosong -->
                            <div v-else class="flex flex-col items-center text-gray-400 text-xs">
                                <FontAwesomeIcon :icon="faCamera" class="text-lg mb-1" />
                                <span>Upload</span>
                            </div>

                            <!-- Tombol remove -->
                            <button v-if="previewUrl || form.image && form.trade_units.length > 1 " @click.stop="resetImage"
                                class="absolute top-1 right-1 bg-white text-gray-500 rounded-full p-1 shadow hover:text-red-500">
                                <FontAwesomeIcon :icon="faXmark" class="w-3 h-3" />
                            </button>
                        </div>


                        <input type="file" v-if="form.trade_units.length > 1" accept="image/*" ref="fileInput" class="hidden" @change="previewImage" />
                    </div>

                    <!-- Form Fields -->
                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Code</label>
                            <PureInput type="text" v-model="form.code" @update:model-value="form.errors.code = null"
                                class="w-full" />
                            <small v-if="form.errors.code" class="text-red-500 text-xs flex items-center gap-1 mt-1">
                                <FontAwesomeIcon :icon="faCircleExclamation" />
                                {{ form.errors.code.join(", ") }}
                            </small>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Name</label>
                            <PureInput type="text" v-model="form.name" @update:model-value="form.errors.name = null"
                                class="w-full" />
                            <small v-if="form.errors.name" class="text-red-500 text-xs flex items-center gap-1 mt-1">
                                <FontAwesomeIcon :icon="faCircleExclamation" />
                                {{ form.errors.name.join(", ") }}
                            </small>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Unit</label>
                            <PureInput v-model="form.unit" @update:model-value="form.errors.unit = null"
                                class="w-full" />
                            <small v-if="form.errors.unit" class="text-red-500 text-xs flex items-center gap-1 mt-1">
                                <FontAwesomeIcon :icon="faCircleExclamation" />
                                {{ form.errors.unit.join(", ") }}
                            </small>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Units</label>
                            <PureInputNumber v-model="form.units" @update:model-value="form.errors.units = null"
                                class="w-full" />
                            <small v-if="form.errors.units" class="text-red-500 text-xs flex items-center gap-1 mt-1">
                                <FontAwesomeIcon :icon="faCircleExclamation" />
                                {{ form.errors.units.join(", ") }}
                            </small>
                        </div>

                        <div v-if="form.trade_units.length > 1">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Marketing Weight</label>
                            <PureInputNumber v-model="form.marketing_weight"
                                @update:model-value="form.errors.marketing_weight = null" class="w-full"
                                :suffix="'g'" />
                            <small v-if="form.errors.marketing_weight"
                                class="text-red-500 text-xs flex items-center gap-1 mt-1">
                                <FontAwesomeIcon :icon="faCircleExclamation" />
                                {{ form.errors.marketing_weight.join(", ") }}
                            </small>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Description title</label>
                            <PureInput type="text" v-model="form.description_title"
                                @update:model-value="form.errors.description_title = null" class="w-full" />
                            <small v-if="form.errors.description_title"
                                class="text-red-500 text-xs flex items-center gap-1 mt-1">
                                <FontAwesomeIcon :icon="faCircleExclamation" />
                                {{ form.errors.description_title.join(", ") }}
                            </small>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Dimension</label>
                            <PureInputDimension :rows="4" v-model="form.dimensions"
                                @update:model-value="form.errors.dimensions = null" class="w-full" />
                            <small v-if="form.errors.dimensions"
                                class="text-red-500 text-xs flex items-center gap-1 mt-1">
                                <FontAwesomeIcon :icon="faCircleExclamation" />
                                {{ form.errors.dimensions.join(", ") }}
                            </small>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                            <SideEditorInputHTML :rows="4" v-model="form.description"
                                @update:model-value="form.errors.description = null" class="w-full" />
                            <small v-if="form.errors.description"
                                class="text-red-500 text-xs flex items-center gap-1 mt-1">
                                <FontAwesomeIcon :icon="faCircleExclamation" />
                                {{ form.errors.description.join(", ") }}
                            </small>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Description extra</label>
                            <SideEditorInputHTML :rows="4" v-model="form.description_extra"
                                @update:model-value="form.errors.description_extra = null" class="w-full" />
                            <small v-if="form.errors.description_extra"
                                class="text-red-500 text-xs flex items-center gap-1 mt-1">
                                <FontAwesomeIcon :icon="faCircleExclamation" />
                                {{ form.errors.description_extra.join(", ") }}
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Product Section -->
            <div v-if="form.trade_units.length">
                <button
                    class="w-full flex items-center justify-between border-b pb-2 text-sm font-semibold text-gray-600 hover:text-gray-800"
                    @click="tableVisible = !tableVisible">
                    <span>{{ trans("Shop Product") }}</span>
                    <FontAwesomeIcon :icon="tableVisible ? faChevronUp : faChevronDown" class="text-xs" />
                </button>

                <div v-if="tableVisible" class="mt-4">
                    <TableSetPriceProduct v-model="tableData" :key="key" :currency="currency.code" :form="form"  />
                    <small v-if="form.errors.shop_products" class="text-red-500 flex items-center gap-1">
                        {{ form.errors.shop_products.join(", ") }}
                    </small>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <template #footer>
            <div class="flex justify-end gap-3 border-t pt-3">
                <Button label="Cancel" type="negative" class="!px-5" @click="emits('update:showDialog', false)" />
                <Button type="create" :label="'save & create another one'" :loading="loading == 'create'"
                    :disabled="form.trade_units.length < 1" class="!px-6" @click="submitForm(false)" />
                <Button type="save" :loading="loading == 'save'" :disabled="form.trade_units.length < 1" class="!px-6"
                    @click="submitForm" />
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
