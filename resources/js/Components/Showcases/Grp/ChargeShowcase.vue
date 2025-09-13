<script setup lang="ts">
import { defineProps, computed, inject } from 'vue';
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faTag, faInfoCircle, faCalendarAlt, faPoundSign, faCog, faRuler, faHashtag } from "@fal";
import { faCheckCircle, faTimesCircle } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { trans } from "laravel-vue-i18n";
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure';

library.add(faTag, faInfoCircle, faCalendarAlt, faPoundSign, faCog, faRuler, faHashtag, faCheckCircle, faTimesCircle);

interface ChargeData {
    id: number;
    slug: string;
    code: string;
    name: string;
    label?: string;
    description?: string;
    state: string;
    created_at: string;
    updated_at: string;
    amount: string;
    currency_code: string;
    settings: {
        rules: string;
        amount: string;
        rule_subject: string;
    };
}

const props = defineProps<{
    data?: {
        charge: ChargeData
    }
}>()

const locale = inject('locale', aikuLocaleStructure)

// Computed properties for better data handling
const isActive = computed(() => props.data?.charge.state === 'active');
const formattedAmount = computed(() => {
    if (!props.data) return '';
    return locale.currencyFormat(props.data.charge.currency_code, +props.data.charge.amount);
});

const formattedDate = computed(() => {
    if (!props.data?.charge.created_at) return '';
    return new Date(props.data.charge.created_at).toLocaleDateString();
});

const formattedUpdatedDate = computed(() => {
    if (!props.data?.charge.updated_at) return '';
    return new Date(props.data.charge.updated_at).toLocaleDateString();
});

const stateColor = computed(() => {
    return isActive.value ? 'text-green-600' : 'text-red-600';
});

const stateBgColor = computed(() => {
    return isActive.value ? 'bg-green-100' : 'bg-red-100';
});
</script>

<template>
    <div class="p-4">
        <div v-if="data.charge" class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0">
                            <FontAwesomeIcon :icon="faTag" class="w-8 h-8 text-blue-600" />
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">{{ data.charge.name }}</h2>
                            <p class="text-sm text-gray-600">{{ data.charge.code }}</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span
                            :class="[stateBgColor, stateColor, 'px-3 py-1 rounded-full text-sm font-medium flex items-center']">
                            <FontAwesomeIcon :icon="isActive ? faCheckCircle : faTimesCircle" class="w-4 h-4 mr-1" />
                            {{ data.charge.state.charAt(0).toUpperCase() + data.charge.state.slice(1) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="p-6">
                <!-- Basic Information Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Left Column -->
                    <div class="space-y-4">
                        <!-- ID & Slug -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h3 class="font-semibold text-gray-700 mb-3 flex items-center">
                                <FontAwesomeIcon :icon="faHashtag" class="w-4 h-4 mr-2 text-gray-500" />
                                {{ trans('Identification') }}
                            </h3>
                            <div class="space-y-2">
                                <!-- <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">ID:</span>
                                    <span class="text-sm font-medium text-gray-900">#{{ data.charge.id }}</span>
                                </div> -->
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Slug:</span>
                                    <span class="font-mono text-gray-900 bg-gray-100 px-2 py-1 rounded">{{
                                        data.charge.slug }}</span>
                                </div>
                            </div>
                        </div>


                    </div>

                    <!-- Right Column -->
                    <div class="space-y-4">
                        <!-- Amount Information -->
                        <div class="bg-green-50 rounded-lg p-4">
                            <h3 class="font-semibold text-gray-700 mb-3 flex items-center">
                                {{ trans('Amount') }}
                            </h3>
                            <div class="text-base font-bold text-green-600">
                                {{ formattedAmount }}
                            </div>
                        </div>

                        <!-- Label (if exists) -->
                        <div v-if="data.charge.label" class="bg-purple-50 rounded-lg p-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                <FontAwesomeIcon :icon="faTag" class="w-4 h-4 mr-2 text-purple-600" />
                                {{ trans('Label') }}
                            </h3>
                            <span
                                class="inline-block bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-medium">
                                {{ data.charge.label }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Description Section -->
                <div v-if="data.charge.description" class="mb-6">
                    <div class="bg-yellow-50 rounded-lg p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                            <FontAwesomeIcon :icon="faInfoCircle" class="w-4 h-4 mr-2 text-yellow-600" />
                            {{ trans('Description') }}
                        </h3>
                        <p class="text-sm text-gray-700 leading-relaxed">{{ data.charge.description }}</p>
                    </div>
                </div>

                <!-- Settings Section -->
                <div v-if="data.charge.settings" class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center">
                        <FontAwesomeIcon :icon="faCog" class="w-4 h-4 mr-2 text-gray-600" />
                        {{ trans('Settings & Rules') }}
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-white rounded-lg p-3 border border-gray-200">
                            <div class="text-xs text-gray-500 uppercase tracking-wide font-semibold mb-1">Rules</div>
                            <div class="text-sm font-mono text-gray-900 bg-gray-100 px-2 py-1 rounded min-h-6">
                                {{ data.charge.settings.rules }}
                            </div>
                        </div>
                        <div class="bg-white rounded-lg p-3 border border-gray-200">
                            <div class="text-xs text-gray-500 uppercase tracking-wide font-semibold mb-1">Rule Subject
                            </div>
                            <div class="text-sm text-gray-900">
                                {{ data.charge.settings.rule_subject }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="bg-gray-50 rounded-lg border-2 border-dashed border-gray-300 p-8 text-center">
            <FontAwesomeIcon :icon="faInfoCircle" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ trans('No Data Available') }}</h3>
            <p class="text-gray-500">{{ trans('No charge information to display') }}</p>
        </div>
    </div>
</template>