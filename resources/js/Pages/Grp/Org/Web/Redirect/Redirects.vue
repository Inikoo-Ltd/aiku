<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 16 Aug 2023 20:30:48 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faAnalytics, faBrowser,
    faChartLine, faDraftingCompass, faRoad, faSlidersH, faUsersClass, faClock, faSeedling,
    faBroadcastTower,
    faSkull,
    faRocket,
    faExternalLink,
    faFolderDownload,
    faDownload
} from "@fal"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import { computed, ref, provide } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import ModelDetails from "@/Components/ModelDetails.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableWebpages from "@/Components/Tables/Grp/Org/Web/TableWebpages.vue"
import TableExternalLinks from "@/Components/Tables/Grp/Org/Web/TableExternalLinks.vue"
import { capitalize } from "@/Composables/capitalize"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import WebsiteShowcase from "@/Components/Showcases/Org/WebsiteShowcase.vue"
import TableWebUsers from "@/Components/Tables/Grp/Org/CRM/TableWebUsers.vue"
import WebsiteAnalytics from "@/Pages/Grp/Org/Web/WebsiteAnalytics.vue"
import TableRedirects from "@/Components/Tables/Grp/Org/Web/TableRedirects.vue"
import { PageHeadingTypes } from "@/types/PageHeading";
import { routeType } from "@/types/route"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import { useForm } from '@inertiajs/vue3'
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import { trans } from "laravel-vue-i18n"
import { ctrans } from "@/Composables/useTrans"
import { notify } from "@kyvg/vue3-notification"

library.add(
    faChartLine,
    faClock,
    faAnalytics,
    faUsersClass,
    faDraftingCompass,
    faSlidersH,
    faRoad,
    faBrowser,
    faSeedling,
    faBroadcastTower,
    faSkull,
    faRocket,
    faExternalLink,
    faFolderDownload,
    faDownload
)


const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: object
    }
    redirects: {}
    download_route: routeType
    route_redirects: {
        submit: {
            name: string
            parameters: {}
        }
        fetch_live_webpages: {
            name: string
            parameters: {}
        }
    }
}>()

/* provide('layout', {})
const layout = {} */
let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components = {
        redirects: TableRedirects,
    }
    return components[currentTab.value]

})

const isDownloadingCsv = ref(false)

const downloadCsv = () => {
    if (isDownloadingCsv.value || !props.download_route?.name) return

    isDownloadingCsv.value = true
    

    setTimeout(() => {
        notify({
            title: trans('Export CSV'),
            text: trans('Download will start shortly...'),
            type: 'info',
        })
        const url = route(props.download_route.name, { ...props.download_route.parameters, type: 'csv' })
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', '')
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        isDownloadingCsv.value = false
    }, 1000)
}

const openModal = ref(false)
const form = useForm({
    from_url: '',
    to_url: ''
})
const submitForm = () => {
    if (!props?.route_redirects?.submit?.name) {
        console.log('No submit route')
        return 
    }
    form.post(route(props.route_redirects.submit.name, props.route_redirects.submit.parameters), {
        preserveScroll: true,
        onSuccess: () => {
            openModal.value = false
            form.reset()
            notify({
                title: trans("Success!"),
                text: trans("New redirect created successfully."),
                type: "success",
            })
        },
        onError: () => {
            
        }
    })
}


</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button
                v-if="download_route?.name"
                :icon="faDownload"
                :label="ctrans('Export CSV')"
                type="tertiary"
                :loading="isDownloadingCsv"
                @click="downloadCsv"
            />
            <Button type="create" :label="trans('Redirect')" @click="openModal = true" />
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component
        :is="component"
        :data="props[currentTab]"
        :tab="currentTab"
    />

    <Modal :isOpen="openModal" width="w-full max-w-md" closeButton @onClose="openModal = false">
        <slot name="modal" :closeModal="() => openModal = false">
            <div class="space-y-2">
                <!-- Modal Title -->
                <h2 class="text-xl font-semibold pb-2 border-b">{{ trans("Create Redirect") }}</h2>

                <!-- Form -->
                <form @submit.prevent="submitForm" class="space-y-3">
                    <!-- From URL -->
                    <div>
                        <div class="block text-sm font-medium py-2">{{ trans("From URL:") }}</div>
                        <PureInput
                            v-model="form.from_url"
                            placeholder="e.g. /old-page"
                            :class="{ 'border-red-500': form.errors.from_url }"
                            :disabled="form.processing"
                        />
                        <p v-if="form.errors.from_url" class="text-sm text-red-600 mt-1">
                            {{ form.errors.from_url }}
                        </p>
                    </div>

                    <!-- To URL -->
                    <div>
                        <div class="block text-sm font-medium py-2">{{ trans("Target URL:") }}</div>
                        <PureMultiselectInfiniteScroll
                            v-model="form.to_url"
                            :fetchRoute="route_redirects.fetch_live_webpages"
                            :placeholder="trans('Select Redirect')"
                            valueProp="id"
                            labelProp="url"
                            :disabled="form.processing"
                        >
                            <template #singlelabel="{ value }">
                                <div
                                    class="w-full text-left pl-3 pr-2 text-sm whitespace-nowrap truncate">
                                    {{ value.slug }}
                                    <span v-if="value.code" class="text-sm text-gray-400">(/{{ value.href }})</span>
                                </div>
                            </template>

                            <template #option="{ option, isSelected, isPointed }">
                                <!-- <pre>{{ option }}</pre> -->
                                <div class="">{{ option.slug }} <span v-if="option.code" class="text-sm"
                                    :class="isSelected(option) ? 'text-indigo-200' : 'text-gray-400'">(/{{ option.href }})</span>
                                </div>
                            </template>
                        </PureMultiselectInfiniteScroll>
                        <p v-if="form.errors.to_url" class="text-sm text-red-600 mt-1">
                            {{ form.errors.to_url }}
                        </p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-2">
                        <Button label="Cancel" @click="() => openModal = false" type="white" />
                        <Button type="save" :label="trans('Create Redirect')" full :disabled="form.processing"  @click="() => submitForm()" />
                    </div>
                </form>
            </div>
        </slot>
    </Modal>

</template>
