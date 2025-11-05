<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faCube, faFileInvoice, faFolder, faFolderOpen, faAtom, faFolderTree,
    faChartLine, faShoppingCart, faStickyNote, faMoneyBillWave
} from "@fal"
import { faCheckCircle } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref, inject } from "vue"
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
}>()

const layout = inject('layout', {});
let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)
const showDialog = ref(false)
const tableData = ref(cloneDeep(props.shopsData));
const currency = props.masterCurrency ? props.masterCurrency : layout.group.currency;

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
        attachments: AttachmentManagement
    }
    return components[currentTab.value]
})
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-assign="{ action }">
            <Button :icon="action.icon" :label="action.label" @click="showDialog = true" :style="action.style" />
        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <div v-if="mini_breadcrumbs.length" class="bg-white px-4 py-2 w-full border-gray-200 border-b overflow-x-auto">
        <Breadcrumb :model="mini_breadcrumbs">
            <template #item="{ item }">
                <div class="flex items-center gap-1 whitespace-nowrap">
                    <component :is="item.to ? Link : 'span'"
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

    <component :is="component" :tab="currentTab" :master="true" :data="props[currentTab]" />

    <!-- ✅ PrimeVue Dialog -->
    <Dialog v-model:visible="showDialog" modal header="Add New Item" :style="{ width: '40vw' }">
        <TableSetPriceProduct v-model="tableData"  :currency="currency.code" :form="form" />
        <small v-if="form.errors.shop_products" class="text-red-500 flex items-center gap-1">
            {{ form.errors.shop_products.join(", ") }}
        </small>
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
