<!--
  * Author: Vika Aqordi
  * Created on: 18-02-2025-09h-16m
  * Github: https://github.com/aqordeon
  * Copyright: 2025
-->
<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { inject, nextTick, onMounted, provide, reactive, ref, toRaw, watch } from 'vue'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { trans } from 'laravel-vue-i18n'
import AnnouncementTemplateList from '@/Components/Websites/Announcement/AnnouncementTemplateList.vue'
import AnnouncementSettings from '@/Components/Websites/Announcement/AnnouncementSettings.vue'

import { library } from "@fortawesome/fontawesome-svg-core"
import { faGlobe, faImage, faExternalLink, faRocketLaunch, faSave, faUndoAlt, faInfoCircle, faChevronDown, faCircle, faHandPointer, faStopwatch20 } from '@fal'
import { faThLarge, faSquare } from '@fas'
import { faCheckCircle } from '@far'
import { faStop } from '@fad'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import AnnouncementSideEditor from '@/Components/Websites/Announcement/AnnouncementSideEditor.vue'
import { notify } from '@kyvg/vue3-notification'
import ScreenView from '@/Components/ScreenView.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { debounce, remove, set } from 'lodash'
import Modal from '@/Components/Utils/Modal.vue'
import { getAnnouncementComponent } from '@/Composables/getAnnouncement'
import { propertiesToHTMLStyle } from '@/Composables/usePropertyWorkshop'
import { routeType } from '@/types/route'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import VueDatePicker from '@vuepic/vue-datepicker';
import { useFormatTime } from '@/Composables/useFormatTime'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import Icon from '@/Components/Icon.vue'

library.add(faStop, faGlobe, faImage, faExternalLink, faRocketLaunch, faSave, faUndoAlt, faInfoCircle, faChevronDown, faCircle, faHandPointer, faStopwatch20, faSquare, faThLarge, faCheckCircle)

const props = defineProps<{
    pageHead: {}
    title: string
    // data: {},
    announcement_data: {
        container_properties: {
            
        }
        created_at: string
        fields: {
            close_button: {
                size: string
                text_color: string
                block_properties: {
                    position: {
                        x: string
                        y: string
                        type: string  // 'absolute' | 'relative'
                    }
                }
            }
            
        }
        id: number
        icon?: string
        name: string
        schedule_at?: string
        schedule_finish_at?: string
        settings: {

        }
        state: string
        status: string
        ulid: string
        template_code: string
        updated_at: string
    }
    // announcement_list: {}
    routes_list: {
        publish_route: routeType
        update_route: routeType
        reset_route: routeType
        close_route: routeType
        activated_route: routeType
    }
    is_announcement_published: boolean
    is_announcement_active: string  // 'inactive' | 'active'
    last_published_date: string | null
    is_announcement_dirty?: boolean
    portfolio_website: {
        name: string
        url: string
    }
}>()

const announcementData = ref(props.announcement_data)
console.log('ann', announcementData.value)
provide('announcementData', announcementData.value)

const isModalOpen = ref(false)

// Method: Save announcement
const isLoadingSave = ref(false)
const saveCancelToken = ref<Function | null>(null)
const onSave = () => {
    if(announcementData.value?.template_code) {
        router[props.routes_list.update_route.method || ''](
            route(props.routes_list.update_route.name, props.routes_list.update_route.parameters),
            announcementData.value,
            {
                onStart: () => isLoadingSave.value = true,
                onFinish: () => {
                    isLoadingSave.value = false
                    saveCancelToken.value = null
                },
                onError: (error) => {
                    console.log('ewew', error)
                    notify({
                        title: trans('Something went wrong'),
                        text: trans('Failed to save Announcement data'),
                        type: 'error',
                    })
                },
                onCancelToken: (cclToken) => saveCancelToken.value = cclToken.cancel,
                preserveState: true,
                preserveScroll: true
            }
        )
    }
}


// Method: Publish announcement
const isLoadingPublish = ref(false)
const onPublish = () => {
    const toPublish = {
        ...announcementData.value,
        compiled_layout: 'ffff' || _component_template_Announcement.value?.compiled_layout || undefined,
        text: 'xxx'
    }
    // console.log('toto', _component_template_Announcement.value?.dataToPublish)
    // console.log('topub', toPublish)
    router[props.routes_list.publish_route.method || 'patch'](
        route(props.routes_list.publish_route.name, props.routes_list.publish_route.parameters),
        toPublish,
        {
            onStart: () => isLoadingPublish.value = true,
            onFinish: () => {
                isLoadingPublish.value = false
                saveCancelToken.value = null
            },
            onSuccess: () => {
                notify({
                    title: 'Success',
                    text: trans('Announcement is published'),
                    type: 'success',
                })
            },
            onError: (error) => {
                console.log('error', error)
                notify({
                    title: trans('Something went wrong'),
                    text: error.message,
                    type: 'error',
                })
            },
            preserveState: true,
            preserveScroll: true
        }
    )
}

