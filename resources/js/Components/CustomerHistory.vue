<script setup lang="ts">
import { inject, computed, ref, defineProps } from "vue";
import { trans } from "laravel-vue-i18n"
import ProgressBar from 'primevue/progressbar';
import Dialog from 'primevue/dialog';
import CustomerLifetimeValue from "./CustomerLifetimeValue.vue";
import { faTimes } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { useFormatTime } from "@/Composables/useFormatTime";

library.add(faTimes)

const props = defineProps<{
    data: any
}>()

const layout = inject('layout')

const progressBarColor = computed(() => layout?.app?.theme[0])

const isShowDetail = ref(false)
</script>

<template>
    <div class="border rounded-lg p-4">
        <div class="flex flex-col gap-2">
            <div class="box-border">
                <h3 class="text-lg font-bold">$4,583.09</h3>
                <span class="text-sm">{{ trans('Customer Lifetime Value (CLV)') }}</span>
            </div>
            <ProgressBar :value="70" :showValue="false" class="custom-progressbar mb-4"></ProgressBar>
            <div class="space-y-2">
                <div class="flex justify-between text-xs">
                    <span class="font-semibold">{{ trans('Expected date of next order') }}</span>
                    <span>{{ useFormatTime(data.expected_date_of_next_order, { formatTime: 'MMM dd, yyyy' }) }}</span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="font-semibold">{{ trans('Avg time between orders') }}</span>
                    <span>10 days</span>
                </div>
                <div class="flex justify-between text-xs"> 
                    <span class="font-semibold">{{ trans('Avarage order value') }}</span>
                    <span @click="isShowDetail = true">{{ data.average_order_value }}</span>
                </div>
            </div>
        </div>
    </div>
    <Dialog v-model:visible="isShowDetail" modal :showHeader="false" :style="{ width: '50rem' }" closable>
      <CustomerLifetimeValue :data="data" >
        <template #close>
            <button @click="isShowDetail = false"><FontAwesomeIcon :icon="faTimes" /></button>
        </template>
      </CustomerLifetimeValue>
    </Dialog>
</template>

<style scoped lang="scss">
::v-deep(.custom-progressbar .p-progressbar-value) {
    background-color: v-bind(progressBarColor) !important;
}
</style>
