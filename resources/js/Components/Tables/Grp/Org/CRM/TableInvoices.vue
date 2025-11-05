<script setup lang="ts">
import Table from "@/Components/Table/Table.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faCheckCircle, faCircle } from "@fal";
import { useFormatTime } from "@/Composables/useFormatTime";
import { useLocaleStore } from "@/Stores/locale";

library.add(faCircle, faCheckCircle);

defineProps<{
    data: {};
    tab?: string;
}>();

const locale = useLocaleStore();
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(date)="{ item }">
            <div class="text-gray-500 text-right">
                {{ useFormatTime(item.date, { localeCode: locale.language.code, formatTime: "aiku" }) }}
            </div>
        </template>

        <template #cell(net_amount)="{ item }">
            <div class="text-gray-500">
                {{ useLocaleStore().currencyFormat(item.currency_code, item.net_amount) }}
            </div>
        </template>

        <template #cell(total_amount)="{ item }">
            <div :class="item.total_amount >= 0 ? 'text-gray-500' : 'text-red-400'">
                {{ useLocaleStore().currencyFormat(item.currency_code, item.total_amount) }}
            </div>
        </template>
    </Table>
</template>
