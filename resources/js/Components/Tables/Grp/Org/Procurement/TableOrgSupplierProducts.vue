<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 11 Aug 2024 10:11:50 Central Indonesia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { OrgSupplierProduct } from "@/types/org-supplier-product"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { computed, inject, ref } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBarcodeRead, faLink } from "@fal"
import CompletenessBar from "@/Components/Procurement/CompletenessBar.vue"
import SupplierProductLabelsDialog from "@/Components/SupplyChain/SupplierProductLabelsDialog.vue"
import SupplierProductsCopyActions from "@/Components/Procurement/SupplierProductsCopyActions.vue"
import SupplierProductStateSelect from "@/Components/SupplyChain/SupplierProductStateSelect.vue"
import SupplierProductsBulkStateButton from "@/Components/SupplyChain/SupplierProductsBulkStateButton.vue"
import Icon from "@/Components/Icon.vue"
import { SupplierProductStateOption, SupplierProductStateRow } from "@/Composables/useSupplierProductState"
import { CopyProducts, CopyTarget } from "@/types/supplier-products-copy"

library.add(faBarcodeRead, faLink)

const locale = inject("locale", aikuLocaleStructure)

const props = defineProps<{
  data: { data?: (SupplierProductStateRow & { supplier_product_id: number | null })[] }
  tab?: string
  copyProducts?: CopyProducts | null
  copyTargets?: CopyTarget[]
  labelsPrint?: {
    plan_route: routeType
    pdf_route: routeType
    ids_parameter: string
  }
  linkGroupSkoRoute?: routeType | null
  stateOptions?: SupplierProductStateOption[] | null
}>()

const _table = ref<InstanceType<typeof Table> | null>(null)
const selectedRows = ref<Record<string, boolean>>({})
const selectedIds = computed(() => Object.keys(selectedRows.value).filter((id) => selectedRows.value[id]).map(Number))

const isLabelsDialogOpen = ref(false)

function reloadData() {
  router.reload({ only: [props.tab ?? "index"] })
}

const linkingId = ref<number | null>(null)
const linkErrors = ref<Record<number, string>>({})

function linkGroupSko(supplierProduct: { id: number }) {
  if (!props.linkGroupSkoRoute) {
    return
  }

  router.post(
    route(props.linkGroupSkoRoute.name, { ...props.linkGroupSkoRoute.parameters, orgSupplierProduct: supplierProduct.id }),
    {},
    {
      preserveScroll: true,
      onStart: () => {
        linkingId.value = supplierProduct.id
        delete linkErrors.value[supplierProduct.id]
      },
      onFinish: () => linkingId.value = null,
      onError: (errors) => linkErrors.value[supplierProduct.id] = Object.values(errors)[0] as string,
    }
  )
}

const routeParams = route().params
const currentRouteName = route().current()

function addToShoppingList(supplierProduct: OrgSupplierProduct & { units_per_carton?: number }) {
  router.post(
    route("grp.org.procurement.shopping_list.store", [routeParams["organisation"], supplierProduct.slug]),
    { quantity_units: supplierProduct.units_per_carton ?? 1 },
    { preserveScroll: true }
  )
}

function supplierProductRoute(supplierProduct: OrgSupplierProduct) {
  switch (currentRouteName) {
    case "grp.org.procurement.org_agents.show.supplier_products.index":
      return route(
        "grp.org.procurement.org_agents.show.supplier_products.show",
        [routeParams["organisation"], routeParams["orgAgent"], supplierProduct.slug])

    case "grp.org.agent.org_supplier_products.index":
      return route(
        "grp.org.agent.org_supplier_products.show",
        [routeParams["organisation"], supplierProduct.slug])
    case "grp.org.procurement.org_suppliers.show":
    case "grp.org.procurement.org_suppliers.show.supplier_products.index":
      return route(
        "grp.org.procurement.org_suppliers.show.supplier_products.show",
        [routeParams["organisation"], (routeParams["orgSupplier"] ?? routeParams["supplier"]), supplierProduct.slug])

    default:
      return route(
        "grp.org.procurement.org_supplier_products.show",
        [routeParams["organisation"], supplierProduct.slug])
  }
}

function orgSupplierRoute(orgSupplierSlug: string) {
  return route("grp.org.procurement.org_suppliers.show", [routeParams["organisation"], orgSupplierSlug])
}
</script>

