<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 08 Sept 2022 00:38:38 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { library } from '@fortawesome/fontawesome-svg-core';
import {
    faEnvelope,
    faIdCard,
    faPhone,
    faSignature,
    faUser,
    faBirthdayCake,
    faVenusMars,
    faHashtag,
    faHeading,
    faHospitalUser,
    faClock,
    faPaperclip,
    faTimes,
    faCameraRetro,
    faKey
} from '@fal';
import { faCheckCircle } from '@fas';
import { router } from '@inertiajs/vue3'
import { capitalize } from "@/Composables/capitalize"
import PageHeading from '@/Components/Headings/PageHeading.vue';
import { computed, defineAsyncComponent, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import ModelDetails from "@/Components/ModelDetails.vue";
import DataModel from "@/Components/DataModel.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue";
import TableTimesheets from "@/Components/Tables/Grp/Org/HumanResources/TableTimesheets.vue";
import TableJobPositions from "@/Components/Tables/Grp/Org/HumanResources/TableJobPositions.vue";
import type { Table } from "@/types/Table.ts"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import type { Navigation } from "@/types/Tabs";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Popover from '@/Components/Popover.vue'
import Button from '@/Components/Elements/Buttons/Button.vue';
import UploadAttachment from '@/Components/Upload/UploadAttachment.vue';
import ShowcaseEmployee from '@/Components/Showcases/Grp/ShowcaseEmployee.vue';
import { trans } from 'laravel-vue-i18n'

library.add(
    faIdCard,
    faUser,
    faCheckCircle,
    faSignature,
    faEnvelope,
    faPhone,
    faIdCard,
    faBirthdayCake,
    faVenusMars,
    faHashtag,
    faHeading,
    faHospitalUser,
    faClock,
    faPaperclip,
    faTimes,
    faCameraRetro,
    faKey
)

const createEmployeeUser = () => {
    router.post(route('grp.org.hr.employees.show.user.store', props['employee'].data.id), {})
}

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes,
    tabs: {
        current: string;
        navigation: Navigation;
    },
    history?: object
    data?: object
    timesheets?: object
    job_positions?: Table
    attachments?: {}
    attachmentRoutes?:object
    showcase : object
}>()

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);
const isModalUploadOpen = ref(false)
const attachmentRoutes = ref(props.attachmentRoutes)
const component = computed(() => {

    const components = {
        showcase: ShowcaseEmployee,
        history: TableHistories,
        data: DataModel,
        timesheets: TableTimesheets,
        job_positions: TableJobPositions,
        attachments: TableAttachments
    };
    return components[currentTab.value];

});

</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #tabs-pin="{ data }">
            <div class="flex items-end gap-x-1">
                <Popover v-if="data.label" class="flex items-end" position="left-0">
                    <template #button>
                        <div class="flex items-end text-xs leading-none gap-x-1">
                            <FontAwesomeIcon v-if="data.leftIcon" v-tooltip="data.leftIcon.tooltip" fixed-width
                                aria-hidden="true" :icon="data.leftIcon.icon" class="text-gray-400" />
                            <div class="flex items-end leading-none">XXXXXX</div>
                        </div>
                    </template>

                    <template #content="{ close: closed }">
                        <div class="w-full whitespace-nowrap">
                            <strong>Pin</strong> : {{ data.label }}
                        </div>
                    </template>
                </Popover>
                <div v-else>Not Set</div>
            </div>
        </template>
        <template #other>
            <Button
                v-if="currentTab === 'attachments'"
                @click="() => isModalUploadOpen = true"
                :label="trans('Attach file')"
                icon="fal fa-upload"
                type="secondary"
            />
        </template>

    </PageHeading>

    <!--
    <div v-if="!employee.data.user || ( $page.props.flash.notification && $page.props.flash.notification.type==='newUser')"   class="m-4 bg-white shadow sm:rounded-lg max-w-2xl">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900">{{ trans('System user')}}  <span v-if="$page.props.flash.notification" class="text-green-600 ml-2 text-sm">
                    <FontAwesomeIcon aria-hidden="true" icon="fa-solid fa-check-circle" size="lg" /> {{$page.props.flash.notification.message}}</span></h3>
                <div v-if="!employee.data.user"  class="mt-2 sm:flex sm:items-start sm:justify-between">
                    <div class="max-w-xl text-sm text-gray-500">
                        <p class="text-red-500">{{ trans('This employee is not an user') }}.</p>
                    </div>


                    <div class="mt-5 sm:mt-0 sm:ml-6 sm:flex sm:shrink-0 sm:items-center">
                        <button @click="createEmployeeUser" type="button" class="mr-5 inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:text-sm">
                            {{ trans('Add employee to system users') }}
                        </button>

                    </div>
                </div>
            </div>
            <div v-if="$page.props.flash.notification && $page.props.flash.notification.type==='newUser'" class="border-t border-gray-200 px-4 py-5 sm:p-0">

                <dl class="sm:divide-y sm:divide-gray-200">
                    <div class="py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:py-5 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">{{$page.props.flash.notification.fields.username.label}}</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{{$page.props.flash.notification.fields.username.value}}</dd>
                    </div>
                    <div class="py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:py-5 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">{{$page.props.flash.notification.fields.password.label}}</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{{$page.props.flash.notification.fields.password.value}}</dd>
                    </div>


                </dl>
            </div>

        </div>
    -->

    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab" :detachRoute="attachmentRoutes.detachRoute"></component>

    <UploadAttachment v-model="isModalUploadOpen" scope="attachment" :title="{
        label: 'Upload your file',
        information: 'The list of column file: customer_reference, notes, stored_items'
    }" progressDescription="Adding Pallet Deliveries" :attachmentRoutes="attachmentRoutes" />
</template>
