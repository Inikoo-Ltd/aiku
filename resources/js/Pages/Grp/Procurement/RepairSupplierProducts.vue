<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import Select from "primevue/select"
import { notify } from "@kyvg/vue3-notification"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PillFilterBar from "@/Components/Utils/PillFilterBar.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTools } from "@fal"

library.add(faTools)

const props = defineProps<{
  pageHead: object
  title: string
  data: object
  suppliers: { id: number, label: string }[]
  repairRoute: routeType
  filterOptions: Record<"state" | "purchase_orders", { value: string, label: string, count: number }[]>
  active: { state: string | null, purchase_orders: string | null }
}>()

function filterBy(key: "state" | "purchase_orders", value: string | null) {
  const query = Object.fromEntries(new URLSearchParams(window.location.search))
  delete query.page
  delete query.purchase_orders
  delete query.state

  router.get(window.location.pathname, {
    ...query,
    state: key === "state" ? (value ?? "all") : (props.active.state ?? "all"),
    ...((key === "purchase_orders" ? value : props.active.purchase_orders) ? { purchase_orders: key === "purchase_orders" ? value : props.active.purchase_orders } : {}),
  }, { preserveScroll: true })
}

interface PurchaseOrderLine {
  organisation: string
  organisation_slug: string
  reference: string
  slug: string
  state: string
  quantity: number | null
  created_at: string | null
  submitted_at: string | null
  confirmed_at: string | null
  estimated_received_at: string | null
}

const stateLabels: Record<string, string> = {
  in_process: ctrans("Draft"),
  submitted: ctrans("Submitted"),
  confirmed: ctrans("Accepted"),
}

function purchaseOrderDates(purchaseOrder: PurchaseOrderLine): string {
  return [
    [ctrans("created"), purchaseOrder.created_at],
    [ctrans("submitted"), purchaseOrder.submitted_at],
    [ctrans("confirmed"), purchaseOrder.confirmed_at],
    [ctrans("expected"), purchaseOrder.estimated_received_at],
  ]
    .filter(([, date]) => date)
    .map(([label, date]) => `${label} ${useFormatTime(date as string, { formatTime: "aiku" })}`)
    .join(" · ")
}

const table = ref<InstanceType<typeof Table> | null>(null)
const selectedRows = ref<Record<string, boolean>>({})
const selectedIds = computed(() => Object.keys(selectedRows.value).filter((id) => selectedRows.value[id]).map(Number))
const targetSupplierId = ref<number | null>(null)
const isRepairing = ref(false)
const repairError = ref<string | null>(null)

function repairSelectedProducts() {
  if (!targetSupplierId.value || !selectedIds.value.length) {
    return
  }

  router.post(
    route(props.repairRoute.name, [...Object.values(props.repairRoute.parameters ?? {}), targetSupplierId.value]),
    { supplier_product_ids: selectedIds.value },
    {
      preserveScroll: true,
      onStart: () => {
        isRepairing.value = true
        repairError.value = null
      },
      onFinish: () => isRepairing.value = false,
      onError: (errors) => repairError.value = Object.values(errors)[0] as string,
      onSuccess: () => {
        table.value?.clearSelection()
        selectedRows.value = {}
        notify({
          title: ctrans("Products repaired"),
          text: ctrans("They now belong to the supplier you chose"),
          type: "success",
        })
      },
    }
  )
}
</script>

<template>
  <div>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 mt-4 flex flex-wrap items-center gap-2">
      <PillFilterBar :label="ctrans('State')" :options="filterOptions.state" :selected="active.state" @select="(value) => filterBy('state', value)" />
      <PillFilterBar :label="ctrans('Purchase orders')" :options="filterOptions.purchase_orders" :selected="active.purchase_orders" @select="(value) => filterBy('purchase_orders', value)" />
    </div>

    <div class="mx-4 mt-4 flex flex-wrap items-center gap-3">
      <span class="text-sm text-gray-600">
        {{ ctrans("Tick the products, shift-click to tick a range, then choose their real supplier.") }}
      </span>
      <template v-if="selectedIds.length">
        <span class="text-sm font-medium tabular-nums text-gray-700">
          {{ ctrans(":count selected", { count: String(selectedIds.length) }) }}
        </span>
        <Select
          v-model="targetSupplierId"
          :options="suppliers"
          optionLabel="label"
          optionValue="id"
          filter
          :placeholder="ctrans('Real supplier')"
          :emptyMessage="ctrans('No other supplier')"
          class="w-72"
        />
        <Button
          type="tertiary"
          :label="ctrans('Repair')"
          :disabled="!targetSupplierId || isRepairing"
          :loading="isRepairing"
          @click="repairSelectedProducts"
        >
          <template #icon>
            <FontAwesomeIcon icon="fal fa-tools" fixed-width aria-hidden="true" />
          </template>
        </Button>
      </template>
      <p v-if="repairError" class="w-full text-sm text-red-500">{{ repairError }}</p>
    </div>

    <Table
      ref="table"
      :resource="data"
      class="mt-4"
      isCheckBox
      :disabledCheckbox="(item: { sent_purchase_orders: PurchaseOrderLine[] }) => item.sent_purchase_orders.length > 0"
      @onSelectRow="(rows: Record<string, boolean>) => selectedRows = { ...rows }"
    >
      <template #disable-checkbox />
      <template #cell(code)="{ item }">
        <span class="whitespace-nowrap">{{ item.code }}</span>
      </template>
      <template v-for="column in ['draft_purchase_orders', 'sent_purchase_orders']" :key="column" #[`cell(${column})`]="{ item }">
        <ul class="flex flex-col gap-1">
          <li v-for="purchaseOrder in item[column]" :key="purchaseOrder.slug">
            <div class="flex flex-wrap items-baseline gap-x-2">
              <Link :href="route('grp.org.procurement.purchase_orders.show', [purchaseOrder.organisation_slug, purchaseOrder.slug])" class="secondaryLink whitespace-nowrap">
                {{ purchaseOrder.organisation }} {{ purchaseOrder.reference }}
              </Link>
              <span :class="purchaseOrder.state === 'in_process' ? 'text-gray-600' : 'font-medium text-amber-600'">
                {{ stateLabels[purchaseOrder.state] ?? purchaseOrder.state }}
              </span>
              <span v-if="purchaseOrder.quantity !== null" class="tabular-nums text-gray-500">
                {{ ctrans(":quantity ordered", { quantity: String(Number(purchaseOrder.quantity)) }) }}
              </span>
            </div>
            <div class="text-xs text-gray-500">{{ purchaseOrderDates(purchaseOrder) }}</div>
          </li>
        </ul>
      </template>
    </Table>
  </div>
</template>
