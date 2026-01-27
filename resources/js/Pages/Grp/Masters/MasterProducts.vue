<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Updated & Optimized by ChatGPT
-->

<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3"
import { computed, ref } from "vue"
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
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { ulid } from "ulid"
import { trans } from "laravel-vue-i18n"
import { notify } from '@kyvg/vue3-notification'
import Dialog from "primevue/dialog"
import InputNumber from "primevue/inputnumber"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PureInput from "@/Components/Pure/PureInput.vue"
import { router } from "@inertiajs/vue3"
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import TableMasterProductsEdit from "@/Components/Tables/TableMasterProductsEdit.vue"
import { useTabChange } from '@/Composables/tab-change'
import Tabs from "@/Components/Navigation/Tabs.vue"

library.add(faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome, faPlus)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: {}
    }
    index?: {}
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
    variantSlugs?: Record<string, string>;
}>()

const currentTab = ref<string>(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const currentData = computed(() => {
    if (currentTab.value === 'index' || currentTab.value === 'sales') {
        return (props as any)[currentTab.value] || props.data
    }
    return props.data
})

const component = computed(() => {
    const components: any = {
        index: TableMasterProducts,
        sales: TableMasterProducts,
    }

    return components[currentTab.value]
})

// dialog state
const showDialog = ref(false)
// Selected products logic
const selectedProductsId = ref<Record<string, boolean>>({})
const compSelectedProductsId = computed(() =>
    Object.keys(selectedProductsId.value).filter(key => selectedProductsId.value[key])
)
console.log(compSelectedProductsId.value)


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
</script>

<template>
    <!-- Page Title -->
    <Head :title="capitalize(title)" />

    <!-- Page Heading with slot button -->
    <PageHeading :data="pageHead">
        <template #button-master-product="{ action }">
            <Button
                :icon="action.icon"
                :label="action.label" @click="showDialog = true"
                :style="action.style"
            />
        </template>

        <template #other>
            <Button
                @click="() => onVisit()"
                :label="trans('Bulk edit products') + ` (${compSelectedProductsId?.length})`"
                :disabled="!compSelectedProductsId.length"
                type="secondary"
                icon="fal fa-pencil"
                :loading="isLoadingVisit"
            />
        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <!-- Products Table -->
    <component
        :is="component"
        :key="currentTab"
        :tab="currentTab"
        :data="currentData"
        :variant-slugs="variantSlugs"
        isCheckBox
        @selectedRow="(productsId: Record<string, boolean>) => selectedProductsId = productsId"
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