// Method: Reset data
const isLoadingReset = ref(false)
const onReset = async () => {
    router[props.routes_list.reset_route.method || 'delete'](
        route(props.routes_list.reset_route.name, props.routes_list.reset_route.parameters),
        {
            onStart: () => {
                isLoadingReset.value = true
            },
            onFinish: () => {
                isLoadingReset.value = false
                saveCancelToken.value = null
            },
            onError: (error) => {
                notify({
                    title: 'Something went wrong',
                    text: error.message,
                    type: 'error',
                })
            },
            onCancelToken: (cclToken) => saveCancelToken.value = cclToken.cancel,
            preserveState: true,
            preserveScroll: true
        }
    )
}


// If fieldvalue have changes, then auto save
watch(announcementData, (newVal) => {
    if (newVal) {
        // If still on progress saving, cancel the save
        if (saveCancelToken.value) {
            saveCancelToken.value()
        }

        xxx()
    }
}, { deep: true })

const xxx = debounce(() => onSave(), 1000, { leading: false, trailing: true })


const isOnPublishState = inject('isOnPublishState', false)
const styleToRemove = isOnPublishState ? ['top'] : null
const _parentComponent = ref(null)

// Section: Tabs
// const isLoadingComponent = ref<string | null>(null)
const selectedTab = ref(0)
const changeTab = async (idxCategory: number) => {
    // isLoadingComponent.value = idxCategory
    selectedTab.value = idxCategory
}



const cancelTokenActivate = ref<Function | null>(null)
const onClickToggleActivate = async (newVal: string) => {
    if (props.is_announcement_active === newVal) return

    if(cancelTokenActivate.value) return
    
    router[props.routes_list.activated_route.method || 'patch'](
        route(props.routes_list.activated_route.name, props.routes_list.activated_route.parameters),
        { is_published: newVal },
        {
            onCancelToken: (cancelToken) => {
                cancelTokenActivate.value = cancelToken.cancel
            },
            onFinish: () => {
                cancelTokenActivate.value = null
            },
            onSuccess: () => {
                notify({
                    title: trans('Gotcha!'),
                    text: trans('Successfully set the status'),
                    type: 'success',
                })
            },
            onError: () => {
                notify({
                    title: trans('Something went wrong'),
                    text: trans('Failed to update the status'),
                    type: 'error',
                })
            },
        }
    )
}

const openFieldWorkshop = ref<number | null>(null)
provide('openFieldWorkshop', openFieldWorkshop)

// Delivery url
const getDeliveryUrlXx = () => {
    console.log('envi', usePage().props.environment)
    if (usePage().props.environment === 'local') {
        return `http://delivery.wowsbar.test/announcement?logged_in=true&domain=${new URL(props.portfolio_website.url).hostname}`
    } else if (usePage().props.environment === 'staging') {
        return `https://delivery-staging.wowsbar.com/announcement?logged_in=true&domain=${new URL(props.portfolio_website.url).hostname}`
    } else if (usePage().props.environment === 'production') {
        return `https://delivery.wowsbar.com/announcement?logged_in=true&domain=${new URL(props.portfolio_website.url).hostname}`
    }

    return '#'
}
const deliveryUrl = getDeliveryUrlXx()

const _component_template_Announcement = ref(null)

const sectionClass = ref('')
const onSectionSetting = () => {
    setTimeout(() => {
        sectionClass.value = 'bg-yellow-500/40'
        setTimeout(() => {
            sectionClass.value = 'bg-yellow-500/0'
        }, 600)
    }, 100)
}

</script>

