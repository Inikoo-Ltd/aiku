<script setup lang="ts">
import Table from '@/Components/Table/Table.vue';
import { ref } from 'vue';
import { useLayoutStore } from "@/Stores/retinaLayout";
import { faPlus, faMinus } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faArrowRight, faPlusCircle, faPenSquare, faTrashAlt, faUndo, faExchange } from '@fal';
import { trans } from 'laravel-vue-i18n';
import { useFormatTime } from '@/Composables/useFormatTime';
import Modal from '@/Components/Utils/Modal.vue';

library.add(faPlus, faMinus, faArrowRight);

const eventIcons: Record<string, any> = {
    created: faPlusCircle,
    deleted: faTrashAlt,
    restored: faUndo,
    migration: faExchange,
};

const describeAgent = (userAgent?: string): string => {
    if (!userAgent) return '';
    const browser =
        userAgent.includes('Edg/') ? 'Edge' :
        userAgent.includes('Firefox/') ? 'Firefox' :
        userAgent.includes('Chrome/') ? 'Chrome' :
        userAgent.includes('Safari/') ? 'Safari' : '';
    const os =
        userAgent.includes('Mac OS X') ? 'macOS' :
        userAgent.includes('Windows') ? 'Windows' :
        userAgent.includes('Android') ? 'Android' :
        userAgent.includes('iPhone') || userAgent.includes('iPad') ? 'iOS' :
        userAgent.includes('Linux') ? 'Linux' : '';
    return [browser, os].filter(Boolean).join(' · ');
};

const detailHistory = ref<any>(null);
const isStaffApp = !String(route().current() ?? '').startsWith('retina.');

defineProps<{
    data: object,
    tab?: string
}>()

const layout = useLayoutStore()

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
    .split('.')
    .map(segment => segment
      .replace(/_/g, ' ')
      .replace(/\b\w/g, char => char.toUpperCase()))
    .join(' › ');
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

const hasValue = (value: any): boolean => {
  return value !== null && value !== undefined && value !== '';
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
    <Table :resource="data" class="mt-5" :name="tab">
        <template #cell(datetime)="{ item: history }">
            <span class="whitespace-nowrap">
                <FontAwesomeIcon
                    :icon="eventIcons[history.event] ?? faPenSquare"
                    v-tooltip="history.event?.replace(/_/g, ' ')"
                    class="text-gray-400 mr-2"
                    fixed-width
                    aria-hidden="true"
                />
                <span v-tooltip="useFormatTime(history.datetime, { formatTime: 'hms' })">{{ useFormatTime(history.datetime, { formatTime: 'short-datetime' }) }}</span>
            </span>
        </template>

        <template #cell(user_name)="{ item: history }">
            <button type="button" @click="detailHistory = history" v-tooltip="trans('Details')" class="whitespace-nowrap cursor-pointer hover:underline">
                {{ history.user_name }}
            </button>
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
                                        </span>
                                        <span v-if="item.new">
                                            {{ item.new }}
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else-if="history.event == 'shipper_lock_override'"
                        class="text-sm space-y-1"
                    >
                        <div class="flex items-center space-x-2">
                            <span class="font-bold text-gray-700">{{ trans("Shipper") }}:</span>
                            <span class="text-gray-600">{{ history.old_values?.shipper }}</span>
                            <FontAwesomeIcon :icon="faArrowRight" aria-hidden="true" size="xs" />
                            <span class="text-gray-800 font-semibold">{{ history.new_values?.shipper }}</span>
                        </div>
                        <div class="text-xs text-gray-500 italic">
                            {{ history.old_values?.locked_by == 'customer'
                                ? trans("Overrode the shipper chosen by the customer")
                                : trans("Overrode the lock set by the shipping rules for :scope", { scope: history.old_values?.locked_scope ?? '' }) }}
                        </div>
                    </div>
                    <div
                        v-else
                        class="grid grid-cols-[9rem_1fr] gap-x-3 gap-y-0.5 items-baseline w-full"
                        :class="getChangedKeys(history.old_values, history.new_values).length > 1 ? 'text-xs' : 'text-sm'"
                    >
                        <template
                            v-for="key in getChangedKeys(history.old_values, history.new_values)"
                            :key="key"
                        >
                            <span class="text-xs text-gray-500 text-right whitespace-nowrap">{{ formatKey(key) }}:</span>
                            <span class="text-gray-700">
                                <template v-if="hasValue(history.old_values[key])">
                                    <span
                                        class="text-gray-400"
                                        :class="{ 'line-through decoration-gray-300': hasValue(history.new_values[key]) }"
                                    >{{ formatValue(history.old_values[key], key) }}</span>
                                    <FontAwesomeIcon v-if="hasValue(history.new_values[key])" :icon="faArrowRight" aria-hidden="true" size="xs" class="text-gray-300 mx-1.5" />
                                </template>
                                <span v-if="hasValue(history.new_values[key])">{{ formatValue(history.new_values[key], key) }}</span>
                                <span v-else class="text-gray-400 italic">{{ trans("cleared") }}</span>
                            </span>
                        </template>
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
    </Table>

    <Modal :isOpen="!!detailHistory" @onClose="detailHistory = null" width="w-full max-w-md">
        <div v-if="detailHistory" class="text-sm">
            <div class="text-base mb-4">{{ trans("Change details") }}</div>
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5">
                <dt class="text-gray-400 text-right">{{ trans("User") }}</dt>
                <dd>{{ detailHistory.user_name }}</dd>
                <dt class="text-gray-400 text-right">{{ trans("Date") }}</dt>
                <dd>{{ useFormatTime(detailHistory.datetime, { formatTime: 'hms' }) }}</dd>
                <dt class="text-gray-400 text-right">{{ trans("Action") }}</dt>
                <dd>{{ detailHistory.event?.replace(/_/g, ' ') }}</dd>
                <template v-if="isStaffApp && detailHistory.ip_address">
                    <dt class="text-gray-400 text-right">{{ trans("IP address") }}</dt>
                    <dd>{{ detailHistory.ip_address }}</dd>
                </template>
                <template v-if="isStaffApp && detailHistory.user_agent">
                    <dt class="text-gray-400 text-right">{{ trans("Browser") }}</dt>
                    <dd>{{ describeAgent(detailHistory.user_agent) }}</dd>
                    <dt class="text-gray-400 text-right">{{ trans("User agent") }}</dt>
                    <dd class="break-words text-gray-500 text-xs">{{ detailHistory.user_agent }}</dd>
                </template>
                <template v-if="isStaffApp && detailHistory.url">
                    <dt class="text-gray-400 text-right">{{ trans("URL") }}</dt>
                    <dd class="break-all text-gray-500 text-xs">{{ detailHistory.url }}</dd>
                </template>
            </dl>
        </div>
    </Modal>
</template>
