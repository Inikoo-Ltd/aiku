<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faIndustry, faDownload, faCalendar, faFileExcel } from "@fal";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { ref, computed } from "vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { capitalize } from "@/Composables/capitalize";
import Calendar from "primevue/calendar";

library.add(faIndustry, faDownload, faCalendar, faFileExcel);

const props = defineProps<{
    title: string;
    pageHead: any;
    downloadRoute: {
        name: string;
        parameters: any;
    };
}>();

const now = new Date();
const currentYear = now.getFullYear();
const currentMonth = now.getMonth();
const currentQuarter = Math.floor(currentMonth / 3);

const startDate = ref(new Date(currentYear - 1, 0, 1));
const endDate = ref(new Date(currentYear - 1, 11, 31));

const isDownloading = ref(false);

const formatDate = (date: Date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
};

const downloadUrl = computed(() => {
    return route(props.downloadRoute.name, {
        ...props.downloadRoute.parameters,
        start_date: formatDate(startDate.value),
        end_date: formatDate(endDate.value),
    });
});

const handleDownload = () => {
    isDownloading.value = true;
    window.location.href = downloadUrl.value;
    setTimeout(() => {
        isDownloading.value = false;
    }, 3000);
};

const setDateRange = (range: string) => {
    const year = currentYear;
    const lastYear = currentYear - 1;
    const month = currentMonth;

    switch (range) {
        case "current-month":
            startDate.value = new Date(year, month, 1);
            endDate.value = new Date(year, month + 1, 0);
            break;
        case "last-month":
            startDate.value = new Date(year, month - 1, 1);
            endDate.value = new Date(year, month, 0);
            break;
        case "q1":
            startDate.value = new Date(year, 0, 1);
            endDate.value = new Date(year, 2, 31);
            break;
        case "q2":
            startDate.value = new Date(year, 3, 1);
            endDate.value = new Date(year, 5, 30);
            break;
        case "q3":
            startDate.value = new Date(year, 6, 1);
            endDate.value = new Date(year, 8, 30);
            break;
        case "q4":
            startDate.value = new Date(year, 9, 1);
            endDate.value = new Date(year, 11, 31);
            break;
        case "ytd":
            startDate.value = new Date(year, 0, 1);
            endDate.value = new Date();
            break;
        case "last-year":
            startDate.value = new Date(lastYear, 0, 1);
            endDate.value = new Date(lastYear, 11, 31);
            break;
        case "full-year":
            startDate.value = new Date(year, 0, 1);
            endDate.value = new Date(year, 11, 31);
            break;
    }
};

const presets = computed(() => {
    const list = [];
    list.push({ label: "Current Month", value: "current-month" });
    list.push({ label: "Last Month", value: "last-month" });
    for (let q = 1; q <= 4; q++) {
        if (q <= currentQuarter + 1) {
            list.push({ label: `Q${q} ${currentYear}`, value: `q${q}` });
        }
    }
    list.push({ label: "Year to Date", value: "ytd" });
    list.push({ label: `${currentYear - 1} (Full Year)`, value: "last-year" });
    if (currentMonth === 11) {
        list.push({ label: `${currentYear} (Full Year)`, value: "full-year" });
    }
    return list;
});

const selectedRangeText = computed(() => {
    return `${formatDate(startDate.value)} to ${formatDate(endDate.value)}`;
});
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="max-w-5xl mx-auto px-4 py-6">
        <div class="space-y-5">
            <!-- Description -->
            <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-lg p-5">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 bg-slate-100 shadow ring-1 ring-slate-200 h-10 w-10 rounded-md flex justify-center items-center">
                        <FontAwesomeIcon :icon="['fal', 'industry']" class="text-xl text-gray-500" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-base font-semibold text-gray-900 mb-1.5">
                            UK Manufacturing Survey
                        </h2>
                        <p class="text-sm text-gray-600 mb-3">
                            Export an Excel report of sales grouped by 6-digit tariff code for submission to the ONS UK Manufacturing Survey.
                        </p>
                        <div class="text-xs text-gray-500 space-y-1">
                            <p class="font-medium">Sheets included:</p>
                            <ul class="list-disc list-inside ml-2 space-y-0.5">
                                <li>All Data – full breakdown by tariff code and product</li>
                                <li>Manufactured – goods produced by AW Aromatics</li>
                                <li>Merchanted – goods purchased for resale without processing</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Date Range -->
            <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-lg p-5">
                <div class="flex items-center gap-2 mb-4">
                    <FontAwesomeIcon :icon="['fal', 'calendar']" class="text-gray-400 text-sm" />
                    <h3 class="text-sm font-semibold text-gray-900">Select Date Range</h3>
                </div>

                <div class="mb-5">
                    <label class="block text-xs font-medium text-gray-700 mb-2">Quick Presets</label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="preset in presets"
                            :key="preset.value"
                            @click="setDateRange(preset.value)"
                            class="px-2.5 py-1.5 text-xs bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 rounded transition-colors focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-1"
                        >
                            {{ preset.label }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="start-date" class="block text-xs font-medium text-gray-700 mb-1.5">
                            Start Date
                        </label>
                        <Calendar
                            v-model="startDate"
                            inputId="start-date"
                            dateFormat="dd MM yy"
                            :showIcon="true"
                            iconDisplay="input"
                            class="w-full"
                        />
                    </div>
                    <div>
                        <label for="end-date" class="block text-xs font-medium text-gray-700 mb-1.5">
                            End Date
                        </label>
                        <Calendar
                            v-model="endDate"
                            inputId="end-date"
                            dateFormat="dd MM yy"
                            :showIcon="true"
                            iconDisplay="input"
                            class="w-full"
                        />
                    </div>
                </div>

                <div class="p-2.5 bg-slate-50 border border-slate-200 rounded">
                    <p class="text-xs text-gray-700">
                        <span class="font-medium">Selected Range:</span>
                        {{ selectedRangeText }}
                    </p>
                </div>
            </div>

            <!-- Download Button -->
            <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-lg p-5">
                <Button
                    @click="handleDownload"
                    :disabled="isDownloading"
                    :style="'primary'"
                    size="lg"
                    :full="true"
                >
                    <template v-if="!isDownloading">
                        <FontAwesomeIcon :icon="['fal', 'file-excel']" class="mr-2" />
                        Download Excel Report
                    </template>
                    <template v-else>
                        <FontAwesomeIcon :icon="['fal', 'download']" class="mr-2 animate-bounce" />
                        Generating Report...
                    </template>
                </Button>
                <p class="mt-3 text-center text-xs text-gray-500">
                    Report will be downloaded as an Excel file (.xlsx) with 3 sheets
                </p>
            </div>
        </div>
    </div>
</template>
