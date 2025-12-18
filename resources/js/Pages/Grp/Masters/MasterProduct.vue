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
import { PageHeadingTypes } from "@/types/PageHeading"
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue"
import TradeUnitImagesManagement from "@/Components/Goods/ImagesManagement.vue"
import Breadcrumb from "primevue/breadcrumb"
import AttachmentManagement from "@/Components/Goods/AttachmentManagement.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Dialog from "primevue/dialog"
import TableSetPriceProduct from "@/Components/TableSetPriceProduct.vue";
import { cloneDeep, uniqueId } from "lodash-es";
import { trans } from "laravel-vue-i18n"
import axios from "axios";
import ProductSales from "@/Components/Product/ProductSales.vue"
import { notify } from "@kyvg/vue3-notification"

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
    sales: {}
    salesData?: {}
    images?: {}
    mini_breadcrumbs?: any[]
    attachments?: {}
    shopsData: any[]
    masterAsset: {}
    tradeUnits : {}
    is_single_trade_unit?: boolean
    trade_unit_slug?: string
}>()
console.log('sdsjkh',props)


const layout = inject('layout', {});
let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)
const showDialog = ref(false)
const tableData = ref(cloneDeep(props.shopsData))
const key = ref(crypto.randomUUID())
const disableClone = ref(true)
const loading = ref(false)
const currency = props.masterCurrency ?? layout.group.currency;

const form = useForm({
    shop_products: null,
    trade_units: props.tradeUnits
});

const component = computed(() => {
    const components: Record<string, any> ={
        showcase: MasterProductShowcase,
        history: TableHistories,
        products: TableProducts,
        images: TradeUnitImagesManagement,
        trade_units: TableTradeUnits,
        attachments: AttachmentManagement,
        sales: ProductSales,
    }
    return components[currentTab.value]
})

function openModal() {
    refreshModalData()
    showDialog.value = true;
}

const submitForm = async () => {
    loading.value = true
    form.clearErrors()

    try {
        const finalDataTable: Record<number, any> = {}

        for (const item of tableData.value.data) {
            const create = item.product.create_in_shop

            finalDataTable[item.id] = {
                price: create ? item.product.price : 1,
                rrp: create ? item.product.rrp : 1,
                create_in_shop: create ? 'Yes' : 'No'
            }
        }

        const params = {
            ...route().params,
            masterFamily: String(props.masterAsset.master_family.id)
        }

       const response = await axios.post(
            route('grp.models.master_family.clone_to_other_store', params),
            {
                ...form.data(),
                shop_products: finalDataTable
            },
            { headers: { "Content-Type": "multipart/form-data" } }
        )

        notify({
            title: trans('Created Successfully'),
            text: trans('Added products to Selected Stores'),
            type: 'success'
        })

        router.reload({ only : 'products'})
        showDialog.value = false
        key.value = crypto.randomUUID()
        refreshModalData()
        router.reload({ only: ['products'] })

    } catch (error: any) {
        if (error.response?.data?.errors) {
            notify({
                title: trans('Something went wrong'),
                data: {
                    html: Object.values(error.response.data.errors).flat().join('<br>')
                },
                type: 'error'
            })
        }
    } finally {
        loading.value = false
    }
}


function refreshModalData() {
    const productCodes = new Set(
        props.products?.data?.map(p => p.shop_code)
    )

    tableData.value = {
        ...tableData.value,
        data: tableData.value.data.filter(
            item => !productCodes.has(item.code)
        )
    }

    disableClone.value = tableData.value.data.length === 0
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

            <Link v-if="is_single_trade_unit && trade_unit_slug" :href="route('grp.trade_units.units.show', [trade_unit_slug])" v-tooltip="trans('Go to Trade Unit')">
                <FontAwesomeIcon
                    icon="fal fa-atom"
                />
            </Link>
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

    <component :is="component" :tab="currentTab" :master="true" :data="props[currentTab]" :salesData="props.salesData" :handleTabUpdate :currency="currency" />

    <!-- ✅ PrimeVue Dialog -->
    <Dialog v-model:visible="showDialog" modal header="Add Item to Other Shop" :style="{ width: '60vw' }">
        <TableSetPriceProduct :key="key" v-model="tableData" :currency="currency.code" :form="form"
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
