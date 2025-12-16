<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { Head } from '@inertiajs/vue3'
import { PageHeadingTypes } from "@/types/PageHeading"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { inject } from 'vue'


import DataTable from "primevue/datatable"
import Column from "primevue/column"


defineProps<{
    title: string
    pageHead : PageHeadingTypes
    assets: {

    }[]
    currency: {
        data: {
            code: string
        }
    }
}>()

const locale = inject('locale', {})


</script>

<template>
    <Head :title="title" />
    <PageHeading :data="pageHead">
	</PageHeading>

    <div class="px-4 py-5 md:px-6 lg:px-8 ">
        <div class="mt-4">
            <div class="text-2xl font-semibold text-gray-600">
                {{ trans("Goods, Service, and Rentals") }}
            </div>

            <!-- <pre>{{ assets }}</pre> -->
            
            <DataTable :value="assets" stripedRows removableSort paginator :rows="10" :rowsPerPageOptions="[5, 10, 15]" tableStyle="min-width: 30rem" class="border border-gray-300 rounded-md">
                <Column field="name" sortable header="Name"></Column>
                <Column field="type" sortable header="Type"></Column>
                <Column field="price" sortable headerClass="flex justify-end">
                    <template #header>
                        <div class="flex justify-end items-end">
                            <span class="font-bold text-right">{{ trans("Price") }}</span>
                        </div>
                    </template>

                    <template #body="{ data }">
                        <div class="flex justify-end relative">
                            <span class="">{{ locale.currencyFormat(currency.data.code, data.price) }}</span>
                            <!-- <span class="ml-1 font mr-1">{{ locale.currencyFormat(currency.code, data.agreed_price) }}</span> -->
                            <Tag :label="data.percentage_off + '%'" :theme="3" noHoverColor />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </div>
</template>
