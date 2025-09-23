<script setup lang="ts">
import { ref, reactive, inject } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import NumberWithButtonSave from '@/Components/NumberWithButtonSave.vue'
import Table from '@/Components/Table/Table.vue'
import Tag from '@/Components/Tag.vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { routeType } from '@/types/route'
import { Table as TableTS } from '@/types/Table'
import { faMinus, faPlus } from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Link, router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { debounce } from 'lodash-es'
import InputNumber from 'primevue/inputnumber'
import { faXmark } from '@fortawesome/free-solid-svg-icons'

const props = defineProps<{
  data: any[] | TableTS
  tab: string
  updateRoute: routeType
  state?: string
  readonly?: boolean
}>()

// --- State ---
// editingIds: tracks rows in edit mode
const editingIds = ref<number[]>([])
// createNewQty: map id -> cloned item (so original props.data stays unchanged)
const createNewQty = reactive<Record<number, any>>({})
const locale = inject('locale', retinaLayoutStructure)
const isLoading = ref<string | null>(null)

// Helper to get array of rows whether props.data is array or TableTS
function rowsArray() {
  if (Array.isArray(props.data)) return props.data as any[]
  return (props.data as any).data || []
}

// --- Utils ---
function formatQuantity(value: any): string | number {
  if (Number.isInteger(Number(value)) && String(value).match(/^\d+(\.0+)?$/)) {
    return parseInt(value)
  }
  return parseFloat(value)
}

function productRoute(product: any) {
  switch (route().current()) {
    case 'grp.org.shops.show.crm.customers.show.orders.show':
    case 'grp.org.shops.show.ordering.orders.show':
      if (product.product_slug) {
        return route(
          'grp.org.shops.show.catalogue.products.all_products.show',
          [
            route().params['organisation'],
            route().params['shop'],
            product.product_slug,
          ]
        )
      }
      return ''
    default:
      return ''
  }
}

// Start edit: create shallow clone and add to editingIds
function startEdit(item: any) {
  if (!editingIds.value.includes(item.id)) {
    editingIds.value.push(item.id)
    // create a new variable (clone) and store it — do NOT mutate original item
    createNewQty[item.id] = { ...item }
  }
}

// Cancel editing for a row
function onCancel(item: any) {
  // remove clone and exit edit mode
  delete createNewQty[item.id]
  editingIds.value = editingIds.value.filter((id) => id !== item.id)
}

// --- Update logic (per-row patch) ---
const onUpdateQuantity = (
  routeUpdate: routeType,
  idTransaction: number,
  value: number
) => {
  router.patch(
    route(routeUpdate.name, routeUpdate.parameters),
    { quantity_ordered: Number(value) },
    {
      onError: (e: any) => {
        notify({
          title: trans('Something went wrong'),
          text: e.message,
          type: 'error',
        })
      },
      onStart: () => (isLoading.value = 'quantity' + idTransaction),
      onFinish: () => (isLoading.value = null),
      only: ['transactions', 'box_stats', 'total_to_pay', 'balance'],
      preserveScroll: true,
    }
  )
}

const debounceUpdateQuantity = debounce(
  (routeUpdate: routeType, idTransaction: number, value: number) => {
    onUpdateQuantity(routeUpdate, idTransaction, value)
  },
  500
)

// --- Save all changes ---
function onSave() {
  // Build list of changed rows by comparing clone vs original
  const changedItems = Object.entries(createNewQty)
    .map(([id, clonedItem]) => {
      const orig = rowsArray().find((x: any) => x.id === Number(id))
      if (!orig) return null
      const origQty = Number(orig.quantity_ordered)
      const newQty = Number(clonedItem.quantity_ordered)
      if (newQty === origQty) return null // skip unchanged
      return {
        id: orig.id,
        newQty,
      }
    })
    .filter(Boolean) as Array<{ id: number; updateRoute: routeType; newQty: number }>

  if (changedItems.length === 0) {
    // nothing changed — keep edit state if you want, or clear; here we just return
    return
  }

  // Submit each changed row (you can replace with a bulk request if desired)
  console.log(changedItems)

  // Clear clones & exit edit mode
  Object.keys(createNewQty).forEach((k) => delete createNewQty[Number(k)])
  editingIds.value = []
}
</script>

