<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationCircle, faCheckCircle, faAsterisk, faChevronDown } from '@fas'
import { faPhone } from '@fal'
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
import { isArray } from 'lodash-es'

import { ref, onMounted } from 'vue'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue'
import { getComponent } from '@/Composables/Listing/FieldFormList'  // Fieldform list

library.add(faExclamationCircle, faAsterisk, faCheckCircle, faPhone, faChevronDown)

const props = defineProps<{
    formData: {
        submitButton?: String
        fullLayout?: Boolean
        submitPosition?:String
        blueprint: {
            title?: string
            subtitle?: string
            icon?: string | string[]
            fields: any  // dynamic key
        }[]
        route: {
            name: string
            parameters?: Array<string>
        };
    }
}>()

let fields: any = {}
Object.entries(props.formData.blueprint).forEach(([, val]) => {
    Object.entries(val.fields).forEach(([fieldName, fieldData]: any) => {
        fields[fieldName] = fieldData.value
    })
})

const ButtonActive = ref(isArray(props.formData.route) ? props.formData.route[0] : null)
const form = useForm(
    props.formData.submitField // This seems to be a condition for choosing between two objects
        ? { ...fields, [props.formData.submitField]: 'active' } // This part executes if the condition is truthy
        : fields // This part executes if the condition is falsy
);

const isLoading = ref(false)
const handleFormSubmit = async () => {
    if (!props.formData.submitButton) {
        if (props.formData?.route?.body) {
            form
            .transform((data) => ({
                ...data,
                ...props.formData.route.body
            }))
            .post(route(
                props.formData.route.name,
                props.formData.route.parameters
            ), {
                onStart: () => isLoading.value = true,
                onError: () => isLoading.value = false
            })
        } else {
            form.post(route(
                props.formData.route.name,
                props.formData.route.parameters
            ), {
                onStart: () => isLoading.value = true,
                onError: () => isLoading.value = false
            })
        }
    } else {
        form.post(route(
            ButtonActive.value.name,
            ButtonActive.value.parameters
        ), {
            onStart: () => isLoading.value = true,
            onError: () => isLoading.value = false
        })
    }
}

const buttonRefs = ref([])
const tabActive: any = ref({})

const handleIntersection = (index: number) => (entries: any) => {
    const [entry] = entries
    tabActive.value[`${index}`] = entry.isIntersecting
}

onMounted(() => {
    // To indicate active state that on viewport
    buttonRefs.value.forEach((element: any, index) => {
        const observer = new IntersectionObserver(handleIntersection(index))
        observer.observe(element)

        // Clean up the observer when the component is unmounted
        element.cleanupObserver = () => {
            observer.disconnect()
        }
    })

    // Clean up all the observers when the component is unmounted
    return () => {
        buttonRefs.value.forEach((button: any) => button.cleanupObserver())
    }
})


const onSelectSubmitChange = (value) => {
    form[props.formData.submitField] = value.key
    ButtonActive.value = value
}

</script>

