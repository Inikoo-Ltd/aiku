<script setup lang="ts">
import Table from '@/Components/Table/Table.vue';
import { ref } from 'vue';
import { useLayoutStore } from "@/Stores/retinaLayout";
import JsonViewer from 'vue-json-viewer';
import { faPlus, faMinus } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faArrowRight, faPlus as falPlus, faMinus as falMinus} from '@fal';

library.add(faPlus, faMinus, faArrowRight);

defineProps<{
    data: object,
    tab?: string
}>()

const index = ref(null);
const ExpandData = ref(null);
const layout = useLayoutStore()

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
    ...Object.keys(newValues || {}),
    ...Object.keys(oldValues || {}),
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

const formatValue = (value: any, key?: string) => {
  if (typeof value === 'boolean') {
    return value ? 'Active' : 'Inactive';
  }

  if (value === null || value === undefined || value === '') {
    return value;
  }

  const numericValue = typeof value === 'number'
    ? value
    : (typeof value === 'string' && /^-?\d+(?:\.\d+)?$/.test(value.trim()) ? Number(value) : null);

  if (numericValue !== null && Number.isFinite(numericValue)) {
    const fieldKey = (key || '').toLowerCase();
    const isAmountLikeField = fieldKey.includes('amount')
      || fieldKey.includes('exchange')
      || fieldKey.includes('commission')
      || fieldKey.includes('margin');

    if (isAmountLikeField) {
      return numericValue.toFixed(2);
    }

    if (!Number.isInteger(numericValue) && String(value).includes('.')) {
      return Number(numericValue.toFixed(6)).toString();
    }
  }

  return value;
};

const expandedRows = ref<String[]>([]);

const clickExpand = (id: string) => {
    if (id) {
        if (expandedRows.value.includes(id)) {
            expandedRows.value = expandedRows.value.filter((item) => item != id);
        } else {
            expandedRows.value.push(id);
        }
    }
}

const getTradeUnitHistory = (oldData, newData) => {
    const modified = [];
    const added = [];
    const removed = [];

    const keys = new Set([
        ...Object.keys(oldData ?? {}),
        ...Object.keys(newData ?? {}),
    ]);

    for (const key of keys) {
        const oldValue = oldData?.[key];
        const newValue = newData?.[key];

        if (oldValue === undefined) {
            added.push({ key, old: newValue });
        } else if (newValue === undefined) {
            removed.push({ key, new: oldValue });
        } else if (JSON.stringify(oldValue) !== JSON.stringify(newValue)) {
            modified.push({
                key,
                old: oldValue,
                new: newValue,
            });
        }
    }

    return { modified, added, removed };
}

</script>

<template>
    <!-- <pre>{{ data }}</pre> {{ tab }} -->
    <Table :resource="data" class="mt-5" :name="tab" :useExpandTable="true">
        <template #cell(expand)="{ item: user }">
            <div v-if="user?.rowIndex === index" class="p-4 cursor-pointer">
                <FontAwesomeIcon @click="() => onCloseExpand(user)" icon="fas fa-minus" />
            </div>
            <div v-else class="p-4 cursor-pointer">
                <FontAwesomeIcon @click="() => onExpand(user)" icon="fas fa-plus" />
            </div>
        </template>

        <template #cell(datetime)="{ item: user }">
            <span>{{ formatDate(user.datetime) }}</span>
        </template>

        <template #cell(values)="{ item: history }">
            <!-- Only display the values column if the event is not "migration" -->
             <div class="flex">
                <div 
                    v-if="history.event !== 'migration'" 
                    class="space-y-2 overflow-y-auto grid flex-auto transition-all ease-in-out duration-700" 
                    :class="history.id && expandedRows.includes(history.id) ? 'max-h-[999px]' : 'max-h-[100px]'"
                    style="scrollbar-width:none"
                >
                    <div 
                        v-if="history.event == 'update_trade_units'"
                        :key="history.id"
                        class="grid space-x-2 text-sm"
                    >
                        <div 
                            v-for="(audit, key) in getTradeUnitHistory(history.old_values, history.new_values)"
                            :class="[
                                !audit.length ? 'hidden': 'mb-1'
                            ]"
                            class="!mx-0"
                        >
                            <span class="font-semibold">
                                {{ key.charAt(0).toUpperCase() + key.slice(1) }}: 
                            </span>
                            <div 
                                class="flex flex-col gap-2 border rounded-md px-2 py-1 w-[75%] mb-1"
                                :class="{
                                    'bg-blue-100 border-blue-400': key == 'modified',
                                    'bg-red-100 border-red-400': key == 'removed',
                                    'bg-green-100 border-green-400': key == 'added',
                                }"
                            >
                                <div
                                    v-for="(item, key2) in audit"
                                    :key="key2"
                                    class="flex items-center space-x-2 text-xs"
                                >
                                    <span class="font-semibold">
                                        <span>
                                            {{ key == 'modified' ? '~' : (key == 'added'  ? '+' : '-') }}
                                        </span>
                                        {{ item.key }}:
                                    </span>
                                    <span>
                                        <span v-if="item.old">
                                            {{ item.old }} 
                                        </span>
                                        <span v-if="item.old && item.new" class="px-1">
                                            <FontAwesomeIcon :icon="faArrowRight" aria-hidden="true" size="xs" />
                                        </span v-if="item.new">
                                        <span>
                                            {{ item.new }}
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        v-for="key in getChangedKeys(history.old_values, history.new_values)"
                        :key="key"
                        class="flex items-center space-x-2 text-sm"
                    >
                        <span class="font-bold text-gray-700">{{ formatKey(key) }}:</span>
                        <span class="text-gray-600">{{ formatValue(history.old_values[key], key) }}</span>
                            <FontAwesomeIcon :icon="faArrowRight" aria-hidden="true" size="xs" />
                        <span class="text-gray-800">{{ formatValue(history.new_values[key], key) }}</span>
                    </div>
                </div>
                <div 
                    v-if=" 
                        history.event == 'update_trade_units' ?
                        (getChangedKeys(history.old_values, history.new_values).length ?? 0) > 2
                        : (getChangedKeys(history.old_values, history.new_values).length ?? 0) > 4
                    "
                    @click="clickExpand(history.id)"
                    class="flex-initial w-[50px] my-auto cursor-pointer"
                >
                    <span
                        class="justify-self-end text-md p-2 rounded-full h-[30px] w-[30px] flex align-center hover:opacity-85" 
                        :class="history.id && expandedRows.includes(history.id) ? 'align-top' : 'align-center'"
                        :style="{
                            background: layout?.app?.theme[0],
                            color: layout?.app?.theme[1],
                        }"
                    >
                        <FontAwesomeIcon 
                            :icon="history.id && expandedRows.includes(history.id) ? faMinus : faPlus" 
                            class="h-fit transition-all ease-out duration-700"
                        />
                    </span>
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
