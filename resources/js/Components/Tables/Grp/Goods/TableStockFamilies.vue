<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 24 Mar 2024 21:16:55 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import Table from '@/Components/Table/Table.vue';
import { StockFamily } from "@/types/stock-family";
import { computed, inject } from 'vue';
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

defineProps<{
    data: object,
    tab?: string
}>()

const locale = inject('locale', aikuLocaleStructure)

const interval = computed(() => {
    const url = usePage().url;
    const params = new URLSearchParams(url.split('?')[1]);
    return params.get('dateInterval') ?? 'all';
});


function stockFamilyRoute(stockFamily: StockFamily) {
            return route('grp.goods.stock-families.show', [
                stockFamily.slug,
                stockFamily.slug
            ])

}

</script>



<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: stockFamily }">
            <Link :href="stockFamilyRoute(stockFamily)" class="primaryLink">
            {{ stockFamily['code'] }}
            </Link>
        </template>
        <template #cell(name)="{ item: stockFamily }">
            {{ stockFamily['name'] }}
        </template>
        <template #cell(number_stocks)="{ item: stockFamily }">
            <Link :href="route('grp.goods.stock-families.show.stocks.index', stockFamily['slug'])">
            {{ stockFamily['number_stocks'] }}
            </Link>
        </template>
        <template #cell(revenue_grp_currency)="{ item: stockFamily }">
            {{
            locale.currencyFormat(
              stockFamily["grp_currency"],
              Number(stockFamily["revenue_grp_currency_" + interval])
            )
          }}
        </template>
    </Table>
</template>
