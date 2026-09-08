<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faCube, faFileInvoice, faFolder, faFolderOpen, faAtom, faFolderTree,
    faChartLine, faShoppingCart, faStickyNote, faMoneyBillWave,
} from "@fal"
import { faCheckCircle, faSave, faShapes, faStar } from "@fas"
import { faCircleExclamation } from "@fortawesome/free-solid-svg-icons"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TableSetPriceProduct from "@/Components/TableSetPriceProduct.vue";
import MasterPriceCurrencyTable from "@/Components/Pure/MasterPriceCurrencyTable.vue"
import MasterRrpCurrencyTable from "@/Components/Pure/MasterRrpCurrencyTable.vue"
import { trans } from "laravel-vue-i18n"
import { ref, computed, watch, onMounted } from "vue"
import { useForm, router } from "@inertiajs/vue3"
import { cloneDeep } from "lodash-es"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"


library.add(
    faChartLine, faCheckCircle, faFolderTree, faFolder, faCube,
    faShoppingCart, faFileInvoice, faStickyNote, faMoneyBillWave, faFolderOpen, faAtom
)

const props = defineProps<{
    shopsData: any[]
    tradeUnits: any
    masterAsset: any
    products?: any
    currency: any
}>()

const emits = defineEmits<{
    (e: 'saved'): void
}>()

const tableData = ref(cloneDeep(props.shopsData))
const key = ref(crypto.randomUUID())
const disableClone = ref(true)
const loading = ref(false)
const currencies_data = ref({})
const org_data = ref(null)
const avg_org_cost = ref(0)

const tradeUnitsList = computed<any[]>(() => props.tradeUnits?.data ?? props.tradeUnits ?? [])

const form = useForm({
    shop_products: null,
    trade_units: tradeUnitsList.value,
    master_prices: null,
    master_rrps: null,
})

const unitsPerOuter = computed(() => {
    const units = tradeUnitsList.value

    return units.length == 1 ? parseInt(units[0].quantity) : 1
})

const priceByCurrency = computed(() => {
    const prices = (form.master_prices ?? {}) as Record<string, { value: number | null }>

    return Object.entries(prices).reduce((costs, [code, price]) => {
        costs[code] = price?.value ?? null

        return costs
    }, {} as Record<string, number | null>)
})

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

const fetchCreationData = async () => {
    const finalDataTable: Record<number, any> = {}
    for (const tableDataItem of tableData.value.data) {
        finalDataTable[tableDataItem.id] = {
            price: tableDataItem.product?.price || null,
            create_in_shop: tableDataItem.product?.create_in_shop,
            rrp: tableDataItem.product?.rrp || null,
        }
    }

    const trade_units = tradeUnitsList.value.map((item: any) => ({
        id: item.id,
        quantity: item.quantity,
    }))

    try {
        const response = await axios.post(
            route("grp.models.master_product_category.product_creation_data", {
                masterProductCategory: props.masterAsset.master_family.id,
            }),
            { trade_units, shop_products: finalDataTable }
        )

        for (const item of response.data.shops) {
            const index = tableData.value.data.findIndex((row: any) => row.id == item.id)
            if (index !== -1) {
                tableData.value.data[index].product = {
                    ...tableData.value.data[index].product,
                    ...item,
                    pick_fractional: item.pick_fractional,
                    rrp: item.rrp / unitsPerOuter.value,
                }
            }
        }
        console.log('product_creation_data', response.data )
        currencies_data.value = response.data.currencies
        form.master_prices = response.data.master_prices
        form.master_rrps = response.data.master_rrps
        org_data.value = response.data.org_data
        avg_org_cost.value = response.data.avg_org_cost
    } catch (error: any) {
        console.error(error)
    }
}

const submitForm = async () => {
    loading.value = true
    form.clearErrors()

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

    await axios.post(
        route('grp.models.master_family.clone_to_other_store', params),
        {
            ...form.data(),
            shop_products: finalDataTable
        },
        { headers: { "Content-Type": "multipart/form-data" } }
    ).then(() => {
        notify({
            title: trans('Created Successfully'),
            text: trans('Added products to Selected Stores'),
            type: 'success'
        })
        router.reload({ only: ['products'] })
        key.value = crypto.randomUUID()
        refreshModalData()
        emits('saved')
    }).catch((error: any) => {
        notify({
            title: trans('Something went wrong'),
            data: {
                html: Object.values(error.response.data.errors).flat().join('<br>')
            },
            type: 'error',
            duration: 5000,
        })
    }).finally(() => {
        loading.value = false
    })
}

watch(() => props.products, () => {
    refreshModalData()
}, { deep: true })

onMounted(() => {
    refreshModalData()
    fetchCreationData()
})

defineExpose({ refreshModalData })
</script>

<template>
    <div class="grid grid-cols-2 gap-5 mb-4">
        <div>
            <MasterPriceCurrencyTable
                v-model="form.master_prices"
                :currencies="currencies_data"
                :unitsPerOuter="unitsPerOuter"
                :org_data="org_data"
                :avg_org_cost="avg_org_cost"
            />
            <small v-if="form.errors.master_prices" class="text-red-500 text-xs flex items-center gap-1 mt-1">
                <FontAwesomeIcon :icon="faCircleExclamation" />
                {{ form.errors.master_prices.join(", ") }}
            </small>
        </div>

        <div>
            <MasterRrpCurrencyTable
                v-model="form.master_rrps"
                :currencies="currencies_data"
                :unitsPerOuter="unitsPerOuter"
                :costs="priceByCurrency"
            />
            <small v-if="form.errors.master_rrps" class="text-red-500 text-xs flex items-center gap-1 mt-1">
                <FontAwesomeIcon :icon="faCircleExclamation" />
                {{ form.errors.master_rrps.join(", ") }}
            </small>
        </div>
    </div>

    <TableSetPriceProduct 
        :key="key" 
        v-model="tableData" 
        :currency="currency.code" 
        :form="form"
        :disable-exist="true" 
    />

    <small v-if="form.errors.shop_products" class="text-red-500 flex items-center gap-1">
        {{ form.errors.shop_products.join(", ") }}
    </small>

    <div class="sticky bottom-0 z-10 pt-4 pb-2 flex items-end w-full bg-white border-t border-gray-200">
        <Button :class="'ms-auto'" type="save" :disabled="disableClone" v-on:click="submitForm()" :loading="loading" :label="trans('save')">
        </Button>
    </div>
</template>

<style scoped>

</style>
