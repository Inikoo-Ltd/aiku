<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Button from '@/Components/Elements/Buttons/Button.vue'
import { capitalize } from "@/Composables/capitalize"
import { useTabChange } from "@/Composables/tab-change"
import { computed, reactive, ref, watch } from "vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from '@/types/route'
import { faTools } from '@fal'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import PureInput from '@/Components/Pure/PureInput.vue'
import { ctrans } from '@/Composables/useTrans'
import axios from 'axios'
import { ulid } from 'ulid'
import AttachmentManagement from '@/Components/Goods/AttachmentManagement.vue'
import { faSave as fadSave } from '@fad'
import { faSave as falSave, faInfoCircle } from '@fal'
import { faAsterisk, faQuestion } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faWarning } from '@fortawesome/free-solid-svg-icons'
import SetOrderingPositionOfProduct from "@/Components/Master/SetOrderingPositionOfProduct.vue";
import { notify } from '@kyvg/vue3-notification'
import ModalCreateStepDiscountProduct from '@/Components/Offers/ModalCreateStepDiscountProduct.vue'
import { faCube, faPlay } from "@fal"

library.add(fadSave, faQuestion, falSave, faInfoCircle, faAsterisk, faTools, faCube)

const props = defineProps<{
    pageHead: PageHeadingTypes
    editable_table: boolean
    title: string
    currencies?: any
    familyId?: number
    tabs: {
        current: string
        navigation: Record<string, string>
    },
    data: Record<string, any>
    index?: Record<string, any>
    index_ordering?: Record<string, any>
    edit?: Record<string, any>
    bulk_unit?: Record<string, any>
    sales?: Record<string, any>
    routes?: {
        families_route: routeType
        submit_route: routeType
    }
    is_orphan_products?: boolean
    attachments?: Record<string, any>
    shop_id?: number
    variantSlugs?: Record<string, string>;
    mismatch_trade_unit_with_master?: boolean
    hide_sku_in_name_column?: boolean
    products_export?: {
        fields: { key: string; label: string }[]
        download_route: { xlsx: routeType; csv: routeType }
    }
    bulk_set_active_route?: routeType | null
    can_edit?: boolean
    step_discount_shop_data?: {
        id: number
        slug: string
        organisation: string
        currency_code: string
        default_dates: {
            start: string
            end: string
        }
    } | null
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => {
    selectedProductsId.clear()
    useTabChange(tabSlug, currentTab)
}
const key = ref(ulid())

/* Bulk form fields */
const form = ref({
    unit: ''
})

const localData = ref({ ...props })
const formProcessing = ref({ unit: false })

/* Component mapping */
const component = computed(() => {
    const mapping: Record<string, any> = {
        index: TableProducts,
        sales: TableProducts,
        edit: TableProducts,
        bulk_unit: TableProducts,
        attachments: AttachmentManagement,
        index_ordering : SetOrderingPositionOfProduct
    }
    return mapping[currentTab.value]
})

const selectedProductsId = reactive(new Set<number>())
const compSelectedProductsId = computed(() => [...selectedProductsId])

const isStepDiscountAvailable = computed(() => currentTab.value === 'index' && !!props.step_discount_shop_data)

const loadedProductsById = reactive(new Map<number, Record<string, any>>())
watch(
    () => props.index?.data,
    (products) => {
        products?.forEach((product: Record<string, any>) => loadedProductsById.set(product.id, product))
    },
    { immediate: true }
)

const selectedProducts = computed(() =>
    compSelectedProductsId.value
        .map((productId) => loadedProductsById.get(productId))
        .filter((product) => !!product)
)

const isBulkSetActiveAvailable = computed(() => currentTab.value === 'index' && !!props.bulk_set_active_route)
const isSettingProductsActive = ref(false)

const setSelectedProductsActive = () => {
    if (!props.bulk_set_active_route) {
        return
    }

    const productsToActivate = compSelectedProductsId.value

    router.patch(
        route(props.bulk_set_active_route.name, props.bulk_set_active_route.parameters),
        { products: productsToActivate },
        {
            preserveScroll: true,
            onStart: () => isSettingProductsActive.value = true,
            onSuccess: () => {
                selectedProductsId.clear()
                notify({
                    title: ctrans("Success!"),
                    text: ctrans(":count products set as active", { count: String(productsToActivate.length) }),
                    type: "success"
                })
            },
            onError: (errors) => {
                notify({
                    title: ctrans("Something went wrong"),
                    text: Object.values(errors)[0] || ctrans("Failed to set products as active"),
                    type: "error"
                })
            },
            onFinish: () => isSettingProductsActive.value = false
        }
    )
}

const loadingField = ref<string | null>(null)
const rowErrors = ref<Record<string, any>>({})


const onSaveEditBulkProduct = async (field: string, value: any) => {
    formProcessing.value[field] = true
    loadingField.value = field
    rowErrors.value = {}

    try {
        const payload = compSelectedProductsId.value.map(productId => ({
            id: productId,
            [field]: value,
        }))

        await router.patch(
            route("grp.models.product.bulk_update", { shop: props.shop_id }),
            { products: payload },
            {
                preserveScroll: true,
                onError: (errors) => (rowErrors.value = errors),
                onSuccess: () => {
                    key.value = ulid()
                    selectedProductsId.clear()
                }
            }
        )
    } catch (err) {
        console.error("Bulk edit failed:", err)
    } finally {
        loadingField.value = null
        formProcessing.value[field] = false
        key.value = ulid()
    }
}


const saveUnit = () => onSaveEditBulkProduct("unit", form.value.unit)


const repairTradeUnitToChildren = async () => {
    await axios.patch(route('grp.models.master_asset.repair_mismatch_trade_units', {
        masterAsset: props.masterAsset.id,
    })).then((response) => {
        notify({
            title: ctrans("Success!"),
            text: ctrans("Successfully repaired the product details"),
            type: "success"
        })
        router.visit(route('grp.masters.master_shops.show.master_products.show', route().params))
    }).catch((errors) => {
        notify({
            title: ctrans("Something went wrong"),
            text: errors.message || ctrans("Failed to update product quantity in basket"),
            type: "error"
        })
    })
}
const loadingOrder = ref(false)
const SaveOrder = () => {
    // console.log(localData.value.index_ordering);
    const products = localData.value?.index_ordering || localData.value?.index_ordering?.data || []

    router.patch(route('grp.models.product_category.reorder_index', {
        productCategory: props.familyId
    }), {
        products: products.map((product: any, index: number) => ({
            id: product.id,
            code : product.code,
            index_under_family: product.index_under_family || index,
        }))
    }, {
        preserveScroll: true,
        onStart : () => {
            loadingOrder.value = true
        },
        onSuccess: () => {
            notify({
                title: ctrans("Success!"),
                text: ctrans("Successfully reordered the products"),
                type: "success"
            })
        },
        onError: (errors) => {
            // console.log(errors);
            notify({
                title: ctrans("Something went wrong"),
                text: errors.message || ctrans("Failed to reorder products"),
                type: "error"
            })
        },
        onFinish : () => {
            loadingOrder.value = false
        }
    })
}

watch(
    () => props,
    (newVal) => {
        localData.value = { ...newVal }
    },
    { deep: true }
)

const replaceProps = (updatedData) => {
    localData.value[currentTab.value] = updatedData
}

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
           <template #button-save-order="{ action }">
            <Button
                v-if="currentTab === 'index_ordering'"
                :icon="action.icon"
                :label="action.label"
                :style="action.style"
                :onClick="SaveOrder"
                :loading="loadingOrder"
            />
            <span v-else />
        </template>

        <template #button-create="{ action }">
            <div  v-if="currentTab === 'index_ordering'"></div>
        </template>

         <template #button-repair-image="{ action }">
            <div  v-if="currentTab === 'index_ordering'"></div>
        </template>


        <template #afterTitle2>
            <FontAwesomeIcon
                v-if="mismatch_trade_unit_with_master"
                :icon="faWarning"
                class="text-red-500"
                v-tooltip="ctrans('One or more product under this master has mismatched trade units data. Please fix it by modifying the master products trade units')" fixed-width
            />
        </template>

        <template #button-repair-mismatch="{ action }">
            <Button v-if="mismatch_trade_unit_with_master && currentTab !== 'index_ordering'" :icon="faTools" :label="ctrans('Repair trade units')" v-tooltip="ctrans('Will force child to follow master products trade units')" @click="repairTradeUnitToChildren()" :style="'warning'" />
        </template>
        <template #otherBefore>
            <Button
                v-if="isBulkSetActiveAvailable"
                :icon="faPlay"
                :label="ctrans('Set :count active', { count: String(compSelectedProductsId.length) })"
                type="secondary"
                :disabled="compSelectedProductsId.length === 0"
                :loading="isSettingProductsActive"
                v-tooltip="compSelectedProductsId.length === 0 ? ctrans('Select products with the checkboxes first') : ''"
                @click="setSelectedProductsActive"
            />
            <div
                v-if="isStepDiscountAvailable"
                v-tooltip="compSelectedProductsId.length === 0 ? ctrans('Select products with the checkboxes first') : ''"
            >
                <ModalCreateStepDiscountProduct
                    :shop_data="step_discount_shop_data"
                    :selected_products="selectedProducts"
                    :disabled="compSelectedProductsId.length === 0"
                    @success="selectedProductsId.clear()"
                    @removeProduct="(productId) => selectedProductsId.delete(productId)"
                />
            </div>
        </template>
    </PageHeading>

    <Tabs
        :current="currentTab"
        :navigation="props.tabs.navigation"
        @update:tab="handleTabUpdate"
    />

    <div
        v-if="currentTab === 'bulk_unit'"
        class="mt-4 mx-4 flex items-center gap-2"
    >
        <span class="text-sm">{{ ctrans('Unit label') }}</span>
        <div class="w-48">
            <PureInput v-model="form.unit" :placeholder="ctrans('e.g. piece')" />
        </div>
        <Button
            :label="ctrans('Apply to :count selected', { count: String(compSelectedProductsId.length) })"
            type="secondary"
            :disabled="!form.unit || compSelectedProductsId.length === 0"
            :loading="formProcessing.unit"
            v-tooltip="compSelectedProductsId.length === 0 ? ctrans('Select products with the checkboxes first') : ''"
            @click="saveUnit"
        />
    </div>

    <component
        :is="component"
        :key="currentTab + key"
        :tab="currentTab"
        :data="localData[currentTab]"
        v-bind="currentTab === 'index_ordering' ? { pasteLookupRoute: { name: 'grp.json.product_category.products_by_codes', parameters: { productCategory: familyId } }, disabled: can_edit === false } : {}"
        :isCheckboxProducts="currentTab === 'bulk_unit' || isStepDiscountAvailable || isBulkSetActiveAvailable"
        :selectedProductsId="selectedProductsId"
        :variantSlugs="variantSlugs"
        :productsExport="currentTab === 'index' ? products_export : undefined"
        :mismatch_trade_unit_with_master="mismatch_trade_unit_with_master"
        :hide_sku_in_name_column="hide_sku_in_name_column"
        @update:data="(updatedData) => replaceProps(updatedData)"
        :editable_table="currentTab === 'edit'"
    />
</template>
