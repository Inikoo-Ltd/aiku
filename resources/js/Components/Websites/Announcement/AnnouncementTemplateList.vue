<script setup lang="ts">
import { ref, onMounted, inject, toRaw, isProxy, computed } from 'vue';
import { faPresentation, faCube, faText, faImage, faImages, faPaperclip, faShoppingBasket, faStar, faHandHoldingBox, faBoxFull, faBars, faBorderAll, faLocationArrow } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"

import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import Image from '@/Components/Image.vue'
import { getAnnouncementComponent } from '@/Composables/useAnnouncement'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { AnnouncementData } from '@/types/Announcement'
import Checkbox from 'primevue/checkbox'
import { set } from 'lodash-es'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { Image as ImageTS } from '@/types/Image'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'

library.add(faPresentation, faCube, faText, faImage, faImages, faPaperclip, faShoppingBasket, faStar, faHandHoldingBox, faBoxFull, faBars, faBorderAll, faLocationArrow)

interface AnnouncementTemplateCode {
    id: number
    code: string
    screenshot: ImageTS
    // Add other properties as needed
}

const props = withDefaults(defineProps<{
    // onPickBlock: Function
    // webBlockTypes: Root
    // scope?: String /* all|website|webpage */
}>(), {
    scope: "all",
})

const emits = defineEmits<{
    (e: 'afterSubmit'): void
}>()

const layout = inject('layout', layoutStructure)

const announcementData = inject<AnnouncementData | null>('announcementData', null)

const announcements_list = ref<AnnouncementTemplateCode[] | null>(null)
// const announcements_list_categories = ref<string[] | []>([])
const selectedCategory = ref<string>('all')
const compFilteredAnnouncementList = computed(() => {
    if (selectedCategory.value === 'all') {
        return announcements_list.value;
    }
    return announcements_list.value?.filter(ann => ann.category === selectedCategory.value);
})
const currentTopbarCode = ref(null)
const selectedTemplate = ref(announcementData || null)
const isSelectFullTemplate = ref(false)



function mergeData(data1: {}, data2: {}) {
    // const data1a = toRaw(data1)
    // const data2a = toRaw(data2)

    // console.log('data1', data1)

    // for (const key in data2a) {
    //     console.log('keey', key)
    //     if (data1a.hasOwnProperty(key)) {
    //         if (data2a[key].text) {
    //             data1a[key].text = data2a[key].text;
    //         }
    //     } else {
    //         data1a[key] = data2a[key];
    //     }
    // }

    for (const key in data2) {
        if (data1.hasOwnProperty(key)) {
            for (const prop in data2[key]) {
                if (prop !== 'text') {
                    set(data1, [key, prop], data2[key][prop])
                }
            }
        } else {
            // If key doesn't exist in data1, add it entirely
            data1[key] = data2[key];
        }
    }

    // console.log('data 111', data1)
    // return data1a;
}

const isLoadingSubmit = ref(false)
const onSubmitTemplate = (template) => {
    console.log('template', template.fields, isSelectFullTemplate.value, announcementData?.template_code)

    isLoadingSubmit.value = true

    if (isSelectFullTemplate.value || !announcementData?.template_code) {
        console.log('sdsdsd')
        announcementData.template_code = template.code
        announcementData.fields = template.fields
        announcementData.container_properties = template?.container_properties
    } else {
        announcementData.template_code = template.code
        announcementData.container_properties = template.container_properties
        mergeData(announcementData.fields, template.fields)
    }

    emits('afterSubmit')

    isLoadingSubmit.value = false
}

// Method: fetch announcement list
const isLoadingFetch = ref(false)
const fetchAnnouncementList = async () => {
    isLoadingFetch.value = true
    console.log('vvvvv', layout.currentParams)
    try {
        const response = await axios.get(
            route('grp.json.announcement_templates.index'),
        )

        console.log('kelo', response)
        announcements_list.value = response.data.data

        // Set category announcement
        if (announcements_list.value) {
            // announcements_list_categories.value = Array.from(response.data.data.reduce((acc, item) => {
            //     acc.add(item.category)
            //     return acc
            // }, new Set()))
        } else {
            console.log('No data available');
        }
    } catch (error) {
        console.log(error)
        notify({
            title: trans("Something went wrong."),
            text: trans("Failed to fetch announcement templates."),
            type: "error"
        });
        // loadingState.value = false
    }
    isLoadingFetch.value = false
}

onMounted(() => {
    fetchAnnouncementList()
})


</script>