<template>
  <div>
  <div v-if="copyProducts || labelsPrint" class="mx-4 mt-4 flex flex-wrap items-center gap-2">
    <span class="text-sm text-gray-600 tabular-nums">{{ ctrans(":count selected", { count: String(selectedIds.length) }) }}</span>
    <Button v-if="labelsPrint" type="tertiary" :label="ctrans('Print barcodes')" :disabled="!selectedIds.length" @click="isLabelsDialogOpen = true">
      <template #icon>
        <FontAwesomeIcon icon="fal fa-barcode-read" class="text-green-600" fixed-width aria-hidden="true" />
      </template>
    </Button>
    <SupplierProductsCopyActions
      v-if="copyProducts"
      :copyProducts="copyProducts"
      :copyTargets="copyTargets"
      :selectedIds="selectedIds"
      idsParameter="org_supplier_products"
      @copied="_table?.clearSelection()"
    />
    <SupplierProductsBulkStateButton
      v-if="stateOptions?.length"
      :selectedIds="selectedIds"
      idsParameter="org_supplier_products"
      :rows="data?.data ?? []"
      :stateOptions="stateOptions"
      @saved="reloadData"
    />
  </div>
  <Table
    ref="_table"
    :resource="data"
    :name="tab"
    class="mt-5"
    :isCheckBox="Boolean(copyProducts || labelsPrint)"
    @onSelectRow="(rows: Record<string, boolean>) => selectedRows = { ...rows }"
  >
    <template #cell(code)="{ item: supplier_product }">
      <Link :href="supplierProductRoute(supplier_product)" class="primaryLink">
        {{ supplier_product["code"] }}
      </Link>
    </template>
    <template #cell(supplier_name)="{ item: supplier_product }">
      <Link
        v-if="supplier_product.org_supplier_slug"
        :href="orgSupplierRoute(supplier_product.org_supplier_slug)"
        class="secondaryLink">
        {{ supplier_product.supplier_name }}
      </Link>
      <span v-else>{{ supplier_product.supplier_name }}</span>
    </template>
    <template #cell(state)="{ item: supplier_product }">
      <SupplierProductStateSelect
        v-if="stateOptions?.length && supplier_product.supplier_product_id"
        :supplierProductId="supplier_product.supplier_product_id"
        :code="supplier_product.code"
        :state="supplier_product.state"
        :stateOptions="stateOptions"
        @saved="reloadData"
      />
      <span v-else-if="supplier_product.state" class="inline-flex items-center gap-x-1.5 whitespace-nowrap">
        <Icon :data="supplier_product.state.icon" :title="supplier_product.state.label" />
        {{ supplier_product.state.label }}
      </span>
    </template>
    <template #cell(internal_code)="{ item: supplier_product }">
      <Link v-if="supplier_product.internal_code" :href="supplierProductRoute(supplier_product)" class="primaryLink">
        {{ supplier_product.internal_code }}
      </Link>
      <span v-else class="inline-flex items-center gap-2">
        <span class="italic text-gray-400">{{ ctrans("No SKO") }}</span>
        <Button
          v-if="linkGroupSkoRoute && supplier_product.group_sko_code"
          v-tooltip="ctrans('Linked to :sko at group level, not yet in this organisation', { sko: supplier_product.group_sko_code })"
          type="tertiary"
          size="xxs"
          :label="ctrans('Link to :sko', { sko: supplier_product.group_sko_code })"
          :loading="linkingId === supplier_product.id"
          @click="linkGroupSko(supplier_product)"
        >
          <template #icon>
            <FontAwesomeIcon icon="fal fa-link" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
          </template>
        </Button>
      </span>
      <p v-if="linkErrors[supplier_product.id]" class="text-xs text-red-600">{{ linkErrors[supplier_product.id] }}</p>
      <span v-if="supplier_product.availability_status && supplier_product.availability_status !== 'available'"
        class="ml-1.5 whitespace-nowrap rounded px-1 py-0.5 text-[11px]"
        :class="supplier_product.availability_status === 'limited' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700'">
        {{ supplier_product.availability_status === "limited" ? ctrans("Limited") : ctrans("Not available") }}
      </span>
    </template>
    <template v-for="key in ['cbm', 'lead_time_days', 'moq']" :key="key" #[`cell(${key})`]="{ item: supplier_product }">
      <span v-if="supplier_product[key] !== null && supplier_product[key] !== undefined">{{ supplier_product[key] }}</span>
      <span v-else class="text-gray-300">-</span>
    </template>
    <template #cell(completeness)="{ item: supplier_product }">
      <CompletenessBar :value="supplier_product.completeness" :checks="supplier_product.completeness_checks" />
    </template>
    <template #cell(group_cost)="{ item: supplier_product }">
      <span v-if="supplier_product.group_cost !== null" class="tabular-nums">{{ locale.currencyFormat(supplier_product.group_currency_code, supplier_product.group_cost) }}</span>
      <span v-else class="text-gray-300">-</span>
    </template>
    <template #cell(add)="{ item: supplier_product }">
      <button type="button" class="secondaryLink" @click="addToShoppingList(supplier_product)">
        {{ ctrans("Add to shopping list") }}
      </button>
    </template>
  </Table>

  <SupplierProductLabelsDialog
    v-if="labelsPrint"
    v-model:visible="isLabelsDialogOpen"
    :selectedIds="selectedIds"
    :planRoute="labelsPrint.plan_route"
    :pdfRoute="labelsPrint.pdf_route"
    :idsParameter="labelsPrint.ids_parameter"
  />
  </div>
</template>
