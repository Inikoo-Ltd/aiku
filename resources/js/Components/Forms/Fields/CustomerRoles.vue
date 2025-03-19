<script setup lang="ts">
import { get } from 'lodash-es'
import { reactive, watch, ref, watchEffect } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLock } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faLock)

const props = defineProps<{
    form?: any
    fieldName: any
    fieldData?: {
        placeholder?: string
        required?: boolean
        mode?: string
		searchable?: boolean
    }
}>()

const optionsRoles1 = ref(
{
    label: 'Group admin',
    name: 'group-admin',
    value: props.form[props.fieldName].includes("group-admin"),
    disabled: false
})

const optionsRoles2 = ref(
    {
        label: 'Portfolio',
        name: 'portfolio',
        value: props.form[props.fieldName].includes("portfolio"),
        disabled: false
    }
)

const optionsRoles3 = reactive([
    {
        label: 'Banners',
        name: 'banners',
        value: props.form[props.fieldName].includes("group-admin"),
        disabled: false
    },
    {
        label: 'Social',
        name: 'social',
        value: props.form[props.fieldName].includes("social"),
        disabled: false
    },
    {
        label: 'SEO',
        name: 'seo',
        value: props.form[props.fieldName].includes("seo"),
        disabled: false
    },
    {
        label: 'PPC',
        name: 'ppc',
        value: props.form[props.fieldName].includes("ppc"),
        disabled: false
    },
    {
        label: 'Prospects',
        name: 'prospects',
        value: props.form[props.fieldName].includes("prospects"),
        disabled: false
    }
])

watch(() => optionsRoles1.value.value, () => {
    optionsRoles1.value.value ? optionsRoles2.value.value = true : ''
})

watch(() => optionsRoles2.value.value, () => {
    optionsRoles2.value.value ? optionsRoles3.map(item => item.value = true) : ''
})

watchEffect(() => {
    props.form[props.fieldName] = optionsRoles1.value.value
        ? [optionsRoles1.value.name]
        : optionsRoles2.value.value
            ? [optionsRoles2.value.name]
            : optionsRoles3.filter(item => item.value === true).map(item => item.name)
})

// When checkbox is updated then clear the error
watch([optionsRoles1, optionsRoles2, optionsRoles3], () => {
    props.form.errors[props.fieldName] = ""
})
</script>

<template>
    <div>
        <div class="flex items-center justify-between">
            <table class="w-fit divide-y divide-red-300">
                <tbody class="">
                    <tr class="border-b border-gray-300">
                        <td class="">
                            <label :for="optionsRoles1.name"
                                class="whitespace-nowrap block py-2 pl-4 pr-3 text-sm font-medium cursor-pointer"
                                :class="[optionsRoles1.disabled ? 'text-gray-300' : 'text-gray-500 hover:text-gray-600']"
                            >
                                {{ optionsRoles1.label }}
                            </label>
                        </td>
                        <td class="whitespace-nowrap px-3 text-sm text-gray-500">
                            <input v-model="optionsRoles1.value" :id="optionsRoles1.name"
                                :name="optionsRoles1.name" type="checkbox"
                                :titles="`I'm Interested in ${optionsRoles1.label}`"
                                :disabled="optionsRoles1.disabled"
                                class="h-5 w-5 rounded cursor-pointer disabled:text-green-400 border-gray-300 hover:border-green-500 text-green-500 focus:ring-green-500"
                            />
                        </td>
                    </tr>
                    <tr class="border-b border-gray-300">
                        <td class="">
                            <label :for="optionsRoles2.name"
                                class="whitespace-nowrap block py-2 pl-4 pr-3 text-sm font-medium cursor-pointer"
                                :class="[optionsRoles1.value ? 'text-gray-400' : 'text-gray-500 hover:text-gray-600']"
                            >
                                {{ optionsRoles2.label }}
                            </label>
                        </td>
                        <td class="whitespace-nowrap px-3 text-sm text-gray-500">
                            <div class="flex items-center gap-x-2">
                                <input v-model="optionsRoles2.value" :id="optionsRoles2.name"
                                    :name="optionsRoles2.name" type="checkbox"
                                    :titles="`I'm Interested in ${optionsRoles2.label}`"
                                    :disabled="optionsRoles1.value"
                                    class="h-5 w-5 rounded cursor-pointer disabled:text-green-400 border-gray-300 hover:border-green-500 text-green-500 focus:ring-green-500"
                                />
                                <FontAwesomeIcon v-if="optionsRoles1.value" icon='fal fa-lock' class='' aria-hidden='true' />
                            </div>
                        </td>
                    </tr>
                    <tr v-for="(option, index) in optionsRoles3" :key="index">
                        <td class="">
                            <label :for="option.name"
                                class="whitespace-nowrap block py-2 pl-4 pr-3 text-sm font-medium cursor-pointer"
                                :class="[optionsRoles2.value ? 'text-gray-400' : 'text-gray-500 hover:text-gray-600']"
                            >
                                {{ option.label }}
                            </label>
                        </td>
                        <td class="whitespace-nowrap px-3 text-sm text-gray-500 text-center">
                            <div class="flex items-center gap-x-2">
                                <input v-model="option.value" :id="option.name"
                                    :name="option.name" type="checkbox"
                                    :titles="`I'm Interested in ${option.label}`"
                                    :disabled="optionsRoles2.value"
                                    class="h-5 w-5 rounded cursor-pointer disabled:text-green-400 border-gray-300 hover:border-green-500 text-green-500 focus:ring-green-500"
                                />
                                <FontAwesomeIcon v-if="optionsRoles2.value" icon='fal fa-lock' class='' aria-hidden='true' />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Icon: Error, Success, Processing -->
            <div class="mr-2 h-full flex items-center pointer-events-none">
                <FontAwesomeIcon v-if="get(form, ['errors', `${fieldName}`])" icon="fas fa-exclamation-circle"
                    class="h-5 w-5 text-red-500" aria-hidden="true" />
                <FontAwesomeIcon v-if="form.recentlySuccessful" icon="fas fa-check-circle"
                    class="h-5 w-5 text-green-500" aria-hidden="true" />
                <FontAwesomeIcon v-if="form.processing" icon="fad fa-spinner-third"
                    class="h-5 w-5 animate-spin" />
            </div>
        </div>
        
        <!-- Errors description -->
        <div class="mr-2  text-red-500">
            {{ get(form, ['errors', `${fieldName}`]) }}
        </div>
    </div>
</template>
