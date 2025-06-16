<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 19 Feb 2024 23:55:54 Central Standard Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {ref, onMounted, onBeforeUnmount} from 'vue'
import Action from '@/Components/Forms/Fields/Action.vue'
import FieldForm from '@/Components/Forms/FieldForm.vue'
import { get as getLodash } from 'lodash-es'
import { capitalize } from "@/Composables/capitalize"
// import { useLayoutStore } from '@/Stores/retinaLayout'
import {library} from "@fortawesome/fontawesome-svg-core"
import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome"
import {faGoogle} from "@fortawesome/free-brands-svg-icons"
import { routeType } from '@/types/route'
import PageHeading from '@/Components/Headings/PageHeading.vue';
import { inject } from 'vue'
import { faUserLock, faBell, faCopyright, faUserCircle, faMobileAndroidAlt, faKey, faClone, faPaintBrush, faMoonStars, faLightbulbOn, faCheck, faPhone, faIdCard, faFingerprint, faLanguage, faAddressBook, faTrashAlt, faSlidersH } from '@fal'
import { Head } from '@inertiajs/vue3'
import ApiFieldForm from './ApiFieldForm.vue'

library.add(faUserLock,faBell,faCopyright,faUserCircle, faMobileAndroidAlt, faKey, faClone, faPaintBrush, faMoonStars, faLightbulbOn, faCheck, faPhone, faIdCard, faFingerprint,faLanguage,faAddressBook,faTrashAlt, faSlidersH, faGoogle)


const props = defineProps<{
    title: string,
    pageHead: {
        title: string
        exitEdit: {
            route: {
                name: string
                parameters: string[]
            }
        }
    },
    data: {}
    formData: {
        current?:string,
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
        args: {
            updateRoute: routeType
        }
        title?: string
    }
}>()

// const layout = useLayoutStore()
const layout: any = inject('layout')
const currentTab = ref<string|number>(props.formData?.current || parseInt(Object.keys(props.formData?.blueprint)[0]))  // if formData.current not exist, take first navigation
const buttonRefs = ref([])  // For click linked to Navigation
const isMobile = ref(false)
const tabActive: any = ref({})
const fieldGroupAnimateSection = ref()

const updateViewportWidth = () => {
    isMobile.value = window.innerWidth <= 768
}

const handleIntersection = (element: Element, index: number) => (entries) => {
    const [entry] = entries;
    tabActive.value[`${index}`] = entry.isIntersecting;
}

onMounted(() => {
    updateViewportWidth()
    window.addEventListener('resize', updateViewportWidth)

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
    buttonRefs.value.forEach((element: any, index: number) => {
        const observer = new IntersectionObserver(handleIntersection(element, index));
        observer.observe(element);

        // Clean up the observer when the component is unmounted
        element.cleanupObserver = () => {
            observer.disconnect();
        };
    });

    // Clean up all the observers when the component is unmounted
    return () => {
        buttonRefs.value.forEach((button: any) => button.cleanupObserver());
    };
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateViewportWidth)
})

</script>


<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>


    <!-- If overflow-hidden, affect to Multiselect on Address -->
    <div class="rounded-lg shadow">
        <div v-if="!isMobile" class="divide-y divide-gray-200 lg:grid grid-flow-col lg:grid-cols-12 lg:divide-y-0 lg:divide-x">

            <!-- Tab: Navigation -->
           

            <!-- Content of forms -->
            <div class="px-4 sm:px-6 md:px-4 col-span-9">
                <template v-for="(sectionData, sectionIdx ) in formData.blueprint" :key="sectionIdx">
                    <!-- If Section: all fields is not hidden -->
                    <template v-if="!(Object.values(sectionData.fields).every((field: any) => field.hidden))">
                        <div v-show="sectionIdx == currentTab" >
                            <div class="sr-only absolute -top-16" :id="`field${sectionIdx}`"/>
                            <!-- Title -->
                            <div class="flex items-center gap-x-2" ref="buttonRefs">
                                <h3 v-if="sectionData.title" class="text-lg leading-6 font-medium text-gray-700 capitalize">
                                    {{ sectionData.title }}
                                </h3>
                                <p v-if="sectionData.subtitle" class="max-w-2xl text-sm text-gray-500">
                                    {{ sectionData.subtitle }}
                                </p>
                            </div>
                            <!-- Looping Field -->
                            <div class="my-2 pt-4 space-y-5 transition-all duration-1000 ease-in-out" :class="fieldGroupAnimateSection">
                                <template v-for="(fieldData, fieldName, index) in sectionData.fields" :key="index">
                                    <!-- Field: is not hidden = true -->
                                    <div v-if="!fieldData?.hidden" class="py-2 mt-1 flex text-sm text-gray-700 sm:mt-0">
                                        <Action v-if="fieldData.type==='action'" :action="fieldData.action" :dataToSubmit="fieldData.action?.data" />
                                        <ApiFieldForm v-else :key="index" :field="fieldName" :fieldData="fieldData" :args="formData.args" />
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </template>

                <!-- For button Authorize Google -->
                <div class="py-2 px-3 flex justify-end max-w-2xl" v-if="formData.blueprint?.[currentTab]?.button" :id="formData.title">
                    <component :is="formData.blueprint[currentTab].button.disable ? 'div' : 'a'"
                        :href="formData.blueprint[currentTab].button.route" target="_blank" rel="noopener noreferrer"
                        class="px-3 py-1.5 rounded"
                        :class="[formData.blueprint[currentTab].button.disable ? 'bg-orange-200 cursor-default text-white' : 'text-gray-100 bg-orange-500 hover:bg-orange-600']"
                    >
                        {{ formData.blueprint[currentTab].button.title }}
                    </component>
                </div>
            </div>
        </div>

        <!-- Mobile view -->
        <ul v-else class="space-y-8">
            <template v-for="(sectionData, key) in formData.blueprint">
                <!-- If Section: all fields is not hidden -->
                <li v-if="!(Object.values(sectionData.fields).every((field: any) => field.hidden))"
                    class="group font-medium"
                    :aria-current="key === currentTab ? 'page' : undefined"
                >
                    <div class="bg-gray-200 py-3 pl-5 flex items-center">
                        <FontAwesomeIcon v-if="sectionData.icon" aria-hidden="true" :icon="sectionData.icon"
                            class="flex-shrink-0 mr-3 h-5 w-5"
                            :class="[
                                key === currentTab ? 'text-gray-400' : 'text-gray-500',
                            ]"/>
                        <span class="capitalize truncate">{{ sectionData.label }}</span>
                    </div>
                    <div class="px-5">
                        <template v-for="(fieldData, fieldName, index) in formData.blueprint[key].fields" >
                            <!-- Field: is not hidden = true -->
                            <div v-if="!fieldData?.hidden" class="py-4">
                                <Action v-if="fieldData.type === 'action'" :action="fieldData.action" :dataToSubmit="fieldData.action?.data" />
                                <FieldForm v-else :key="index" :field="fieldName" :fieldData="fieldData" :args="formData.args" :id="fieldData.name" />
                            </div>
                        </template>
                    </div>
                </li>
            </template>
        </ul>

    </div>
</template>



