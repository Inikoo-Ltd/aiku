<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Updated & Optimized by ChatGPT
-->

<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3"
import { computed, ref, watch } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableMasterProducts from "@/Components/Tables/Grp/Goods/TableMasterProducts.vue"
import { capitalize } from "@/Composables/capitalize"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from '@/Components/Utils/Modal.vue'
import { faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome, faPlus, faPencil, faMinus } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import FormCreateMasterProduct from "@/Components/FormCreateMasterProduct.vue"
import { trans } from "laravel-vue-i18n"
import { notify } from '@kyvg/vue3-notification'
import Dialog from "primevue/dialog"
import InputNumber from "primevue/inputnumber"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PureInput from "@/Components/Pure/PureInput.vue"
import { router } from "@inertiajs/vue3"
import { useTabChange } from '@/Composables/tab-change'
import Tabs from "@/Components/Navigation/Tabs.vue"
import ListSelector from "@/Components/DepartmentAndFamily/ListSelector.vue"
import MasterFamilySetOrderingPositionOfProduct from "@/Components/Master/FamilySetOrderingPositionOfProduct.vue"


library.add(faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome, faPlus)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: {}
    }
    index?: {}
    index_ordering?: {}
    sales?: {}
    data: {}
    routes?: {
        master_families_route: routeType
        submit_orphan_route: routeType
    }
    familyId: number
    storeProductRoute: routeType
    shopsData?: any
    masterProductCategoryId?: number
    currency?: any
    variantSlugs?: Record<string, string>
    hide_bulk_edit?: boolean
}>()

const currentTab = ref<string>(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: any = {
        index: TableMasterProducts,
        index_ordering: MasterFamilySetOrderingPositionOfProduct,
        sales: TableMasterProducts,
    }

    return components[currentTab.value]
})

// dialog state
const showDialog = ref(false)
// Selected products logic
const selectedProductsId = ref<Record<string, boolean>>({})
// Selected products to link to a collection
const selectedProductsForAttachId = ref<number[]>([])

const isLoading = ref<string | boolean>(false)
const errorMessage = ref<string>('')
const localData = ref<any>(props.data)

const isModalOpen = {
    products: ref(false),
}

const compSelectedProductsId = computed(() =>
    Object.keys(selectedProductsId.value).filter(key => selectedProductsId.value[key])
)

const isLoadingVisit = ref(false)
const onVisit = () => {
    router.visit(route('grp.masters.master_shops.show.bulk-edit', {
        masterShop: route().params['masterShop'],
        id: compSelectedProductsId.value,
        from: window.location.href
    }), {
        onStart: () => {
            isLoadingVisit.value = true
        },
        onFinish: () => {
            isLoadingVisit.value = false
        },
    })
}

const onSubmitAttach = async ({
    closeModal,
    scope,
    routeToSubmit,
    selectedIds,
    resetSelection
}: {
    closeModal: () => void,
    scope: 'products' | 'families',
    routeToSubmit: routeType,
    selectedIds: any[],
    resetSelection: () => void
}) => {

    const ids = selectedIds.map(item => typeof item === 'object' ? item.id : item)

    if (!ids.length) return
    isLoading.value = 'submitAttach'

    router.post(route(routeToSubmit.name, routeToSubmit.parameters), {
        [scope]: ids
    }, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal()
            notify({
                title: trans('Success'),
                text: trans(`Successfully attach :tscope.`, { tscope: scope }),
                type: 'success',
            })
            resetSelection()
        },
        onError: (errors: any) => {
            errorMessage.value = errors
            notify({
                title: trans('Something went wrong.'),
                text: trans(`Failed to attach :tscope, please try again.`, { tscope: scope }),
                type: 'error',
            })
        },
        onFinish: () => {
            isLoading.value = false
        }
    })
}

const resetSelectionByScope = {
    products: () => (selectedProductsForAttachId.value = []),
}

const loadingOrder = ref(false)
const SaveOrder = () => {
    const products = localData.value?.data || localData.value || []

    router.patch(
        route('grp.models.master_product_category.reorder_index', {
            masterProductCategory: props.familyId
        }),
        {
            products: products.map((product: any, index: number) => ({
                id: product.id,
                code: product.code,
                index_under_master_family:
                    product.index_under_family ?? index,
            }))
        },
        {
            preserveScroll: true,

            onStart: () => {
                loadingOrder.value = true
            },

            onSuccess: () => {
                notify({
                    title: trans("Success!"),
                    text: trans("Successfully reordered the products"),
                    type: "success"
                })
            },

            onError: (errors: any) => {
                console.log(errors)

                notify({
                    title: trans("Something went wrong"),
                    text:
                        errors?.message ||
                        trans("Failed to reorder products"),
                    type: "error"
                })
            },

            onFinish: () => {
                loadingOrder.value = false
            }
        }
    )
}

