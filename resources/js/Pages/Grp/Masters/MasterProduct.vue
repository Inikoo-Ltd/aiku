<script setup lang="ts">
import { Head, Link, useForm, router } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faCube, faFileInvoice, faFolder, faFolderOpen, faAtom, faFolderTree,
    faChartLine, faShoppingCart, faStickyNote, faMoneyBillWave
} from "@fal"
import { faCheckCircle, faSave } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref, inject, onMounted, watch } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import TableTradeUnits from "@/Components/Tables/Grp/Goods/TableTradeUnits.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import MasterProductShowcase from "@/Components/Showcases/Grp/MasterProductShowcase.vue"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue"
import TradeUnitImagesManagement from "@/Components/Goods/ImagesManagement.vue"
import Breadcrumb from "primevue/breadcrumb"
import AttachmentManagement from "@/Components/Goods/AttachmentManagement.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Dialog from "primevue/dialog"
import TableSetPriceProduct from "@/Components/TableSetPriceProduct.vue";
import { cloneDeep } from "lodash-es";
import { trans } from "laravel-vue-i18n"
import axios from "axios";
import TradeUnit from "../Goods/TradeUnit.vue"

library.add(
    faChartLine, faCheckCircle, faFolderTree, faFolder, faCube,
    faShoppingCart, faFileInvoice, faStickyNote, faMoneyBillWave, faFolderOpen, faAtom
)

const props = defineProps<{
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    title: string
    showcase?: {}
    history?: {}
    language?: {}
    products?: {}
    trade_units?: {}
    images?: {}
    mini_breadcrumbs?: any[]
    attachments?: {}
    shopsData: any[]
    masterAsset: {}
    tradeUnit : {}
}>()
console.log('sdsjkh',props.trade_units)
const layout = inject('layout', {});
let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)
const showDialog = ref(false)
const tableData = ref(cloneDeep(props.shopsData));
const disableClone = ref(true)
const loading = ref(false)
const currency = props.masterCurrency ?? layout.group.currency;

const form = useForm({
    shop_products: null,
});

const component = computed(() => {
    const components = {
        showcase: MasterProductShowcase,
        history: TableHistories,
        products: TableProducts,
        images: TradeUnitImagesManagement,
        trade_units: TableTradeUnits,
        attachments: AttachmentManagement,
    }
    return components[currentTab.value]
})

function openModal() {
    showDialog.value = true;
}

const submitForm = async (redirect = true) => {
    loading.value = true;
    form.processing = true
    form.errors = {}

    const finalDataTable: Record<number, { price: number | string }> = {}
    for (const tableDataItem of tableData.value.data) {
        let create_in_shop = tableDataItem.product.create_in_shop
        let price = tableDataItem.product.price
        let rrp = tableDataItem.product.rrp

        if (!create_in_shop) {
            rrp = 1;
            price = 1;
        }

        finalDataTable[tableDataItem.id] = {
            price: price,
            create_in_shop: create_in_shop ? 'Yes' : 'No',
            rrp: rrp
        }
    }
    let params = route().params;
    params['masterFamily'] = String(props.masterAsset.master_family.id);

    // Build payload manual
    const payload: any = {
        ...form.data(),
        shop_products: finalDataTable,
    }

    await axios.post(
        route('grp.models.master_family.clone_to_other_store', params),
        payload,
        { headers: { "Content-Type": "multipart/form-data" } }
    ).catch((error) => {
        loading.value = false;
        console.error(error);
    })
        .then((response) => {
            loading.value = false;
            router.reload();
            showDialog.value = false;
            refreshModalData();
        });

}

function refreshModalData() {
    let productCodes = new Set(props.products?.data?.map(p => p.shop_code));
    tableData.value.data = tableData.value.data.filter(item => !productCodes.has(item.code));
    if (tableData.value.data.length > 0) {
        disableClone.value = false;
    }
}

watch(() => currentTab.value, (value) => {
    if (value === "products") {
        refreshModalData()
    }
})

onMounted(() => {
    refreshModalData();
})


</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #afterTitle="{data}">
            <component v-if="data.iconRight || data.titleRight || data.afterTitle"
                :is="data?.iconRight?.url ? 'a' : 'div'"
                :href="data?.iconRight?.url ? route(data?.iconRight?.url.name, data?.iconRight?.url.parameters) : ''">
                <div class="flex gap-x-2 items-center">
                    <FontAwesomeIcon v-if="data.iconRight" v-tooltip="data.iconRight.tooltip || ''"
                        :icon="data.iconRight?.icon || data.iconRight" class="align-top" :class="data.iconRight.class"
                        aria-hidden="true" :color="data.iconRight.color" :rotation="data?.iconRight?.icon_rotation" />
                    <span v-if="data.titleRight" class="text-lg">{{ data.titleRight }}</span>
                    <div v-if="data.afterTitle" class="font-normal text-lg leading-none">
                        {{ data.afterTitle.label }}
                    </div>
                </div>
            </component>
          <!--   <Link :href="route('grp.trade_units.units.show',[])" class="flex gap-x-2 items-center">
                <FontAwesomeIcon v-tooltip="'trade unit'" :icon="faAtom" class="align-top" :class="'text-gray-300'"  aria-hidden="true" />
            </Link> -->
        </template>

        <template #button-assign="{ action }">
            <Button v-if="currentTab === 'products'" :icon="action.icon" :label="action.label" @click="openModal()"
                :style="action.style" />
            <div v-else></div>
        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <div v-if="mini_breadcrumbs.length" class="bg-white px-4 py-2 w-full border-gray-200 border-b overflow-x-auto">
        <Breadcrumb :model="mini_breadcrumbs">
            <template #item="{ item }">
                <div class="flex items-center gap-1 whitespace-nowrap">
                    <component :is="item.to ? Link : 'span'" :handleTabUpdate="handleTabUpdate"
                        :href="item.to ? route(item.to.name, item.to.parameters) : undefined" :title="item.title"
                        class="flex items-center gap-2 text-sm transition-colors duration-150"
                        :class="item.to ? 'text-gray-500' : 'text-gray-500 cursor-default'">
                        <FontAwesomeIcon :icon="item.icon" class="w-4 h-4" />
                        <span>{{ item.label || '-' }}</span>
                    </component>
                </div>
            </template>
        </Breadcrumb>
    </div>

    <component :is="component" :tab="currentTab" :master="true" :data="props[currentTab]" :handleTabUpdate />

    <!-- ✅ PrimeVue Dialog -->
    <Dialog v-model:visible="showDialog" modal header="Add Item to Other Shop" :style="{ width: '60vw' }">
        <TableSetPriceProduct v-model="tableData" :key="key" :currency="currency.code" :form="form"
            :disable-exist="true" />
        <small v-if="form.errors.shop_products" class="text-red-500 flex items-center gap-1">
            {{ form.errors.shop_products.join(", ") }}
        </small>
        <div class="pt-5 flex items-end w-full">
            <Button :class="'ms-auto'" :disabled="disableClone" v-on:click="submitForm(true)" :loading="loading">
                <FontAwesomeIcon :icon="faSave" />
                {{ trans("Save") }}
            </Button>
        </div>
    </Dialog>
</template>

<style scoped>
:deep(.p-breadcrumb) {
    padding: 0;
    margin: 0;
    background: transparent;
    border: none;
}
</style>
