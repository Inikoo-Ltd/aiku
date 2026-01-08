<script setup lang="ts">
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { trans } from 'laravel-vue-i18n'
import { Column, DataTable, InputNumber } from 'primevue'
import { inject, onMounted, ref } from 'vue'
import InformationIcon from '../Utils/InformationIcon.vue'

const props = defineProps<{
    data_charges: {

    }
    currencyCode: string
    order_data: {

    }
}>()

const locale = inject('locale', aikuLocaleStructure)

const charges = [
    { code: 'CHG001', name: 'Handling Fee', amount: 5.00, quantity: 1 },
    { code: 'CHG002', name: 'Express Processing', amount: 10.00, quantity: 1 },
]
const listCharges = ref([])

onMounted(async () => {
    try {
        const response = await axios.get(
            route('grp.json.order.charges', {order: props.order_data?.id}),
        )
        if (response.status !== 200) {
            
        }
        listCharges.value = response.data?.data || []
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error.message || trans("Please try again or contact administrator"),
            type: 'error'
        })
    }
})
</script>

<template>
    <div>
        <div class="mb-8 font-bold text-2xl text-center">Add/edit Charges</div>
        <DataTable :value="listCharges" tableStyle="min-width: 40rem" removable-sort>
            <Column field="code" header="Code" sortable></Column>
            <Column field="name" header="Name" sortable>
                <template #body="slotProps">
                    <div>
                        {{ slotProps.data.name }}
                        <InformationIcon v-if="slotProps.data.description" :information="slotProps.data.description" />
                    </div>
                </template>
            </Column>
            <Column field="net_amount" header="" class="!text-right" headerStyle="text-align:right" style="text-align:right" sortable>
                <template #header="slotProps">
                    <span class="font-bold w-full">Amount</span>
                </template>

                <template #body="slotProps">
                    <div class="flex items-end flex-col">
                        <InputNumber
                            v-model="slotProps.data.net_amount"
                            :mode="'currency'"
                            :currency="currencyCode"
                            :locale="locale.locale_iso"
                            inputClass="text-right"
                            :min="0"
                            :maxFractionDigits="2"
                        />
                        <div
                            v-if="slotProps.data.default_amount !== null"
                            @click="() => slotProps.data.net_amount = slotProps.data.default_amount"
                            class="text-xs text-gray-500 underline hover:text-blue-500 cursor-pointer mb-0.5 w-fit"
                            :class="slotProps.data.net_amount === slotProps.data.default_amount ? 'opacity-70' : ''"

                        >
                            Default: {{ locale.currencyFormat(slotProps.data.currency_code, slotProps.data.default_amount) }}
                        </div>
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>
</template>