<template>

    <div class="h-full flex flex-col ">
        <div class="flex justify-between items-center mb-2 border-b border-gray-200 pb-2">
            <div class="text-2xl font-medium">
                {{ trans("Announcement Templates") }}
            </div>
        </div>

        <div class="flex gap-x-8 xborder overflow-hidden">
            <!-- <nav class="w-1/5 bg-gray-100 py-4" aria-label="Sidebar">
                <ul v-if="!isLoadingFetch" role="list" class="space-y-1">
                    <li
                        v-for="category in ['all', ...announcements_list_categories || []]"
                        @click="() => selectedCategory = category"
                        :key="category"
                        :class="[category === selectedCategory ? 'bg-white text-indigo-600' : 'hover:bg-white/50 hover:text-indigo-600']"
                        class="capitalize group flex items-center gap-x-2 p-3 text-sm font-semibold cursor-pointer"
                    >
                        {{ category }}
                        <FontAwesomeIcon v-if="category?.icon" :icon='category?.icon' class='text-sm text-gray-400' fixed-width aria-hidden='true' />
                    </li>
                </ul>
                <div v-else class="grid gap-y-2">
                    <div v-for="_ in 3" class="h-10 skeleton">
                    </div>
                </div>
            </nav> -->

            <!-- <section class="h-full mx-auto w-full py-4 pl-2"> -->
            <div class=" w-full relative grid gap-y-16 gap-x-4 pr-8 h-full overflow-y-auto overflow-x-hidden">
                <template v-if="!isLoadingFetch">
                    <template v-if="compFilteredAnnouncementList?.length || true">
                        <div v-for="announcement in announcements_list" :key="announcement.code"
                            class="cursor-pointer overflow-hidden h-min group flex flex-col gap-x-2 relative isolate">
                            <div class="mb-1 w-fit"
                                :class="announcement.code === currentTopbarCode ? 'text-indigo-500 font-semibold shadow-xl' : 'bg-white'">
                                <div v-if="announcement.icon" class="flex items-center justify-center">
                                    <FontAwesomeIcon :icon='announcement.icon' class='' fixed-width
                                        aria-hidden='true' />
                                </div>
                                <h3 class="text-sm"
                                    :class="announcement.code === announcementData?.template_code ? 'text-indigo-600' : ''">
                                    {{ announcement.code }} <span
                                        v-if="announcement.code === announcementData?.template_code">(active)</span>
                                </h3>
                            </div>
                            <div class="group relative min-h-16 max-h-20 w-full aspect-[4/1] overflow-hidden flex items-center bg-gray-100 justify-center rounded cursor-pointer"
                                :class="[
                                    announcement.code == selectedTemplate?.code ? 'border-4 border-indigo-500' : 'border-4 border-gray-300 hover:border-indigo-500'
                                ]">
                                <div class="h-16 w-full object-cover flex items-center justify-center">
                                    <Image :src="announcement.screenshot" zimageCover class=""
                                        :imgAttributes="{ class: 'h-full w-full object-contain shadow-sm' }" />
                                </div>

                                <!-- Checkbox: Full template -->
                                <div v-if="selectedTemplate?.code === announcement.code"
                                    @click="() => (isSelectFullTemplate = !isSelectFullTemplate)"
                                    class="z-40 group-hover:bg-black/10 py-1 px-2 rounded text-gray-400 group-hover:text-gray-700 items-center gap-x-3 absolute top-1.5 right-3"
                                    :class="isSelectFullTemplate ? 'flex' : 'hidden group-hover:flex'">
                                    <input type="checkbox" id="selectFullTemplate" name="selectFullTemplate"
                                        v-model="isSelectFullTemplate"
                                        class="h-3.5 w-3.5 accent-purple-600 cursor-pointer" @click.stop />

                                    <label for="selectFullTemplate" class="cursor-pointer select-none">
                                        {{ trans('Full template') }}
                                        <InformationIcon
                                            :information="trans('If checked, the data is reset and follows the new full template structure.')" />
                                    </label>
                                </div>

                            </div>
                            <!-- Component: clickable -->
                            <component :is="getAnnouncementComponent(announcement.code)" isToSelectOnly
                                @templateClicked="(dataTemplate: {}) => (selectedTemplate = dataTemplate)"
                                class="z-30" />
                        </div>
                    </template>
                    <div v-else>{{ trans("No template available") }}</div>
                </template>

                <div v-else class="grid gap-y-8">
                    <div v-for="_ in 3" class="grid gap-y-2">
                        <div class="h-5 skeleton w-56"></div>
                        <div class="skeleton h-20 w-full rounded-md">
                        </div>
                    </div>
                </div>
            </div>
            <!-- </section> -->
        </div>

        <div class="w-full mt-6">
            <Button @click="() => onSubmitTemplate(selectedTemplate)" :label="trans('Submit')"
                :loading="isLoadingSubmit" :disabled="!selectedTemplate" full />
        </div>
    </div>
</template>
