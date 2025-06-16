<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import Action from '@/Components/Forms/Fields/Action.vue'
import FieldForm from '@/Components/Forms/FieldForm.vue'
import { get as getLodash } from 'lodash-es'
import { capitalize } from "@/Composables/capitalize"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faGoogle } from "@fortawesome/free-brands-svg-icons"
import { routeType } from '@/types/route'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { inject } from 'vue'

import { faUserLock, faEnvelope, faShoePrints, faShoppingBag, faBell, faCopyright, faUserCircle, faMobileAndroidAlt, faKey, faClone, faPaintBrush, faMoonStars, faLightbulbOn, faCheck, faPhone, faIdCard, faFingerprint, faLanguage, faAddressBook, faTrashAlt, faSlidersH, faCog, faFlagCheckered, faBracketsCurly, faFileInvoice, faTransporter, faCode } from '@fal'
import { faBrowser, faPowerOff, faDoorClosed } from '@fal'
import { faBan } from '@far'
import { Head, usePage } from '@inertiajs/vue3'
import axios from "axios"

library.add(faBan, faEnvelope, faPowerOff, faShoePrints, faShoppingBag, faBrowser, faUserLock, faBell, faCopyright, faUserCircle, faMobileAndroidAlt, faKey, faClone, faPaintBrush, faMoonStars, faLightbulbOn, faCheck, faPhone, faIdCard, faFingerprint, faLanguage, faAddressBook, faTrashAlt, faSlidersH, faCog, faGoogle, faFlagCheckered, faBracketsCurly, faFileInvoice, faTransporter, faCode, faDoorClosed)


const props = defineProps<{
    formData: {
        current?: string,
        blueprint: {
            [key: string]: {
                // sectionData
                label: string,
                title: string,
                subtitle?: string,
                icon: string
                fields: {
                    // FieldData
                    name: string,
                    type: string,
                    label: string,
                    value: string | object
                    icon?: string
                    action?: {
                        data?: any
                        method?: string
                    }
                }
                button: {
                    title: string
                    route: string
                    disable: boolean
                }
            }
        }
        fullLayout: boolean
        args: {
            updateRoute: routeType
        }
        title?: string
    }
}>()

// const layout = useLayoutStore()
const layout: any = inject('layout')
const currentTab = ref<string | number>(props.formData?.current || parseInt(Object.keys(props.formData?.blueprint)[0]))  // if formData.current not exist, take first navigation
const _buttonRefs = ref([])  // For click linked to Navigation
const tabActive: any = ref({})
const fieldGroupAnimateSection = ref()
const _fieldForm = ref()


const handleIntersection = (element: Element, index: number) => (entries) => {
    const [entry] = entries
    tabActive.value[`${index}`] = entry.isIntersecting
}

onMounted(() => {

    // Animate the selected section
    route().v().query?.section ? (
        currentTab.value = getLodash(route().v().query, 'section'),
        setTimeout(() => {
            fieldGroupAnimateSection.value = ['bg-yellow-500/20']
            setTimeout(() => {
                fieldGroupAnimateSection.value = []
            }, 600)
        }, 100)
    ) : ''

    // To indicate active state that on viewport
    _buttonRefs.value.forEach((element: any, index: number) => {
        const observer = new IntersectionObserver(handleIntersection(element, index))
        observer.observe(element)

        // Clean up the observer when the component is unmounted
        element.cleanupObserver = () => {
            observer.disconnect()
        }
    })

    // Clean up all the observers when the component is unmounted
    return () => {
        _buttonRefs.value.forEach((button: any) => button.cleanupObserver())
    }
})


function connectToPlatform(routeName, parameters) {
    axios.post(route(routeName, parameters))
        .then((response) => {
            window.location.href = response.data
        })
}

</script>


