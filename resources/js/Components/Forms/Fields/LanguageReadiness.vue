<script setup lang="ts">
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

defineProps<{
    form?: any
    fieldName: string
    fieldData: {
        value: { code: string, language: string, names: number, descriptions: number, products: number, category_names: number, category_descriptions: number, categories: number }[]
    }
}>()

const locale = useLocaleStore()
const percent = (count: number, total: number) => total ? `${(100 * count / total).toFixed(1)}%` : "—"
</script>

<template>
    <DataTable :value="fieldData.value" size="small" class="w-full text-sm tabular-nums" :pt="{ thead: { class: 'whitespace-nowrap' } }">
        <Column field="language" :header="ctrans('Language')" />
        <Column :header="ctrans('With name')" headerClass="[&>div]:justify-end" bodyClass="text-right whitespace-nowrap">
            <template #body="{ data }">
                {{ locale.number(data.names) }} <span class="inline-block w-14 text-gray-400">{{ percent(data.names, data.products) }}</span>
            </template>
        </Column>
        <Column :header="ctrans('With description')" headerClass="[&>div]:justify-end" bodyClass="text-right whitespace-nowrap">
            <template #body="{ data }">
                {{ locale.number(data.descriptions) }} <span class="inline-block w-14 text-gray-400">{{ percent(data.descriptions, data.products) }}</span>
            </template>
        </Column>
        <Column :header="ctrans('Categories with name')" headerClass="[&>div]:justify-end" bodyClass="text-right whitespace-nowrap">
            <template #body="{ data }">
                {{ locale.number(data.category_names) }} <span class="inline-block w-14 text-gray-400">{{ percent(data.category_names, data.categories) }}</span>
            </template>
        </Column>
        <Column :header="ctrans('Categories with description')" headerClass="[&>div]:justify-end" bodyClass="text-right whitespace-nowrap">
            <template #body="{ data }">
                {{ locale.number(data.category_descriptions) }} <span class="inline-block w-14 text-gray-400">{{ percent(data.category_descriptions, data.categories) }}</span>
            </template>
        </Column>
        <template v-if="fieldData.value.length" #footer>
            <span class="text-xs font-normal text-gray-500">{{ ctrans(":count active products", { count: locale.number(fieldData.value[0].products) }) }} · {{ ctrans(":count live departments, sub-departments, families and collections", { count: locale.number(fieldData.value[0].categories) }) }}</span>
        </template>
        <template #empty>{{ ctrans("No active products") }}</template>
    </DataTable>
</template>
