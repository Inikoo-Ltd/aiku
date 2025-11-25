<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { remove as loRemove, cloneDeep} from "lodash-es"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { onMounted, onUnmounted, ref, inject, shallowRef  } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { RouteParams } from "@/types/route-params"
import InputNumber from "primevue/inputnumber"
import { faPlus } from "@far"
import { faXmark } from "@fortawesome/free-solid-svg-icons"
import PureInput from "@/Components/Pure/PureInput.vue"
import { faMinus } from "@fal"
import { trans } from "laravel-vue-i18n"
import ProductUnitLabel from "@/Components/Utils/Label/ProductUnitLabel.vue"

defineProps<{
    data: {}
    tab?: string
    editable_table?: boolean
}>()

const emits = defineEmits<{
    (e: "selectedRow", value: {}): void
}>()

function masterFamilyRoute(masterProduct: MasterProduct) {
    if (route().current() == "grp.masters.master_products.index") {
        return route(
            "grp.masters.master_families.show",
            {masterFamily: masterProduct.master_family_slug})
    } else {
        return route(
            "grp.masters.master_shops.show.master_families.show",
            {masterShop: (route().params as RouteParams).masterShop, masterFamily: masterProduct.master_family_slug})
    }
}

function masterProductRoute(masterProduct: MasterProduct) {
    if (route().current() == "grp.masters.master_products.index") {
        return route(
            "grp.masters.master_products.show",
            {
                masterProduct: masterProduct.slug
            })
    } else if (route().current() == "grp.masters.master_departments.show.master_families.show.master_products.index") {
        return route(
            "grp.masters.master_departments.show.master_families.show.master_products.show",
            {
                masterDepartment: (route().params as RouteParams).masterDepartment,
                masterFamily: (route().params as RouteParams).masterFamily,
                masterProduct: masterProduct.slug
            }
        )
    } else if (route().current() == "grp.masters.master_shops.show.master_departments.show.master_products.index") {
        return route(
            "grp.masters.master_shops.show.master_departments.show.master_products.show",
            {
                masterShop: (route().params as RouteParams).masterShop,
                masterDepartment: (route().params as RouteParams).masterDepartment,
                masterProduct: masterProduct.slug
            }
        )
    } else if (route().current() == "grp.masters.master_shops.show.master_families.master_products.index") {
        return route(
            "grp.masters.master_shops.show.master_families.master_products.show",
            {
                masterShop: (route().params as RouteParams).masterShop,
                masterFamily: (route().params as RouteParams).masterFamily,
                masterProduct: masterProduct.slug
            }
        )
    } else {
        return route(
            "grp.masters.master_shops.show.master_products.show",
            {
                masterShop: (route().params as RouteParams).masterShop,
                masterProduct: masterProduct.slug
            })
    }
}

function masterDepartmentRoute(masterProduct: MasterProduct) {
    if (route().current() == "grp.masters.master_products.index") {
        return route(
            "grp.masters.master_departments.show",
            {masterDepartment: masterProduct.master_department_slug})
    } else {
        return route(
            "grp.masters.master_shops.show.master_departments.show",
            {
                masterShop: (route().params as RouteParams).masterShop,
                masterDepartment: masterProduct.master_department_slug
            })
    }
}

function masterShopRoute(masterProduct: MasterProduct) {
    return route("grp.masters.master_shops.show",
        {
            masterShop: masterProduct.master_shop_slug
        }
    )
}

const editingValues = shallowRef<Record<number, { price: number; rrp: number, unit : string }>>({})
const editingBackup = ref<Record<number, any>>({})
const onEditOpen = ref<number[]>([])
const loadingSave = ref([])

function onEdit(data) {
    const item = cloneDeep(data)
    // backup original values
    editingBackup.value[item.id] = { ...item }

    // make a working copy
    editingValues.value[item.id] = {
        price: item.price,
        rrp: item.rrp,
        unit: item.unit
    }

    if (!onEditOpen.value.includes(item.id)) {
        onEditOpen.value.push(item.id)
    }
}


function getMargin(item: ProductItem) {
    const p = Number(item.product?.price)
    const cost = Number(item.product?.org_cost)

    if (isNaN(p) || p === 0) return 0.000
    if (isNaN(cost) || cost === 0) return 100.000

    return Number((((p - cost) / p) * 100).toFixed(1))
}


onMounted(() => {
    if (typeof window !== "undefined") {
        document.addEventListener("keydown", (e) => e.keyCode == 27 ? onEditProduct.value = false : "")
    }
})

onUnmounted(() => {
    document.removeEventListener("keydown", () => false)
})

const locale = inject("locale", aikuLocaleStructure)

function onSave(item) {
    const updated = editingValues.value[item.id]

    if (!updated) return

    router.patch(
        route("grp.models.master_asset.update", { masterAsset: item.id }),
        {
            price: updated.price,
            rrp: updated.rrp,
            unit: updated.unit
        },
        {
            preserveScroll: true,
            onStart: () => {
                loadingSave.value.push(item.id)
            },
            onSuccess: () => {
                // merge back into original item so the table updates immediately
                Object.assign(item, updated)

                // cleanup
                loRemove(onEditOpen.value, (id) => id === item.id)
                delete editingBackup.value[item.id]
                delete editingValues.value[item.id]
            },
            onError: (errors) => {
                console.error("Save failed", errors)
            },
            onFinish: () => {
                loRemove(loadingSave.value, (id) => id === item.id)
            }
        }
    )
}

