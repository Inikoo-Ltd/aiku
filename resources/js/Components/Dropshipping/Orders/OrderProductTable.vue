<script setup lang="ts">
import { ref, reactive, inject, onBeforeUnmount } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import NumberWithButtonSave from '@/Components/NumberWithButtonSave.vue'
import Table from '@/Components/Table/Table.vue'
import Tag from '@/Components/Tag.vue'
import { routeType } from '@/types/route'
import { Table as TableTS } from '@/types/Table'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPencil, faTimes } from '@far'
import { Link, router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { debounce } from 'lodash-es'
import ModalProductList from "@/Components/Utils/ModalProductList.vue"

type ProductRow = {
  id: number
  asset_code: string
  asset_name: string
  quantity_ordered: number
  available_quantity?: number
  product_slug?: string
  updateRoute: routeType
  deleteRoute?: routeType
}

const props = defineProps<{
  data: ProductRow[] | TableTS<ProductRow>
  tab: string
  updateRoute: routeType
  state?: string
  readonly?: boolean
  modifyRoute?:routeType
}>()

const layout = inject("layout", {});
const locale = inject('locale', {})
const editingIds = ref<Set<number>>(new Set())
const createNewQty = reactive<Record<number, ProductRow>>({})
const isLoading = ref<string | null>(null)
const isModalProductListOpen = ref(false)
const loadingsaveModify = ref(false)

// Helper: get rows as array
function rowsArray() {
  if (Array.isArray(props.data)) return props.data
  return (props.data as TableTS<ProductRow>).data || []
}

// --- Utils ---
function formatQuantity(value: any): string | number {
  if (Number.isInteger(Number(value)) && String(value).match(/^\d+(\.0+)?$/)) {
    return parseInt(value)
  }
  return parseFloat(value)
}

function productRoute(product: ProductRow) {
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

// --- Editing Logic ---
function startEdit(item: ProductRow) {
  if (!editingIds.value.has(item.id)) {
    editingIds.value.add(item.id)
    createNewQty[item.id] = { ...item }
  }
}

function onCancel(item: ProductRow) {
  delete createNewQty[item.id]
  editingIds.value.delete(item.id)
}

// --- Update Logic ---
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

// Debounced update
const debounceUpdateQuantity = debounce(
  (routeUpdate: routeType, idTransaction: number, value: number) => {
    onUpdateQuantity(routeUpdate, idTransaction, value)
  },
  500
)

onBeforeUnmount(() => {
  debounceUpdateQuantity.cancel()
})

// --- Save all changes ---
async function onSave() {
  const changedItems = Object.entries(createNewQty).reduce(
    (acc, [id, clonedItem]) => {
      const orig = rowsArray().find(x => x.id === Number(id))
      if (!orig) return acc

      const newQty = Number(clonedItem.quantity_ordered)
      if (newQty === Number(orig.quantity_ordered)) return acc

      acc[orig.id] = { newQty } // 👈 key by id
      return acc
    },
    {} as Record<number, { newQty: number }>
  )

  if (Object.keys(changedItems).length === 0) return

   router.patch(
    route(props.modifyRoute.name, props.modifyRoute.parameters),
    { transaction: changedItems },
    {
      onStart: () => (loadingsaveModify.value = true),
      onFinish: () => (loadingsaveModify.value = false),
      onSuccess: () => {
          Object.keys(createNewQty).forEach(k => delete createNewQty[Number(k)])
          editingIds.value.clear()
        notify({
          title: trans('Success'),
          text: trans('Quantities updated successfully'),
          type: 'success',
        })
      },
      onError: (e: any) => {
        notify({
          title: trans('Something went wrong'),
          text: e.message,
          type: 'error',
        })
      },
      preserveScroll: true,
    }
  )
}



</script>

<template>
  <Table :resource="data" :name="tab">
    <!-- Save All Button -->
    <template #add-on-button-in-before>
      <Button v-if="Object.keys(createNewQty).length > 0" label="Save all new quantity" @click="onSave" :loading="loadingsaveModify" />
     <!--  <Button label="Add New" /> -->
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
        <div v-if="item.available_quantity !== undefined && item.available_quantity < 1">
          <Tag label="Out of stock" no-hover-color :theme="7" size="xxs" />
        </div>
        <div v-else class="text-gray-500 italic text-xs">
          Stock: {{ locale.number(item.available_quantity || 0) }} available
        </div>
      </div>
    </template>

    <!-- Column: Quantity Ordered -->
    <template #cell(quantity_ordered)="{ item }">
      <div class="flex items-center justify-end gap-2">
        <!-- Editable when creating and not in edit mode -->
        <div v-if="state === 'creating' && !editingIds.has(item.id)" class="w-fit">
          <NumberWithButtonSave :modelValue="item.quantity_ordered" :routeSubmit="item.updateRoute"
            :bindToTarget="{ min: 0 }" isWithRefreshModel keySubmit="quantity_ordered"
            :isLoading="isLoading === 'quantity' + item.id" :readonly="readonly"
            @update:modelValue="(e: number) => debounceUpdateQuantity(item.updateRoute, item.id, e)" noUndoButton
            noSaveButton />
        </div>

        <!-- Read-only display -->
        <div v-else-if="!editingIds.has(item.id)">
          {{ formatQuantity(item.quantity_ordered) }}
        </div>

        <!-- Inline edit mode with original quantity displayed -->
        <div v-else class="items-center gap-2">
          <span class="text-gray-500 italic text-sm">
            original: {{ formatQuantity(item.quantity_ordered) }}
          </span>
          <NumberWithButtonSave v-model="createNewQty[item.id].quantity_ordered" :bindToTarget="{ min: 0 }" noUndoButton
            noSaveButton class="w-24" />
        </div>
      </div>
    </template>

    <template #cell(net_amount)="{ item }">
      <div class="flex justify-end">
        <div v-if="editingIds.has(item.id)" class="">
          <!-- Original price tag -->
          <div
            class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium shadow-sm whitespace-nowrap my-2">
            orig: {{ locale.currencyFormat(item.currency_code, item.net_amount) }}
          </div>
          <!-- Estimated price tag -->
          <div
            class="bg-yellow-100 text-yellow-800 px-4 py-1.5 rounded-full text-sm font-medium shadow-sm whitespace-nowrap inline-flex items-center justify-center">
            est: {{ locale.currencyFormat(item.currency_code, (item.price *
              createNewQty[item.id].quantity_ordered).toFixed(2)) }}
          </div>
        </div>
        <div v-else>
          {{ locale.currencyFormat(item.currency_code, item.net_amount) }}
        </div>
      </div>
    </template>

    <!-- Column: Actions -->
    <template #cell(actions)="{ item }">
      <div class="flex gap-2 items-center">
        <!-- Delete / Unselect -->
        <Link v-if="state === 'creating'" :href="route(item.deleteRoute.name, item.deleteRoute.parameters)" as="button"
          :method="item.deleteRoute.method" @start="() => (isLoading.value = 'unselect' + item.id)"
          @finish="() => (isLoading.value = null)" v-tooltip="trans('Unselect this product')" :preserveScroll="true">
        <Button v-if="!readonly" icon="fal fa-times" type="negative" size="xs"
          :loading="isLoading === 'unselect' + item.id" />
        </Link>

        <!-- Edit / Cancel -->
        <div v-if="state !== 'creating'">
          <!-- Show edit button if not editing AND environment is local -->
          <button v-if="!editingIds.has(item.id) && layout?.app?.environment === 'local'"
            class="h-9 align-bottom text-center" @click="startEdit(item)" aria-label="Edit Product Order"
            v-tooltip="'Edit Product Order'">
            <FontAwesomeIcon :icon="faPencil" class="h-5 text-gray-500 hover:text-gray-700" aria-hidden="true" />
          </button>

          <!-- Cancel button if editing -->
          <Button v-else-if="editingIds.has(item.id)" type="negative" v-tooltip="'Cancel edit'" :icon="faTimes"
            @click="onCancel(item)" size="sm" aria-label="Cancel edit" />
        </div>
      </div>
    </template>

  </Table>

  <!-- <ModalProductList 
    v-model="isModalProductListOpen" 
    :fetchRoute="routes.products_list" 
    :action="currentAction"
    :current="currentTab" 
    v-model:currentTab="currentTab" 
    :typeModel="'order'" 
  /> -->
</template>
