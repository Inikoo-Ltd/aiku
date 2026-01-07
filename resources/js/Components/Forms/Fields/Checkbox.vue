<script setup lang="ts">
const props = defineProps<{
    form?: any
    fieldName: any
    options: string[] | object
    fieldData?: {
        placeholder?: string
        required?: boolean
        mode?: string
		searchable?: boolean
    }
}>()
</script>

<template>
    <table>
        <thead>
            <tr class="text-gray-600 bg-gray-200">
                <th scope="col" class="py-3.5 pl-4 pr-3 text-left font-semibold tracking-wide">
                    Label
                </th>
                <th scope="col" class="px-3 py-3.5 font-semibold tracking-wide text-center">
                    Value
                </th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <tr v-for="(option, index) in form[fieldName]" :key="index">
                <td class="">
                    <label :for="`item-${index}`"
                        class="whitespace-nowrap block py-2 pl-4 pr-3 text-sm font-medium text-gray-500 hover:text-gray-600 cursor-pointer">
                        {{ option.label }}
                    </label>
                </td>
                <td class="whitespace-nowrap px-3 text-sm text-gray-500 text-center">
                    <input v-model="option.value" :id="`item-${index}`"
                        :name="`item-${index}`" type="checkbox"
                        :titles="`I'm Interested in ${option.label}`"
                        class="h-6 w-6 rounded-sm cursor-pointer border-gray-300 hover:border-gray-500 text-gray-600 focus:ring-gray-600" />
                </td>
            </tr>
        </tbody>
    </table>
</template>

<style scoped></style>