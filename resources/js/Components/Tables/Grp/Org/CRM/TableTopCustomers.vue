<script setup lang="ts">
import { ref, provide } from "vue"
import { trans } from "laravel-vue-i18n"
import { Link } from "@inertiajs/vue3"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import DashboardSettings from "@/Components/DataDisplay/Dashboard/DashboardSettings.vue"
import { RouteParams } from "@/types/route-params"

const props = defineProps<{
    data: {
        intervals: {
            options: Array<{ value: string; label: string }>;
            value: string;
            range_interval: string;
        };
        settings: {
            top_customers_limit: {
                id: string;
                value: number;
                options: Array<{ value: number; label: string; tooltip: string }>;
            };
        };
        topCustomers: Array<{
            id: number;
            slug: string;
            reference: string;
            name: string;
            last_invoiced_at: string | null;
            sales: number;
            invoices: number;
            shop_currency_code: string;
            shop_slug?: string;
            organisation_slug?: string;
        }>;
    };
    tab?: string;
}>()

const isLoading = ref(false)
provide("isLoadingOnTable", isLoading)

function customerUrl(customer: any): string {
    return route('grp.org.shops.show.crm.customers.show', {
        organisation: customer.organisation_slug || (route().params as RouteParams).organisation,
        shop: customer.shop_slug || (route().params as RouteParams).shop,
        customer: customer.slug,
    })
}

function formatCurrency(value: number, currencyCode: string): string {
    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: currencyCode || 'GBP',
        }).format(value)
    } catch (e) {
        return `${currencyCode || 'GBP'} ${value.toFixed(2)}`
    }
}
</script>

<template>
    <div class="w-full">
        <div class="mt-3">
            <DashboardSettings
                :intervals="props.data.intervals"
                :settings="props.data.settings"
                currentTab="top_customers"
            />
        </div>

        <div class="relative rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div v-if="isLoading" class="absolute inset-0 bg-white/50 rounded-lg flex items-center justify-center z-20">
                <LoadingIcon class="text-indigo-500 text-3xl" />
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 font-semibold uppercase tracking-wider text-left text-xs">
                            <th class="px-4 py-3 text-center w-12">#</th>
                            <th class="px-4 py-3">{{ trans('Customer') }}</th>
                            <th class="px-4 py-3">{{ trans('Reference') }}</th>
                            <th class="px-4 py-3 text-center">{{ trans('Invoices') }}</th>
                            <th class="px-4 py-3 text-center">{{ trans('Last Invoiced') }}</th>
                            <th class="px-4 py-3 text-right">{{ trans('Total Sales') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-if="!props.data.topCustomers || props.data.topCustomers.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-gray-400 italic">
                                {{ trans('No customer records found for this period') }}
                            </td>
                        </tr>
                        <tr
                            v-for="(customer, index) in props.data.topCustomers"
                            :key="customer.id"
                            class="hover:bg-gray-50/50 transition-colors duration-150"
                        >
                            <td class="px-4 py-3 text-center font-bold text-gray-400 w-12">
                                {{ index + 1 }}
                            </td>
                            <td class="px-4 py-3 font-semibold text-gray-900">
                                <Link :href="customerUrl(customer)" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                                    {{ customer.name }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-gray-500 font-mono text-xs">
                                {{ customer.reference || '—' }}
                            </td>
                            <td class="px-4 py-3 text-center font-medium text-gray-600">
                                {{ customer.invoices }}
                            </td>
                            <td class="px-4 py-3 text-center text-gray-500 text-xs">
                                {{ customer.last_invoiced_at || '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-indigo-950 font-mono">
                                {{ formatCurrency(customer.sales, customer.shop_currency_code) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
