<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { computed, watchEffect } from "vue"
import Icon from "@/Components/Icon.vue"

type RowItem = {
  id: number | string
  code: string
  name: string
  checked?: boolean
}

const modelValue = defineModel<RowItem[]>({
  default: () => []
})

const props = defineProps<{
  collections?: {}
}>()

console.log('props coll', props.collections)

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

const getIcon = (item: string) => {
  const map = {
    in_process: {
      tooltip: trans('In Process'),
      icon: 'fal fa-seedling',
      class: 'text-gray-400',
    },
    active: {
      tooltip: trans('Active'),
      icon: 'fas fa-play',
      class: 'text-green-700',
    },
    inactive: {
      tooltip: trans('Inactive'),
      icon: 'fal fa-pause-circle',
      class: 'text-gray-500',
    },
}

  return map[item] ?? map.inactive
}

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
          <th class="p-2 text-left border-b w-12"></th>
          <th class="p-2 text-left border-b">{{trans('Code')}}</th>
          <th class="p-2 text-left border-b w-56">{{trans('Name')}}</th>
          <th class="p-2 text-left border-b">{{trans('In Shop')}}</th>
          <th class="p-2 text-left border-b">{{trans('Web State')}}</th>
        </tr>
        <!-- children -->
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
          <tr v-for="collection in collections">
              <td class="p-2 text-center">
                <input type="checkbox" />
              </td>
              <td class="p-2 truncate w-12">
                <Icon :data="getIcon(collection.state)"/>
              </td>
              <td class="p-2 truncate" v-tooltip="collection.code">{{ collection.code }}</td>
              <td class="p-2 w-56"  v-tooltip="collection.name">{{ collection.name }}</td>
              <td class="p-2 truncate">{{ collection.shop.code }}</td>
              <td class="p-2 truncate" v-tooltip="collection.webpage?.state ?? trans('Does not have any webpage')">{{ collection.webpage?.state ?? '-' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>