watch(() => currentTab.value, (tab) => {
    if (tab === 'index' || tab === 'index_ordering' || tab === 'sales') {
        localData.value = (props as any)[tab] || props.data
    } else {
        localData.value = props.data
    }
}, { immediate: true })

</script>

<template>
    <!-- Page Title -->
    <Head :title="capitalize(title)" />

    <!-- Page Heading with slot button -->
    <PageHeading :data="pageHead">
        <template #button-add-family="{ action }">
            <Button
                v-if="currentTab === 'index'"
                :icon="action.icon"
                :label="action.label" @click="showDialog = true"
                :style="action.style"
            />
            <span v-else />
        </template>

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

        <template #other>
            <Button
                v-if="!hide_bulk_edit"
                @click="() => onVisit()"
                :label="trans('Bulk edit products') + ` (${compSelectedProductsId?.length})`"
                :disabled="!compSelectedProductsId.length"
                type="secondary"
                icon="fal fa-pencil"
                :loading="isLoadingVisit"
            />
            <div v-if="routes?.dataList">
                <Button
                    type="secondary"
                    label="Attach Products"
                    icon="fal fa-plus"
                    @click="isModalOpen.products.value = true"
                    :tooltip="trans('Attach products to this collections')"
                />
                <!-- Modal: Product -->
                <Modal
                    :isOpen="isModalOpen.products.value"
                    @onClose="isModalOpen.products.value = false"
                    width="w-full max-w-6xl"
                >
                    <ListSelector
                        :headLabel="`${trans('Add products to collection')}`"
                        :routeFetch="routes.dataList"
                        :isLoadingSubmit="isLoading"
                        @submit="(ids) =>
                            onSubmitAttach({
                                closeModal: () => (isModalOpen.products.value = false),
                                scope: 'products',
                                routeToSubmit: routes.submitAttach,
                                selectedIds: ids,
                                resetSelection: resetSelectionByScope.products,
                            })
                        "
                    />
                </Modal>
            </div>
        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <!-- Products Table -->
    <component
        :is="component"
        :key="currentTab"
        :tab="currentTab"
        :data="currentTab == 'index_ordering' ?  localData : props[currentTab]"
        :variant-slugs="variantSlugs"
        :isCheckBox="!hide_bulk_edit"
        :routes="routes"
        @selectedRow="(productsId: Record<string, boolean>) => selectedProductsId = productsId"
        @update:data="(updatedData) => localData = updatedData"
    ></component>

    <!-- Dialog Create Product -->
    <FormCreateMasterProduct
        :showDialog="showDialog"
        :storeProductRoute="storeProductRoute"
        @update:show-dialog="(value) => showDialog = value" :shopsData="shopsData"
        :masterProductCategoryId="masterProductCategoryId"
        :is_dropship="route().params['masterShop'] == 'ds'"
    />

    <Dialog :header="trans('Edit Selected Products')" v-model:visible="isOpenModalEditProducts" :modal="true"
        :closable="true" :style="{ width: '500px' }">
        <div class="px-2 space-y-4">
            <!-- Form fields -->
            <form class="space-y-3">
                <!-- Grid for Price & RRP -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm" for="price">Price</label>
                        <InputNumber v-model="form.price" mode="currency" :currency="currency" :step="0.25" showButtons
                            button-layout="horizontal" inputClass="w-full text-xs">
                            <template #incrementbuttonicon>
                                <FontAwesomeIcon :icon="faPlus" />
                            </template>
                            <template #decrementbuttonicon>
                                <FontAwesomeIcon :icon="faMinus" />
                            </template>
                        </InputNumber>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-sm" for="rrp">RRP</label>
                        <InputNumber v-model="form.rrp" mode="currency" :currency="currency" :step="0.25" showButtons
                            button-layout="horizontal" inputClass="w-full text-xs">
                            <template #incrementbuttonicon>
                                <FontAwesomeIcon :icon="faPlus" />
                            </template>
                            <template #decrementbuttonicon>
                                <FontAwesomeIcon :icon="faMinus" />
                            </template>
                        </InputNumber>
                    </div>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-sm" for="unit">Unit</label>
                    <PureInput v-model="form.unit" />
                </div>

                <div class="flex justify-end gap-2 mt-4">
                    <Button type="tertiary" label="Cancel" @click="isOpenModalEditProducts = false" />
                    <Button type="save" @click="onSaveEditBulkProduct" :loading="loadingSave"/>
                </div>
            </form>
        </div>
    </Dialog>

</template>

<style>

</style>
