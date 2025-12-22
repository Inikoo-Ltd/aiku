<script setup lang="ts">
import { computed, watchEffect } from "vue"

type RowItem = {
  id: number | string
  code: string
  name: string
  checked?: boolean
}

const modelValue = defineModel<RowItem[]>({
  default: () => []
})

/**
 * Ensure `checked` exists on every row (once per change)
 */
watchEffect(() => {
  modelValue.value.forEach(row => {
    if (row.checked === undefined) {
      row.checked = false
    }
  })
})

/**
 * Bulk checkbox logic
 */
const allChecked = computed({
  get() {
    return (
      modelValue.value.length > 0 &&
      modelValue.value.every(row => row.checked)
    )
  },
  set(value: boolean) {
    modelValue.value.forEach(row => {
      row.checked = value
    })
  }
})

const isIndeterminate = computed(() => {
  const checkedCount = modelValue.value.filter(r => r.checked).length
  return checkedCount > 0 && checkedCount < modelValue.value.length
})
</script>

<template>
  <div class="border border-gray-200 rounded">
    <!-- HEADER -->
    <table class="w-full text-sm table-fixed border-collapse">
      <colgroup>
        <col class="w-12" />
        <col />
        <col />
      </colgroup>

      <thead class="bg-gray-100 sticky top-0 z-10">
        <tr>
          <th class="p-2 text-center border-b">
            <input
              type="checkbox"
              v-model="allChecked"
              :indeterminate="isIndeterminate"
            />
          </th>
          <th class="p-2 text-left border-b">Code</th>
          <th class="p-2 text-left border-b">Name</th>
        </tr>
      </thead>
    </table>

    <!-- BODY -->
    <div class="max-h-80 overflow-y-auto">
      <table class="w-full text-sm table-fixed border-collapse">
        <colgroup>
          <col class="w-12" />
          <col />
          <col />
        </colgroup>

        <tbody>
          <tr
            v-for="row in modelValue"
            :key="row.id"
            class="border-t hover:bg-gray-50"
          >
            <td class="p-2 text-center">
              <input type="checkbox" v-model="row.checked" />
            </td>

            <td class="p-2 truncate">{{ row.code }}</td>
            <td class="p-2 truncate">{{ row.name }}</td>
          </tr>

          <tr v-if="modelValue.length === 0">
            <td colspan="3" class="p-4 text-center text-gray-400">
              No data available
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>


