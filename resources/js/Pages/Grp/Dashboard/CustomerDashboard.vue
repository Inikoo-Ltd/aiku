<script setup lang="ts">
import { Chart as ChartJS, ArcElement, Tooltip, Legend, Colors } from "chart.js";
import { Pie } from "vue-chartjs";
import { trans } from "laravel-vue-i18n";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faUsers, faUserCheck, faUserSlash, faUserPlus, faMoneyBillWave } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { useLocaleStore } from "@/Stores/locale";
import { capitalize } from "@/Composables/capitalize";
import { computed, onMounted, onUnmounted, ref } from "vue";
import { Link } from "@inertiajs/vue3"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(faUsers, faUserCheck, faUserSlash, faUserPlus, faMoneyBillWave);

ChartJS.register(ArcElement, Tooltip, Legend, Colors);

const locale = useLocaleStore();

// Props definition
const props = defineProps<{
    data: {
        prospectStats: {
            customers: {
                label: string;
                count: number;
                cases: {
                    [key: string]: {
                        value: string;
                        count: number;
                        label: string;
                        icon: {
                            icon: string | string[];
                            tooltip: string;
                            class: string;
                            color: string;
                        };
                    };
                };
            };
        };
    };
}>();

// Reactive transformation of props for easier template usage
const customerStats = computed(() => {
    const customers = props.data.prospectStats.customers;
    return {
        label: customers.label,
        count: customers.count,
        cases: Object.values(customers.cases).map((caseItem) => ({
            value: caseItem.value,
            count: caseItem.count,
            label: caseItem.label,
            route: caseItem.route,
            icon: {
                icon: caseItem.icon.icon,
                tooltip: caseItem.icon.tooltip,
                class: caseItem.icon.class,
                color: caseItem.icon.color,
            },
        })),
    };
});

// Chart options
const options = {
    responsive: true,
    plugins: {
        legend: { display: false },
        tooltip: {
            titleFont: { size: 10, weight: "lighter" },
            bodyFont: { size: 11, weight: "bold" },
        },
    },
};

// Listener for backend updates
onMounted(() => {
    window.Echo.private("customer.general").listen(".customers.dashboard", (e) => {
        if (e.data.customers) {
            customerStats.value.count = e.data.customers.count;
        }
        if (e.data.customers?.cases) {
            Object.keys(e.data.customers.cases).forEach((key) => {
                const updatedCase = customerStats.value.cases.find((c) => c.value === key);
                if (updatedCase) {
                    updatedCase.count = e.data.customers.cases[key].count;
                }
            });
        }
    });
});

onUnmounted(() => {
    window.Echo.private("customer.general").stopListening(".customers.dashboard");
});

const isLoadingVisit = ref<number | null>(null)
</script>

<template>
    <div class="px-6">
        <dl class="mt-5 grid grid-cols-1 md:grid-cols-3 gap-x-2 gap-y-3">
            <div
                class="px-4 py-5 sm:p-6 rounded-lg bg-white shadow tabular-nums">
                <dt class="text-base font-medium text-gray-400">
                    {{ customerStats.label }}
                </dt>
                <dd class="mt-2 flex justify-between gap-x-2">
                    <div
                        class="flex flex-col gap-x-2 gap-y-3 leading-none items-baseline text-2xl font-semibold text-org-500">
                        <!-- Total Count -->
                        <div class="flex gap-x-2 items-end">
                            {{ locale.number(customerStats.count) }}
                            <span class="text-sm font-medium leading-4 text-gray-500">
                                {{ trans("in total") }}
                            </span>
                        </div>

                        <!-- Case Breakdown -->
                        <div
                            class="text-sm text-gray-500 flex gap-x-5 gap-y-1 items-center flex-wrap">
                            <template v-for="(dCase, idxCase) in customerStats.cases" :key="dCase.value">
                                <component
                                    :is="dCase.route?.name ? Link : 'div'"
                                    :href="dCase.route?.name ? route(dCase.route.name, dCase.route.parameters) : null"
                                    :class="dCase.route?.name ? 'hover:bg-gray-200 px-1 py-0.5 rounded' : ''"
                                    class="flex gap-x-0.5 items-center font-normal"
                                    v-tooltip="capitalize(dCase.icon.tooltip)"
                                    @start="() => isLoadingVisit = idxCase"
                                    @finish="() => isLoadingVisit = null"
                                >
                                    <LoadingIcon v-if="isLoadingVisit === idxCase" class="text-gray-500" />
                                    <FontAwesomeIcon
                                        v-else
                                        :icon="dCase.icon.icon"
                                        :class="dCase.icon.class"
                                        fixed-width
                                        :title="dCase.icon.tooltip"
                                        aria-hidden="true" />
                                    <span class="font-semibold">{{ locale.number(dCase.count) }}</span>
                                </component>
                            </template>
                        </div>
                    </div>

                    <!-- Pie Chart -->
                    <div class="w-20">
                        <Pie
                            :data="{
                                labels: customerStats.cases.map((c) => c.label),
                                datasets: [
                                    {
                                        data: customerStats.cases.map((c) => c.count),
                                        hoverOffset: 4,
                                    },
                                ],
                            }"
                            :options="options" />
                    </div>
                </dd>
            </div>
        </dl>
    </div>
</template>

