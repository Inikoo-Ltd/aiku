<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Updated & Optimized by ChatGPT
-->

<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableMasterProducts from "@/Components/Tables/Grp/Goods/TableMasterProducts.vue";
import { capitalize } from "@/Composables/capitalize";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome, faPlus, faPencil, faMinus  } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import { routeType } from "@/types/route";
import FormCreateMasterProduct from "@/Components/FormCreateMasterProduct.vue";
import { ulid } from "ulid";
import { trans } from "laravel-vue-i18n";
import Dialog from "primevue/dialog";
import InputNumber from "primevue/inputnumber";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import PureInput from "@/Components/Pure/PureInput.vue";
import { router } from "@inertiajs/vue3";


library.add(faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome, faPlus);

const props = defineProps<{
  pageHead: PageHeadingTypes;
  title: string;
  data: {};
  familyId: number;
  storeProductRoute: routeType
  shopsData?: any
  masterProductCategory?: number
  editable_table?: boolean
  currency?: any
}>();

// dialog state
const showDialog = ref(false);
const isOpenModalEditProducts = ref(false)
// Selected products logic
const selectedProductsId = ref<Record<string, boolean>>({})
const compSelectedProductsId = computed(() =>
  Object.keys(selectedProductsId.value).filter(key => selectedProductsId.value[key])
)

const form = useForm({
  rrp: 0,
  price: 0,
  unit: ''
})


const loadingSave = ref(false)
const rowErrors = ref<Record<string, any>>({})
const key = ref(ulid())

const onSaveEditBulkProduct = async () => {
    loadingSave.value = true
    rowErrors.value = {} // reset

    try {
        // Payload sekali request
        const payload = []
        compSelectedProductsId.value.forEach((productId) => {
            payload.push({
                price: form.price,
                rrp: form.rrp,
                unit: form.unit,
                id: productId
            }) 
        })


        await router.patch(
            route("grp.models.master_asset.bulk_update"),
            {products : payload},
            {
                preserveScroll: true,
                onError: (errors) => {
                    rowErrors.value = errors
                },
                onSuccess: () => {
                    isOpenModalEditProducts.value = false
                    key.value = ulid()
                },
            }
        )
    } catch (error) {
        console.error("Unexpected bulk save failure", error)
    } finally {
        loadingSave.value = false
    }
}


</script>

<template>
  <!-- Page Title -->
  <Head :title="capitalize(title)" />

  <!-- Page Heading with slot button -->
  <PageHeading :data="pageHead">
    <template #button-master-product="{ action }">
      <Button :icon="action.icon" :label="action.label" @click="showDialog = true" :style="action.style" />
    </template>
    <template #other>
      <Button v-if="compSelectedProductsId.length > 0" @click="() => isOpenModalEditProducts = true" type="tertiary"
        :icon="faPencil" label="Edit Products" />
    </template>
  </PageHeading>

  <!-- Products Table -->
  <TableMasterProducts :data="data" :editable_table :key="key"
    @selectedRow="(productsId: Record<string, boolean>) => selectedProductsId = productsId" />

  <!-- Dialog Create Product -->
  <FormCreateMasterProduct :showDialog="showDialog" :storeProductRoute="storeProductRoute"
    @update:show-dialog="(value) => showDialog = value" :shopsData="shopsData"
    :masterProductCategory="masterProductCategory" />


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

<style></style>