function onCancel(item) {
    if (editingBackup.value[item.id]) {
        Object.assign(item, editingBackup.value[item.id])
    }
    loRemove(onEditOpen.value, (id) => id === item.id)
    delete editingBackup.value[item.id]
    delete editingValues.value[item.id]
}


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" :isCheckBox="editable_table"
        @onSelectRow="(item) => emits('selectedRow', item)" key="product-table">

        <template #cell(master_shop_code)="{ item: masterProduct }">
            <Link v-tooltip="masterProduct.master_shop_name" :href="(masterShopRoute(masterProduct) as string)"
                  class="secondaryLink">
                {{ masterProduct["master_shop_code"] }}
            </Link>
        </template>

        <template #cell(master_department_code)="{ item: masterProduct }">
            <Link v-if="masterProduct.master_department_slug" v-tooltip="masterProduct.master_department_name"
                  :href="(masterDepartmentRoute(masterProduct) as string)" class="secondaryLink">
                {{ masterProduct["master_department_code"] }}
            </Link>
            <span v-else class="opacity-70  text-red-500">
        {{ trans("No department") }}
      </span>
        </template>

        <template #cell(master_family_code)="{ item: masterProduct }">
            <Link v-if="masterProduct.master_family_slug" v-tooltip="masterProduct.master_family_name"
                  :href="(masterFamilyRoute(masterProduct) as string)" class="secondaryLink">
                {{ masterProduct["master_family_code"] }}
            </Link>
            <span v-else class="opacity-70  text-red-500">
        {{ trans("No family") }}
      </span>
        </template>

        <template #cell(code)="{ item: masterProduct }">
            <Link v-if="masterProduct.code" v-tooltip="masterProduct.code"
                  :href="(masterProductRoute(masterProduct) as string)" class="secondaryLink">
                {{ masterProduct["code"] }}
            </Link>
        </template>
        <template #cell(name)="{ item: masterProduct }">
            <div>
                <ProductUnitLabel
                    v-if="masterProduct?.units"
                    :units="masterProduct?.units"
                    :unit="masterProduct?.unit"
                    class="!py-0 !px-1 !rounded-sm !text-sm mr-1"
                />

                {{ masterProduct["name"] }}

            </div>
        </template>

         <template #cell(unit)="{ item: product }"> 
               <!--  <PureInput v-if="onEditOpen.includes(product.id)" :key="product.id" v-model="editingValues[product.id].unit"></PureInput> -->
                <span >{{ product.unit }}</span>
        </template>

        <template #cell(price)="{ item: product }">
            <div>
                <InputNumber v-if="onEditOpen.includes(product.id)" v-model="editingValues[product.id].price"
                    mode="currency" :currency="product.currency_code" :step="0.25" showButtons
                    button-layout="horizontal" inputClass="w-full text-xs">
                    <template #incrementbuttonicon>
                        <FontAwesomeIcon :icon="faPlus" />
                    </template>
                    <template #decrementbuttonicon>
                        <FontAwesomeIcon :icon="faMinus" />
                    </template>
                </InputNumber>
                <span v-else>
                    {{ locale.currencyFormat(product.currency_code, product.price) }}
                </span>
            </div>
        </template>


        <template #cell(rrp)="{ item: product }">
            <div>
                <InputNumber v-if="onEditOpen.includes(product.id)" v-model="editingValues[product.id].rrp"
                    mode="currency" :currency="product.currency_code" :step="0.25" showButtons
                    button-layout="horizontal" inputClass="w-full text-xs">
                    <template #incrementbuttonicon>
                        <FontAwesomeIcon :icon="faPlus" />
                    </template>
                    <template #decrementbuttonicon>
                        <FontAwesomeIcon :icon="faMinus" />
                    </template>
                </InputNumber>

                <span v-else>{{ locale.currencyFormat(product.currency_code, product.rrp) }}</span>

            </div>

        </template>

        <template #cell(actions)="{ item: item}">
            <div v-if="editable_table">
                <button v-if="!onEditOpen.includes(item.id)" class="h-9 align-bottom text-center" @click="()=>onEdit(item)">
                    <FontAwesomeIcon icon="fal fa-pencil" class="h-5 text-gray-500 hover:text-gray-700"
                        aria-hidden="true" v-tooltip="'edit'" />
                </button>

                <span v-else class="flex items-center space-x-3">
                    <Button type="negative" v-tooltip="'cancel'" :icon="faXmark" @click="()=>onCancel(item)" size="sm">
                    </Button>

                    <button class="h-9 align-bottom text-center" :disabled="loadingSave.includes(item.id)"
                        @click="()=>onSave(item)" v-tooltip="'save'">
                        <FontAwesomeIcon v-if="loadingSave.includes(item.id)" icon="fad fa-spinner-third"
                            class="text-2xl animate-spin" fixed-width aria-hidden="true" />

                        <FontAwesomeIcon v-else-if="editingValues[item.id]" icon="fad fa-save" class="h-8"
                            :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" aria-hidden="true" />

                        <FontAwesomeIcon v-else icon="fal fa-save" class="h-8 text-gray-300" aria-hidden="true" />
                    </button>
                </span>
            </div>
        </template>

    </Table>
</template>


