<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Tue, 11 Nov 2025 09:04:52 Western Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
    import Table from "@/Components/Table/Table.vue";
    import { useLocaleStore } from "@/Stores/locale";
    import { useFormatTime } from "@/Composables/useFormatTime";
    import Icon from "@/Components/Icon.vue";
    import { library } from "@fortawesome/fontawesome-svg-core";
    import { faCheckCircle, faCircle, faQuestionCircle } from "@fal";
    import { Link } from "@inertiajs/vue3";

    library.add(faCircle, faCheckCircle, faQuestionCircle);

    const props = defineProps<{
        data: object;
        tab?: string;
    }>()

    const locale = useLocaleStore();
    
    function productRoute(asset_id: string) {
        if (!asset_id) {
            return ""
        }

        return route("grp.helpers.redirect_asset", [asset_id])
    }
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(reference)="{ item }">
            <Link :href="route('grp.org.shops.show.dashboard.invoices.show', { organisation: item.organisation_slug, shop: item.shop_slug, invoice: item.slug })" class="primaryLink">
                {{ item.reference }}
            </Link>
        </template>

        <template #cell(product_asset)="{ item }">
            <Link :href="productRoute(item.product_asset)" class="primaryLink">
                {{ item.product_code }} [<span class="font-bold">{{(item.shop_slug).toUpperCase()}}</span>]
            </Link>
        </template>

        <template #cell(customer_name)="{ item }">
            <Link :href="route('grp.org.shops.show.crm.customers.show', { organisation: item.organisation_slug, shop: item.shop_slug, customer: item.customer_slug })" class="primaryLink">
                {{ item.customer_name }}
            </Link>
        </template>

        <template #cell(date)="{ item }">
            <div class="text-gray-500 text-right">
                {{ useFormatTime(item.date, { localeCode: locale.language.code, formatTime: "aiku" }) }}
            </div>
        </template>

        <template #cell(pay_status)="{ item }">
            <div class="text-center">
                <Icon :data="item.pay_status" />
            </div>
        </template>

        <template #cell(total_sales)="{ item }">
            <div :class="item.total_sales >= 0 ? 'text-gray-500' : 'text-red-400'">
                {{ useLocaleStore().currencyFormat(item.currency_code, item.total_sales) }}
            </div>
        </template>
    </Table>
</template>