<template>
    <!-- If overflow-hidden, affect to Multiselect on Address -->
    <div class="rounded-lg shadow">
        <div class="divide-y divide-gray-200 lg:grid grid-flow-col lg:grid-cols-12 lg:divide-y-0 lg:divide-x">

            <!-- Tab: Navigation -->
            <aside v-if="!formData.fullLayout" class="bg-gray-50/50 py-0 lg:col-span-3 lg:h-full">
                <div class="sticky top-16">
                    <template v-for="(sectionData, key) in formData.blueprint">
                        <!-- If Section: all fields is not hidden -->
                        <div v-if="!(Object.values(sectionData.fields).every((field: any) => field.hidden))"
                            @click="currentTab = key" :class="[
                                key == currentTab
                                    ? `navigationSecondActive`
                                    : `navigationSecond`,
                                'cursor-pointer group px-3 py-2 flex items-center text-sm font-medium',
                            ]" :style="[key == currentTab ? {
                                'border-left': `4px solid ${layout.app?.theme[2]}`,
                                'background-color': `color-mix(in srgb, ${layout?.app?.theme[2]} 20%, white)`,
                                'color': `color-mix(in srgb, ${layout?.app?.theme[3]} 50%, black)`,
                            } : {}]">
                            <FontAwesomeIcon v-if="sectionData.icon" aria-hidden="true"
                                class="flex-shrink-0 -ml-1 mr-2 h-4 w-4" :class="[
                                    tabActive[key]
                                        ? 'text-gray-400 group-hover:text-gray-500'
                                        : 'text-gray-400',
                                ]" :icon="sectionData.icon" />
                            <span class="truncate">{{ sectionData.label }}</span>
                            <!-- {{ tabActive }} -- {{ key == currentTab }} -->
                        </div>
                    </template>
                </div>
            </aside>

            <!-- Section: Fields Form -->
            <div :class="['px-4 sm:px-6 md:px-4', formData.fullLayout ? 'col-span-12' : 'col-span-9']">
                <!-- Section: Error in models -->
                <Transition name="spin-to-down">
                    <div v-if="usePage().props?.errors?.error_in_models"
                        class="mt-3 flex gap-x-1 items-center bg-red-500 w-full p-3 text-white rounded">
                        <FontAwesomeIcon
                            v-if="usePage().props?.errors?.error_in_models?.match(/^(\d{3}):\s(.+)$/)?.[1] === '403'"
                            icon='far fa-ban' class='text-lg' fixed-width aria-hidden='true' />
                        <div class="">{{ usePage().props.errors.error_in_models }}</div>
                    </div>
                </Transition>

                <template v-for="(sectionData, sectionIdx) in formData.blueprint" :key="sectionIdx">
                    <!-- If Section: all fields is not hidden -->
                    <template v-if="!(Object.values(sectionData.fields).every((field: any) => field.hidden))">
                        <div v-show="sectionIdx == currentTab" class="pt-4">
                            <div class="sr-only absolute -top-16" :id="`field${sectionIdx}`" />
                            <!-- Title -->
                            <div v-if="sectionData.title || sectionData.subtitle" class="mb-4 flex items-center gap-x-2"
                                ref="_buttonRefs">
                                <h3 v-if="sectionData.title"
                                    class="text-lg leading-6 font-medium text-gray-700 capitalize">
                                    {{ sectionData.title }}
                                </h3>
                                <p v-if="!sectionData.subtitle" class="max-w-2xl text-sm text-gray-500">
                                    {{ sectionData.subtitle }}
                                </p>
                            </div>

                            <!-- Looping Field -->
                            <div class="my-2 space-y-5 transition-all duration-1000 ease-in-out"
                                :class="fieldGroupAnimateSection">
                                <template v-for="(fieldData, fieldName, index) in sectionData.fields" :key="index">
                                    <!-- Field: is not hidden = true -->
                                    <div v-if="!fieldData?.hidden" class="py-2 mt-1 flex text-sm text-gray-700 sm:mt-0">
                                        <Action v-if="fieldData.type === 'action'" :action="fieldData.action"
                                            :dataToSubmit="fieldData.action?.data" />
                                        <FieldForm v-else :key="fieldName + index" ref="_fieldForm" :field="fieldName"
                                            :fieldData="fieldData" :args="formData.args" :refForms="_fieldForm" />
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </template>

                <!-- For button Authorize -->
                <div class="py-2 px-3 flex justify-end max-w-2xl" v-if="formData.blueprint?.[currentTab]?.button"
                    :id="formData.title">
                    <component :is="'button'"
                        @click="connectToPlatform(formData.blueprint[currentTab].button.route.name, formData.blueprint[currentTab].button.route.parameters)"
                        class="px-3 py-1.5 rounded"
                        :class="[formData.blueprint[currentTab].button.disable ? 'bg-orange-200 cursor-default text-white' : 'text-gray-100 bg-green-500 hover:bg-green-600']">
                        {{ formData.blueprint[currentTab].button.title }}
                    </component>
                </div>
            </div>
        </div>

    </div>
</template>