<template>
  <Table :resource="data" :name="tab">
    <!-- Save All Button -->
    <template #add-on-button-in-before>
      <Button
        v-if="Object.keys(createNewQty).length > 0"
        label="Save all new quantity"
        @click="onSave"
      />
    </template>

    <!-- Column: Code -->
    <template #cell(asset_code)="{ item }">
      <Link :href="productRoute(item)" class="primaryLink">
        {{ item.asset_code }}
      </Link>
    </template>

    <!-- Column: Name / Stock -->
    <template #cell(asset_name)="{ item }">
      <div>
        <div>{{ item.asset_name }}</div>
        <div
          v-if="
            typeof item.available_quantity !== 'undefined' &&
            item.available_quantity < 1
          "
        >
          <Tag label="Out of stock" no-hover-color :theme="7" size="xxs" />
        </div>
        <div v-else class="text-gray-500 italic text-xs">
          Stock: {{ locale.number(item.available_quantity || 0) }} available
        </div>
      </div>
    </template>

    <!-- Column: Quantity Ordered -->
    <template #cell(quantity_ordered)="{ item }">
      <div class="flex items-center justify-end">
        <div v-if="state === 'creating' || state === 'xsubmitted'" class="w-fit">
          <NumberWithButtonSave
            :modelValue="item.quantity_ordered"
            :routeSubmit="item.updateRoute"
            :bindToTarget="{ min: 0 }"
            isWithRefreshModel
            keySubmit="quantity_ordered"
            :isLoading="isLoading === 'quantity' + item.id"
            :readonly="readonly"
            @update:modelValue="
              (e: number) =>
                debounceUpdateQuantity(item.updateRoute, item.id, e)
            "
            noUndoButton
            noSaveButton
          />
        </div>
        <div v-else>{{ formatQuantity(item.quantity_ordered) }}</div>
      </div>
    </template>

    <!-- Column: New Quantity -->
    <template #cell(new_quantity)="{ item }">
      <div v-if="editingIds.includes(item.id)" class="flex items-center gap-2">
        <!-- Bind to the cloned item property so original item is untouched -->
        <InputNumber
          v-model="createNewQty[item.id].quantity_ordered"
          :step="1"
          showButtons
          button-layout="horizontal"
          inputClass="w-full text-xs"
          :min="0"
        >
          <template #incrementbuttonicon>
            <FontAwesomeIcon :icon="faPlus" />
          </template>
          <template #decrementbuttonicon>
            <FontAwesomeIcon :icon="faMinus" />
          </template>
        </InputNumber>

        <Button
          type="negative"
          v-tooltip="'Cancel'"
          :icon="faXmark"
          @click="onCancel(item)"
          size="sm"
          aria-label="Cancel edit"
        />
      </div>

      <div v-else>
        <Button
          label="New quantity"
          @click="() => startEdit(item)"
          size="xs"
        />
      </div>
    </template>

    <!-- Column: Actions -->
    <template #cell(actions)="{ item }">
      <div class="flex gap-2">
        <Link
          v-if="state === 'creating' || state === 'xsubmitted'"
          :href="route(item.deleteRoute.name, item.deleteRoute.parameters)"
          as="button"
          :method="item.deleteRoute.method"
          @start="() => (isLoading.value = 'unselect' + item.id)"
          @finish="() => (isLoading.value = null)"
          v-tooltip="trans('Unselect this product')"
          :preserveScroll="true"
        >
          <Button
            v-if="!readonly"
            icon="fal fa-times"
            type="negative"
            size="xs"
            :loading="isLoading === 'unselect' + item.id"
          />
        </Link>
      </div>
    </template>
  </Table>
</template>
