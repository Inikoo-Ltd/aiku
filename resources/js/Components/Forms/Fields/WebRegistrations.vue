<script setup lang="ts">
import { watch } from 'vue'
const props = defineProps<{
    form: any,
    fieldName: string,
    options?: any,
    fieldData?: {
        placeholder: string
        options: [{
            key: string
            name: string
            show: boolean
            required: boolean
            label: string
        }]
    }
}>()

// Helper to conditionally value for 'terms and conditions' 
watch(props.form[props.fieldName], (newData) => {
    newData.forEach((item) => {
        if (item.show === false) {
            item.required = false;
        }
        if (item.key == 'terms_and_conditions' && item.show === true) {
            item.required = true
        }
    });
});

</script>

<template>
    <div class="h-full">
        <!-- Component List -->
        <div class="overflow-x-auto ">
            <div class="inline-block min-w-full py-2 align-middle pr-4">
                <table class="min-w-full divide-y divide-gray-300 border-b border-gray-200">
                    <thead>
                        <tr class="text-gray-700">
                            <th scope="col" class="py-2 pl-6 pr-3 text-left text-sm font-semibold">
                                Components
                            </th>
                            <th scope="col" class="px-3 py-2 text-center text-sm font-semibold lg:table-cell">
                                Show
                            </th>
                            <th scope="col" class="px-3 py-2 text-center text-sm font-semibold sm:table-cell">
                                Required
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white text-gray-600 last:border-b last:border-gray-200">
                        <tr v-for="(component, index) in fieldData.options" :key="index">
                            <td class="whitespace-nowrap py-1.5 pl-4 pr-3 text-sm sm:pl-6 capitalize">
                                {{ component.name }}
                            </td>
                            <td class="px-3 py-1.5 text-sm text-gray-500 text-center">
                                <input v-model="form[fieldName][index].show" :id="component.label + index" type="checkbox"
                                    class="w-4 h-4 text-indigo-600 bg-gray-50 border-gray-300 rounded-sm cursor-pointer focus:ring-indigo-500 focus:ring-1">
                            </td>
                            <td class="px-3 py-1.5 text-sm text-gray-500 text-center">
                                <input v-model="form[fieldName][index].required" :id="component.label + index"
                                    type="checkbox"
                                    :disabled="form[fieldName][index].show ? component.key == 'terms_and_conditions' ? true : false : true"
                                    class="w-4 h-4 bg-white text-indigo-600 border-gray-300 rounded-sm cursor-pointer focus:ring-indigo-500 focus:ring-1 disabled:bg-gray-300 disabled:text-gray-300">
                            </td>
                        </tr>
                    </tbody>
                    <!-- {{ componentList }} -->
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped></style>