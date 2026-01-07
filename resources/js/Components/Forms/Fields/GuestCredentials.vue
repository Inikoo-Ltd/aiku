<script setup lang="ts">
import { ref } from 'vue'
import Combobox from '@/Components/Forms/Fields/Combobox.vue'
import Input from '@/Components/Forms/Fields/Input.vue'

const props = defineProps<{
    form: any,
    fieldName: string,
    options?: any,
    fieldData?: {
        placeholder: string
        required: boolean
        mode: string
    }
}>()

const userType = ref('Existing User')

</script>

<template>
    <div class="flex flex-col gap-y-2">
        <!-- Button: Radio -->
        <div class="flex gap-x-8">
            <div class="flex items-center">
                <input v-model="userType" id="new-user" key="" name="guest-credentials" type="radio" value="New User"
                    :checked="userType == 'New User'"
                    class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-0 focus:outline-hidden focus:ring-transparent cursor-pointer" />
                <label for="new-user" class="ml-2 block text-sm font-medium leading-6 text-gray-600 cursor-pointer">
                    New User
                </label>
            </div>
            <div class="flex items-center">
                <input v-model="userType" id="existing-user" key="" name="guest-credentials" type="radio"
                    value="Existing User" :checked="userType == 'Existing User'"
                    class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-0 focus:outline-hidden focus:ring-transparent cursor-pointer" />
                <label for="existing-user" class="ml-2 block text-sm font-medium leading-6 text-gray-600 cursor-pointer">
                    Existing User
                </label>
            </div>
        </div>

        <!-- Conditional: Input-Combobox -->
        <div v-if="userType == 'New User'" class="relative">
            <Input :form="form" :fieldName="fieldName" :fieldData="fieldData" />
        </div>
        <div v-else>
            <Combobox :form="form" :fieldName="fieldName" :fieldData="fieldData" />
        </div>
    </div>

    <p v-if="form.errors[fieldName]" class="mt-2 text-sm text-red-600" id="email-error">{{ form.errors[fieldName] }}</p>
</template>

<style scoped></style>