<template layout="CustomerApp">

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #afterTitle="{ iconRight, afterTitle }">
            <Icon :data="iconRight" />
            <!-- <div
                @click="() => onStopAnnouncement()"
                class="text-red-500 hover:underline cursor-pointer font-normal text-base"
            >
                {{ trans("Stop now") }}
            </div> -->
        </template>

        <template #other>
            <div class="flex gap-x-2 flex-wrap gap-y-1.5 justify-end">
                <Button @click="onReset" label="Reset" v-tooltip="trans('Reset data to last publish') + ` (${useFormatTime(last_published_date || '', {formatTime: 'hm'})})`" :loading="isLoadingReset" :style="'negative'" :disabled="!is_announcement_dirty" icon="fal fa-undo-alt" />
                <!-- <Button @click="() => false" label="Stop now" :loading="isLoadingSave" :style="'red'" icon="fas fa-square" /> -->

                <!-- Button: Active & Inactive -->
                <div v-if="routes_list.activated_route" class="flex items-center min-w-16">
                    <div class="grid grid-cols-2 cursor-pointer rounded overflow-hidden select-none ring-1 ring-gray-400">
                        <div @click="onClickToggleActivate('inactive')"
                            class="py-1.5 px-3 flex justify-center items-center gap-x-1 capitalize transition-all"
                            :class="[is_announcement_active == 'inactive' ? 'bg-red-600 text-gray-100' : 'bg-gray-100/70 text-red-400 hover:bg-red-200/70']">
                            {{ trans('Inactive') }}
                            <LoadingIcon v-if="is_announcement_active !== 'inactive' && cancelTokenActivate" size="sm" />
                            <FontAwesomeIcon v-else-if="is_announcement_active == 'inactive'" icon='far fa-check-circle' size="sm" class='' fixed-width aria-hidden='true' />
                            <FontAwesomeIcon v-else="!cancelTokenActivate" icon='fal fa-circle' size="sm" class='' fixed-width aria-hidden='true' />
                        </div>

                        <div @click="onClickToggleActivate('active')"
                            class="py-1.5 px-3 flex justify-center items-center gap-x-1 capitalize transition-all"
                            :class="[is_announcement_active === 'active' ? 'bg-green-600 text-green-100' : 'bg-gray-100/70 text-gray-400 hover:bg-green-200/70']">
                            {{ trans('Active') }}
                            <LoadingIcon v-if="is_announcement_active !== 'active' && cancelTokenActivate" size="sm" />
                            <FontAwesomeIcon v-else-if="is_announcement_active === 'active'" icon='far fa-check-circle' size="sm" class='' fixed-width aria-hidden='true' />
                            <FontAwesomeIcon v-else="!cancelTokenActivate" icon='fal fa-circle' size="sm" class='' fixed-width aria-hidden='true' />
                        </div>
                    </div>
                </div>

                <!-- Button: Publish & Setting -->
                <div class="flex items-center">
                    <Button
                        @click="() => (selectedTab = 1, onSectionSetting())"
                        :style="'secondary'"
                    >
                        <div>
                            {{ trans("Publish & Setting") }}
                            <FontAwesomeIcon icon='fal fa-cog' class='' fixed-width aria-hidden='true' />
                        </div>
                    </Button>
                    <!-- <Button @click="onPublish" label="Publish now" :loading="isLoadingPublish" iconRight="fal fa-rocket-launch" class="rounded-l-none" /> -->
                </div>
            </div>
        </template>
    </PageHeading>

    <!-- Section: Tab selector -->
    <div class="mx-auto max-w-md px-2 sm:px-0 my-4 flex gap-x-2">
        <!-- {{ selectedTab }} -->
        <TabGroup :selectedIndex="selectedTab" @change="(e: number) => selectedTab === e ? '' : changeTab(e)">
            <TabList class="flex space-x-1 rounded-xl bg-slate-600 p-1">
                <Tab
                    v-for="(category, indexCat) in ['Workshop', 'Setting']"
                    as="template"
                    :key="category"
                    v-slot="{ selected }"
                >
                    <button :class="[
                        'px-8 w-full rounded-lg py-2.5 text-sm font-medium leading-5 ring-white/60 ring-offset-2 ring-offset-blue-400 focus:outline-none focus:ring-2',
                        selected
                            ? 'bg-white text-slate-700 shadow'
                            : 'text-blue-100 hover:bg-white/[0.12] hover:text-white',
                        ]"
                    >
                        {{ category }}
                        <!-- <LoadingIcon v-if="isLoadingComponent === category" /> -->
                    </button>
                </Tab>
            </TabList>

        </TabGroup>
        <div @click="() => false ? onSave() : false" v-tooltip="trans('Save status')" class="flex items-center px-2 text-3xl">
            <LoadingIcon v-if="isLoadingSave" />
            <FontAwesomeIcon v-else icon='fal fa-save' class='text-gray-300' fixed-width aria-hidden='true' />
        </div>
        <!-- <Button @click="onSave" label="save" :loading="isLoadingSave" :style="'tertiary'" icon="fal fa-save" /> -->

    </div>
    
    <!-- Section: Workshop -->
    <div v-show="selectedTab === 0" class="flex border-t border-gray-300">
        <!-- Section: Side editor -->
        <div class="w-[600px] py-2 px-3 ">
            <div class="w-full text-lg font-semibold flex items-center justify-between gap-3 border-b border-gray-300">
                <div class="flex items-center gap-3">
                    {{ trans('Announcement') }}
                </div>

                <div class="py-1 px-2 cursor-pointer" title="template" v-tooltip="'Template'"
                    @click="isModalOpen = true">
                    <FontAwesomeIcon icon="fas fa-th-large" aria-hidden='true' />
                </div>
            </div>

            <div class="h-[calc(100vh-280px)] w-[450px] overflow-y-auto rounded-md shadow-lg">
                <AnnouncementSideEditor
                    v-if="announcementData.template_code"
                    :blueprint="_component_template_Announcement?.fieldSideEditor"
                />
            </div>
        </div>

        <!-- Section: Preview -->
        <div class="w-full h-full flex flex-col py-2 px-3">
            <div class="flex justify-between">
                <!-- <div class="py-1 px-2 cursor-pointer md:hidden block" title="Desktop view" v-tooltip="'Navigation'">
                    <FontAwesomeIcon :icon='faBars' aria-hidden='true' @click="()=>openDrawer = true" />
                    <Drawer v-model:visible="openDrawer" :header="''" :dismissable="true">
                        <WebpageSideEditor ref="_WebpageSideEditor" :webpage="data" :webBlockTypeCategories="webBlockTypeCategories"
                            @update="sendBlockUpdate" @delete="sendDeleteBlock" @add="addNewBlock"
                            @order="sendOrderBlock"  @openBlockList="()=>{openDrawer = false, _WebpageSideEditor.isModalBlocksList = true}" />
                    </Drawer>
                </div> -->

                <!-- Section: Screenview -->
                <div class="flex justify-between w-full py-2 px-2">
                    <div>
                        <!-- <ScreenView @screenView="false" /> -->
                    </div>

                    <a :href="deliveryUrl"
                        target="_blank" class="py-1 px-2 cursor-pointer" title="Desktop view" v-tooltip="'Preview'">
                        What will showed in <span class="font-semibold">{{ portfolio_website.name }}</span>?
                        <FontAwesomeIcon icon='fal fa-external-link' aria-hidden='true' />
                    </a>
                </div>
            </div>

            <div class="border-2 h-full w-full">
                <div class="h-full w-full bg-white relative">
                    <div v-if="announcementData.template_code"
                        ref="_parentComponent"
                        :xxxstyle="{
                            ...propertiesToHTMLStyle(announcementData.container_properties, { toRemove: styleToRemove}),
                            position: announcementData.container_properties?.position?.type === 'fixed' ? 'absolute' : announcementData.container_properties?.position?.type
                        }"
                    >
                        <component
                            :is="getAnnouncementComponent(announcementData.template_code)"
                            :announcementData="announcementData"
                            :key="announcementData.template_code"
                            isEditable
                            :_parentComponent
                            ref="_component_template_Announcement"
                        />
                    </div>

                    <div v-else class="text-center pb-14">
                        <EmptyState :data="{ title: trans('No Announcement selected')}" />
                        
                        <div class="mx-auto mt-4">
                            <Button @click="() => isModalOpen = true" :style="'tertiary'"
                                label="Select from template" />
                        </div>

                    </div>
                    <!-- <br>
                    <br>
                    <pre>{{ announcementData }}</pre> -->
                </div>
            </div>
        </div>
    </div>

    <!-- Section: Setting -->
    <div v-show="selectedTab === 1">
        <div class="max-w-4xl mx-auto px-4 relative pt-4 pb-2 transition-all duration-500" :class="sectionClass">
            <!-- Section: Target -->
            <AnnouncementSettings
                :domain="portfolio_website.url"
                :onPublish
                :isLoadingPublish
            />

            <div v-if="isLoadingPublish" class="rounded-md bg-black/20 text-white text-[60px] absolute inset-0 flex items-center justify-center">
                <LoadingIcon />
            </div>

        </div>
    </div>


    <Modal :isOpen="isModalOpen" @onClose="isModalOpen = false">
        <div class="h-[500px]">
            <AnnouncementTemplateList @afterSubmit="() => isModalOpen = false" />
        </div>
    </Modal>


</template>

<style lang="scss" scoped>
:deep(.announcement-component-editable) {
    @apply hover:bg-white/30 border border-transparent hover:border-white/80 border-dashed cursor-pointer;
}

.moveable-control-box {

}

.moveable-control.moveable-origin {
    visibility: hidden !important;
}

:deep(.p-select-label) {
    padding-right: 0px !important;
}

</style>