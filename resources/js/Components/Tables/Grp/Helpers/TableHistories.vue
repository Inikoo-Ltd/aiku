<script setup lang="ts">
import Table from '@/Components/Table/Table.vue';
import { ref } from 'vue';
import JsonViewer from 'vue-json-viewer';
import { faPlus, faMinus } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faArrowRight } from '@fal';

library.add(faPlus, faMinus, faArrowRight);

defineProps<{
    data: object,
    tab?: string
}>()

const index = ref(null);
const ExpandData = ref(null);

const formatDate = (dateIso: string) => {
    const date = new Date(dateIso);
    const year = date.getFullYear();
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const day = date.getDate().toString().padStart(2, '0');
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');

    return `${year}-${month}-${day} ${hours}:${minutes}`;
}

const onExpand = (data) => {
    ExpandData.value = data;
    index.value = data.rowIndex;
}

const onCloseExpand = (data) => {
    ExpandData.value = null;
    index.value = null;
}

const getKeys = (oldValues: any, newValues: any): string[] => {
  const keys = new Set([
    ...Object.keys(oldValues || {}),
    ...Object.keys(newValues || {})
  ]);
  return Array.from(keys);
};

const getChangedKeys = (oldValues: any, newValues: any): string[] => {
  return getKeys(oldValues, newValues).filter(key => oldValues[key] !== newValues[key]);
};

const formatKey = (key: string): string => {
  return key
    .replace(/_/g, ' ')
    .replace(/\b\w/g, char => char.toUpperCase());
};

const formatValue = (value: any) => {
  if (typeof value === 'boolean') {
    return value ? 'Active' : 'Inactive';
  }
  return value;
};
</script>

<template>
    <!-- <pre>{{ data }}</pre> {{ tab }} -->
    <Table :resource="data" class="mt-5" :name="tab" :useExpandTable="true">
        <template #cell(expand)="{ item: user }">
            <div v-if="user?.rowIndex === index" class="p-4">
                <FontAwesomeIcon @click="() => onCloseExpand(user)" icon="fas fa-minus" />
            </div>
            <div v-else class="p-4">
                <FontAwesomeIcon @click="() => onExpand(user)" icon="fas fa-plus" />
            </div>
        </template>

        <template #cell(datetime)="{ item: user }">
            <span>{{ formatDate(user.datetime) }}</span>
        </template>

        <template #cell(values)="{ item: user }">
            <!-- Only display the values column if the event is not "migration" -->
            <div v-if="user.event !== 'migration'" class="space-y-2">
                <div
                  v-for="key in getChangedKeys(user.old_values, user.new_values)"
                  :key="key"
                  class="flex items-center space-x-2 text-sm"
                >
                  <span class="font-bold text-gray-700">{{ formatKey(key) }}:</span>
                  <span class="text-gray-600">{{ formatValue(user.old_values[key]) }}</span>
                  <FontAwesomeIcon :icon="faArrowRight" aria-hidden="true" size="xs" />
                  <span class="text-gray-800">{{ formatValue(user.new_values[key]) }}</span>
                </div>
            </div>
        </template>

        <template #expandRow="{ item: data }">
            <div v-if="data?.rowIndex === index" class="bg-gray-50">
                <div class="p-4 bg-gray-50">
                    <dl class="grid grid-cols-1 sm:grid-cols-3">
                        <div class="border-gray-100 px-4 py-6 sm:col-span-1 sm:px-0">
                            <dt class="text-sm font-medium leading-6 text-gray-900">IP Address</dt>
                            <dd class="mt-1 text-sm leading-6 text-gray-700 sm:mt-2">{{ ExpandData.ip_address }}</dd>
                        </div>
                        <div class="border-gray-100 px-4 py-6 sm:col-span-1 sm:px-0">
                            <dt class="text-sm font-medium leading-6 text-gray-900">User Agent</dt>
                            <dd class="mt-1 text-sm leading-6 text-gray-700 sm:mt-2">{{ ExpandData.user_agent }}</dd>
                        </div>
                        <div class="border-gray-100 px-4 py-6 sm:col-span-1 sm:px-0">
                            <dt class="text-sm font-medium leading-6 text-gray-900">Auditable Type</dt>
                            <dd class="mt-1 text-sm leading-6 text-gray-700 sm:mt-2">{{ ExpandData.auditable_type }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </template>
    </Table>
</template>
