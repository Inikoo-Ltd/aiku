<script setup lang="ts">
import Toggle from '@/Components/Pure/Toggle.vue'
import Multiselect from '@vueform/multiselect'

const props = defineProps<{
  form: any
  fieldName: string
  fieldData: any
}>()

const ensureFilterStateArray = (row: any) => {
  if (!row.filters) row.filters = {}
  if (!Array.isArray(row.filters.state)) row.filters.state = []
}

const onEnabledChange = (row: any, value: boolean) => {
  row.is_enabled = value
}

const onStateChange = (row: any, value: any) => {
  ensureFilterStateArray(row)
  row.filters.state = Array.isArray(value) ? value : []
}
</script>

<template>
  <div class="space-y-4">
    <div v-if="!form[fieldName] || form[fieldName].length === 0" class="text-gray-500 text-sm">
      {{ fieldData?.emptyText || 'No notification settings found.' }}
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="row in form[fieldName]"
        :key="row.id"
        class="border rounded-lg p-4 bg-gray-50"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="text-sm font-medium text-gray-900 truncate">
              {{ row.type_name }}
            </div>
            <div class="text-xs text-gray-600 mt-1">
              <span class="text-gray-500">Scope:</span>
              <span class="font-semibold text-indigo-700">{{ row.scope_label }}</span>
            </div>
          </div>

          <div class="flex items-center gap-3 shrink-0">
            <div class="text-xs text-gray-600">
              {{ row.is_enabled ? 'Active' : 'Inactive' }}
            </div>
            <Toggle
              :modelValue="row.is_enabled"
              @update:modelValue="(val: boolean) => onEnabledChange(row, val)"
            />
          </div>
        </div>

        <div v-if="row.is_enabled && row.available_states && row.available_states.length" class="mt-4">
          <div class="text-xs font-medium text-gray-700 mb-2">
            States (empty = all)
          </div>
          <Multiselect
            :modelValue="(row.filters && Array.isArray(row.filters.state)) ? row.filters.state : []"
            @update:modelValue="(val: any) => onStateChange(row, val)"
            :options="row.available_states"
            mode="tags"
            :searchable="true"
            :closeOnSelect="false"
            :label="'label'"
            :valueProp="'value'"
            placeholder="Select states"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<style src="@vueform/multiselect/themes/default.css"></style>