<template>

    <div class="bg-white divide-y divide-gray-200 lg:grid grid-flow-col lg:grid-cols-12 lg:divide-y-0 lg:divide-x">

        <!-- Left Tab: Left navigation -->
        <aside v-if="!formData.fullLayout" class="bg-gray-50 py-0 lg:col-span-3 lg:h-full">
            <div class="sticky top-16">
                <!-- <template v-for="(item, key) in formData['blueprint']">
                    <div v-if="item.title || item.icon" @click="jumpToElement(`field${key}`)" :class="[
                        tabActive[key]
                            ? 'navigationSecondActive'
                            : 'navigationSecond',
                        'cursor-pointer group border-l-4 px-3 py-2 flex items-center text-sm font-medium',
                    ]">
                        <FontAwesomeIcon v-if="item.icon" aria-hidden="true" class="flex-shrink-0 -ml-1 mr-3 h-6 w-6"
                            :class="[tabActive[key]
                                ? 'text-gray-400 group-hover:text-gray-500'
                                : 'text-gray-400',
                            ]"
                            :icon="item.icon" />
                        <span class="capitalize truncate">{{ item.title }}</span>
                    </div>
                </template> -->
            </div>
        </aside>

        <!-- Main form -->
        <form
            :class="['px-4 sm:px-6 gap-y-8 pb-8 divide-y divide-gray-200', formData.fullLayout ? 'col-span-12' : 'col-span-9']"
            @submit.prevent="handleFormSubmit">

            <Transition name="spin-to-down">
                <div v-if="usePage().props?.errors?.error_in_models" class="max-w-3xl mt-3 flex gap-x-1 items-center bg-red-500 w-full p-3 text-white rounded">
                    <FontAwesomeIcon v-if="usePage().props?.errors?.error_in_models?.match(/^(\d{3}):\s(.+)$/)?.[1] === '403'" icon='far fa-ban' class='text-lg' fixed-width aria-hidden='true' />
                    <div class="">{{ usePage().props.errors.error_in_models }}</div>
                </div>
            </Transition>


            <template v-for="(sectionData, sectionIdx ) in formData['blueprint']" :key="sectionIdx">
                <!-- If Section: all fields is not hidden -->
                <div v-if="!(Object.values(sectionData.fields).every((field: any) => field.hidden))"
                    class="relative py-4">
                    <!-- Helper: Section click -->
                    <div class="sr-only absolute -top-16" :id="`field${sectionIdx}`" />
                    <!-- Title -->
                    <div class="flex items-center gap-x-2" ref="buttonRefs">
                        <FontAwesomeIcon v-if="sectionData.icon" :icon='sectionData.icon' class=''
                            aria-hidden='true' />
                        <h3 v-if="sectionData.title" class="text-lg leading-6 font-medium text-gray-700 capitalize">
                            {{ sectionData.title }}
                        </h3>
                        <p v-if="sectionData.subtitle" class="max-w-2xl text-sm text-gray-500">
                            {{ sectionData.subtitle }}
                        </p>
                    </div>
                    <div class="mt-2 pt-4 sm:pt-5">
                        <template v-for="(fieldData, fieldName, index ) in sectionData.fields" :key="index">
                            <!-- If Field is not hidden = true -->
                            <dl v-if="!fieldData.hidden" class="mt-1 pb-4 sm:pb-5 sm:grid sm:grid-cols-3 sm:gap-4"
                                :class="fieldData.full ? '' : 'max-w-3xl'">
                                <!-- Title of Field -->
                                <dt class="text-sm font-medium text-gray-500" :class="fieldData.label_no_capitalize ? '' : 'capitalize'">
                                    <div class="inline-flex items-start leading-none">
                                        <span>{{ fieldData.label }}</span>
                                        <!-- Icon: Required -->
                                        <FontAwesomeIcon v-if="fieldData.required"
                                            :icon="['fas', 'asterisk']"
                                            class="ml-1 font-light text-[8px] text-red-400 mr-1 opacity-75" />
                                    </div>
                                </dt>
                                
                                <!-- Field (Full: to full the component field i.e create Prospects Mailshot) -->
                                <dd :class="fieldData.full ? 'sm:col-span-3' : 'sm:col-span-2'">
                                    <div class="mt-1 flex text-sm text-gray-700 sm:mt-0">
                                        <div class="relative flex-grow">
                                            <!-- Dynamic component -->
                                            <component :is="getComponent(fieldData['type'])" :form="form"
                                                :fieldName="fieldName" :options="fieldData['options']"
                                                :fieldData="fieldData" :key="index">
                                            </component>
                                        </div>
                                    </div>
                                </dd>
                            </dl>
                        </template>
                    </div>
                </div>
            </template>

            <!-- Button -->
            <div v-if="!formData?.submitPosition || formData?.submitPosition != 'top'" class="pt-5 flex justify-end">
                <Button
                    v-if="!formData.submitButton"
                    :loading="isLoading"
                    type="save"
                    @click="handleFormSubmit"
                    full
                />

                <div v-else-if="formData.submitButton == 'dropdown'" class="flex justify-center">
                    <Button :key="ButtonActive.key" type="submit" :disabled="form.processing"
                        class="rounded-r-none border-none" :style="'primary'" size="m" @click="handleFormSubmit">
                        {{ ButtonActive.label }}
                    </Button>

                    <Menu as="div" class="relative inline-block text-left">
                        <MenuButton as="template">
                            <Button icon="fas fa-chevron-down" :disabled="form.processing"
                                class="rounded-l-none border-none" :style="'tertiary'" size="l" />
                        </MenuButton>

                        <transition enter-active-class="transition duration-100 ease-out"
                            enter-from-class="transform scale-95 opacity-0"
                            enter-to-class="transform scale-100 opacity-100"
                            leave-active-class="transition duration-75 ease-in"
                            leave-from-class="transform scale-100 opacity-100"
                            leave-to-class="transform scale-95 opacity-0">
                            <MenuItems
                                class="absolute top-[-90px] right-0 mt-2 w-56 origin-top-right divide-y divide-gray-100 rounded-md bg-white shadow-lg ring-1 ring-black/5 focus:outline-none">
                                <div class="px-1 py-1">
                                    <div v-for="(item, index) in formData.route">
                                        <MenuItem>
                                        <div class="hover:bg-gray-50 group flex w-full items-center rounded-md px-2 py-2 text-sm cursor-pointer"
                                            @click="() => onSelectSubmitChange(item)">
                                            {{ item.label }}
                                        </div>
                                        </MenuItem>
                                    </div>
                                </div>
                            </MenuItems>
                        </transition>
                    </Menu>
                </div>
            </div>
        </form>
    </div>
</template>
