<script setup lang="ts">
import { trans } from "laravel-vue-i18n";

defineProps<{
    packers: Array<{ user: { contact_name: string, email: string }, count: number }>;
    pickers: Array<{ user: { contact_name: string, email: string }, count: number }>;
}>();
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 px-4">
        <!-- Top Packers -->
        <div class="bg-white p-4 rounded-lg shadow border" v-if="packers && packers.length > 0">
            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                <i class="fal fa-box-open text-gray-500"></i>
                {{ trans('Top Packers') }}
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2">{{ trans('Name') }}</th>
                            <th scope="col" class="px-4 py-2 text-right">{{ trans('Packed') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(packer, index) in packers" :key="index" class="bg-white border-b hover:bg-gray-50">
                            <td class="px-4 py-2 font-medium text-gray-900 whitespace-nowrap">
                                {{ packer.user.contact_name || packer.user.email }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ packer.count }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Pickers -->
        <div class="bg-white p-4 rounded-lg shadow border" v-if="pickers && pickers.length > 0">
            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                <i class="fal fa-hand-holding-box text-gray-500"></i>
                {{ trans('Top Pickers') }}
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2">{{ trans('Name') }}</th>
                            <th scope="col" class="px-4 py-2 text-right">{{ trans('Picked') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(picker, index) in pickers" :key="index" class="bg-white border-b hover:bg-gray-50">
                            <td class="px-4 py-2 font-medium text-gray-900 whitespace-nowrap">
                                {{ picker.user.contact_name || picker.user.email }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ picker.count }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
