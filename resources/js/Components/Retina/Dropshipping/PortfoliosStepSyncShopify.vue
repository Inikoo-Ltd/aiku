<script setup lang="ts">
import ConditionIcon from '@/Components/Utils/ConditionIcon.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { trans } from 'laravel-vue-i18n'
import { get } from 'lodash'
import { Column, DataTable } from 'primevue'
import { inject, onMounted } from 'vue'

const model = defineModel<{}[]>()
const props = defineProps<{
    portfolios: {}[]
    listState: { [key: string]: { [key: string]: string } }
}>()

const emits = defineEmits<{
    (e: "updateSelectedProducts", portfolio: {}, dataToSend: {}, keyToConditionicon: string ): void,
    (e: "mounted"): void
}>()

const locale = inject('locale', aikuLocaleStructure)

onMounted(() => {
    emits('mounted')
})
    
</script>

<template>
    <DataTable v-model:selection="model" :value="portfolios" tableStyle="min-width: 50rem">
        <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>
        
        <Column field="code" header="Code" style="max-width: 70px;">

        </Column>

        <Column field="category" header="Category" style="max-width: 200px;">
    
        </Column>

        <Column field="name" header="Name">
            <!-- <template #body="{ data }">
                <div class="whitespace-nowrap">
                    <textarea :value="data.name" class="w-full h-16 resize-none overflow-hidden text-sm text-gray-700 rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                    </textarea>
                </div>
            </template> -->
        </Column>

        <Column field="price" header="Price" style="max-width: 125px;">
            <template #body="{ data }">
                <div class="whitespace-nowrap">
                    {{ locale.currencyFormat(data.currency_code, data.price) }}
                </div>
            </template>
        </Column>

        <Column field="description" header="description"></Column>
    </DataTable>
</template>