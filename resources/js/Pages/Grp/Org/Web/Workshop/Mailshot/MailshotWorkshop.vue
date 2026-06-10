<script setup lang="ts">
import { ref, computed, watch, inject } from 'vue'
import type { Component } from "vue";
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import Unlayer from "@/Components/CMS/Website/Outboxes/Unlayer/UnlayerV2.vue"
import Beetree from '@/Components/CMS/Website/Outboxes/Beefree.vue'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import Dialog from 'primevue/dialog';
import PureInput from "@/Components/Pure/PureInput.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { trans } from "laravel-vue-i18n"
import 'v-calendar/style.css'
import Multiselect from "@vueform/multiselect"
import Tag from '@/Components/Tag.vue'
import { PageHeadingTypes } from "@/types/PageHeading";
import { library } from '@fortawesome/fontawesome-svg-core'
import { faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow, faPaperPlane, faPlus, faExclamationTriangle } from '@fal'
import { faUserCog } from '@fas'
import Tabs from "@/Components/Navigation/Tabs.vue";
import Modal from '@/Components/Utils/Modal.vue'
import { routeType } from '@/types/route'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import { data } from "autoprefixer"
import { useTabChange } from "@/Composables/tab-change";
import TableEmailTemplate from "@/Components/Tables/TableEmailTemplate.vue";
import TablePreviousMailshots from "@/Components/Tables/TablePreviousMailshots.vue"
import TableOtherStoreMailshots from "@/Components/Tables/TableOtherStoreMailshots.vue"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { usePage } from "@inertiajs/vue3"

library.add(faUserCog, faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow, faExclamationTriangle)

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    builder: string
    imagesUploadRoute: routeType
    updateRoute: routeType
    snapshot: routeType
    unpublished_layout: any
    compiledLayout: string | null
    mergeTags: Array<any>
    mergeContents: Array<any> | null
    status: string
    publishRoute: routeType
    sendTestRoute: routeType
    organisationSlug: string
    shopSlug: string
    shopId: number
    storeNewTemplateRoute: routeType
}>()

const comment = ref('')
const isLoading = ref(false)
const isLoadingTemplate = ref(false)
const openTemplates = ref(false)
const _beefree = ref()
const _unlayer = ref()
const visibleEmailTestModal = ref(false)
const visibleSAveEmailTemplateModal = ref(false)
const visibleUnsubscribeWarningModal = ref(false)
const email = ref('')
const templateName = ref('')
const temporaryData = ref()
const active = ref(props.status)
const _popover = ref()
const date = ref(new Date())
const options = ref([
    { name: 'Active', value: "active" },
    { name: 'Suspended', value: "suspended" },
]);

const compiledLayout = ref(props.compiledLayout ?? '')
const compiledLayoutSize = computed(() => {
    return (new Blob([compiledLayout.value]).size / 1024).toFixed(2)
})

const emailSizeWarningTooltip = computed(() => {
    return `Your email content is ${compiledLayoutSize.value} KB, which exceeds Gmail’s recommended 102 KB limit`
})


const onSendPublish = async (data: any) => {
    compiledLayout.value = data?.htmlFile

    try {
        const response = await axios.post(route(props.publishRoute.name, props.publishRoute.parameters), {
            comment: comment.value,
            layout: JSON.parse(data?.jsonFile),
            compiled_layout: data?.htmlFile
        });

        if (response && response.status === 200) {
            if (response.data.has_unsubscribelink === false) {
                visibleUnsubscribeWarningModal.value = true

                notify({
                    title: "Warning",
                    text: "Saved successfully, but no unsubscribe link was found.",
                    type: "warning",
                });
            } else {
                notify({
                    title: "Success",
                    text: "Saved successfully",
                    type: "success",
                });
            }
        }
    } catch (error) {
        console.log(error)
        const errorMessage = error.response?.data?.message || error.message || "Unknown error occurred";
        notify({
            title: "Something went wrong.",
            text: errorMessage,
            type: "error",
        });
    } finally {
        isLoading.value = false;
    }
}


const openSendTest = (data) => {
    visibleEmailTestModal.value = true
    temporaryData.value = {
        compiled_layout: data?.htmlFile
    }
}

const onSaveTemplate = (data: any) => {
    visibleSAveEmailTemplateModal.value = true
    temporaryData.value = {
        layout: data?.jsonFile
    }
}

const sendTestToServer = () => {
    isLoading.value = true;
    axios.post(route(props.sendTestRoute.name, props.sendTestRoute.parameters),
        { ...temporaryData.value, email: email.value }
    ).then((response) => {
        notify({
            title: trans('Success!'),
            text: trans('Test email sent successfully'),
            type: 'success',
        });
        email.value = '';
    }).catch((error) => {
        console.error("Error in sendTest:", error);
        visibleEmailTestModal.value = false
        temporaryData.value = null
        const errorMessage = error.response?.data?.message || error.message || "An unknown error occurred.";
        notify({
            title: "Something went wrong",
            text: errorMessage,
            type: "error",
        });
    }).finally(() => {
        isLoading.value = false;
        visibleEmailTestModal.value = false
        temporaryData.value = null
    });
};


const closeUnsubscribeWarningModal = () => {
    visibleUnsubscribeWarningModal.value = false
}

const saveTemplate = async () => {
    isLoadingTemplate.value = true;

    axios
        .post(
            route(props.storeNewTemplateRoute.name, props.storeNewTemplateRoute.parameters),
            {
                name: templateName.value,
                layout: JSON.parse(temporaryData.value?.layout)
            },
        )
        .then((response) => {
            visibleSAveEmailTemplateModal.value = false
            notify({
                title: trans('Success'),
                text: trans('Saved successfully'),
                type: 'success',
            })
        })
        .catch((error) => {
            notify({
                title: "Failed to save template",
                type: "error",
            })
        })
        .finally(() => {
            visibleSAveEmailTemplateModal.value = false;
            templateName.value = '';
            temporaryData.value = null;
            isLoadingTemplate.value = false;
        });
}

const updateActiveValue = async (action) => {
    router.patch(route(action.name, action.parameters),
        { active: active.value },
        {
            onStart: () => console.log('start'),
            onSuccess: () => {
                notify({
                    title: trans('Success!'),
                    text: trans('change status'),
                    type: 'success',
                })
            },
            onError: () => {
                notify({
                    title: trans('Something went wrong'),
                    text: trans('Unsuccessfully change status'),
                    type: 'error',
                })
            },
            onFinish: () => console.log('finish'),
        }
    )
}

const autoSave = async (jsonFile) => {
    axios
        .patch(
            route(props.updateRoute.name, props.updateRoute.parameters),
            {
                layout: JSON.parse(jsonFile),
                /*  compiled_layout: htmlFile */
            },
        )
        .then((response) => {
            // console.log("autosave successful:", response.data);
            // Handle success (equivalent to onFinish)
        })
        .catch((error) => {
            console.error("autosave failed:", error);
            notify({
                title: "Failed to save",
                type: "error",
            })
        })
        .finally(() => {
            // console.log("autosave finished.");
        });
}

const onSchedulePublish = (event) => {
    event.stopPropagation()
    _popover.value.toggle(event);
}

const schedulePublish = async () => {
    try {
        const response = await axios.post(route('xxxxx'), {
            comment: comment.value,
            layout: JSON.parse(data?.jsonFile),
            compiled_layout: data?.htmlFile
        });
        console.log("Publish response:", response.data);
    } catch (error) {
        console.log(error)
        const errorMessage = error.response?.data?.message || error.message || "Unknown error occurred";
        notify({
            title: "Something went wrong.",
            text: errorMessage,
            type: "error",
        });
    } finally {
        isLoading.value = false;
    }
}

const isModalCloneTemplateEmail = ref(false)
const activeSnapshot = ref(props.snapshot)

const page = usePage()
const tabs = computed(() => page.props.tabs)
const currentTab = ref<string>(tabs.value.current)
const isBeefreeReady = ref(false)

const tabData = computed(() => {
    return page.props[currentTab.value] ?? []
})

const handleTabUpdate = (tabSlug: string) =>
    useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        templates: TableEmailTemplate,
        other_store_templates: TableEmailTemplate,
        previous_mailshots: TablePreviousMailshots,
        other_store_mailshots: TableOtherStoreMailshots,
    };
    return components[currentTab.value];
});

const onSelectTemplateSnapshot = (snapshot: any) => {
    activeSnapshot.value = snapshot
    isModalCloneTemplateEmail.value = false
}

watch(
    () => tabs.value.current,
    (val) => {
        currentTab.value = val
    }
)
</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <div>
                <div class="text-sm text-gray-600 mr-2 flex items-center gap-2">
                    Estimated email size: approximately <span class="font-semibold">{{ compiledLayoutSize }} KB</span>
                    <FontAwesomeIcon v-if="compiledLayoutSize > 102" :icon="faExclamationTriangle"
                        class="text-yellow-500 text-lg" v-tooltip="emailSizeWarningTooltip" fixed-width />
                </div>
            </div>

            <Button @click="() => isModalCloneTemplateEmail = true" :label="trans('Choose Template')"
                class="flex flex-wrap border border-gray-300 rounded-md overflow-hidden h-fit" type="secondary"
                :icon="faPlus" :disabled="!isBeefreeReady" />
        </template>
    </PageHeading>

    <Modal :isOpen="isModalCloneTemplateEmail" @onClose="isModalCloneTemplateEmail = false" width="w-full max-w-6xl">

        <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />


        <component :is="component" :key="currentTab" :data="tabData" :tab="currentTab"
            @select-snapshot="onSelectTemplateSnapshot" />

    </Modal>

    <!-- beefree -->
    <Beetree v-if="builder == 'beefree'" :updateRoute="updateRoute" :imagesUploadRoute="imagesUploadRoute"
        :snapshot="activeSnapshot" :unpublished_layout="unpublished_layout" :mergeTags="mergeTags"
        :mergeContents="mergeContents" :organisationSlug="organisationSlug" :shopSlug="shopSlug" :shopId="shopId"
        @onSave="onSendPublish" @sendTest="openSendTest" @auto-save="autoSave" @saveTemplate="onSaveTemplate"
        ref="_beefree" @ready="isBeefreeReady = $event" />

    <!-- unlayer -->
    <Unlayer v-else-if="builder == 'unlayer'" :updateRoute="updateRoute" :imagesUploadRoute="imagesUploadRoute"
        :snapshot="snapshot" ref="_unlayer" />

    <div v-else>
        <EmptyState :data="{
            title: 'Builder Not Set Up',
            description: 'you neeed to set up the builder'
        }" />
    </div>

    <Dialog v-model:visible="visibleEmailTestModal" modal :closable="false" :showHeader="false"
        :style="{ width: '25rem' }">
        <div class="pt-4">
            <div class="font-semibold w-24 mb-3">Email</div>
            <PureInput v-model="email" placeholder="Email" />
            <div class="flex justify-end mt-3 gap-3">
                <Button :type="'tertiary'" label="Cancel" @click="visibleEmailTestModal = false"
                    :disabled="isLoading"></Button>
                <Button @click="sendTestToServer" :icon="faPaperPlane" label="Send" :loading="isLoading"
                    :disabled="!email"></Button>
            </div>
        </div>
    </Dialog>

    <Dialog v-model:visible="visibleSAveEmailTemplateModal" modal :closable="false" :showHeader="false"
        :style="{ width: '25rem' }">
        <div class="pt-4">
            <div class="font-semibold mb-3"> {{ ctrans('Template Name') }}</div>
            <PureInput v-model="templateName" :placeholder="ctrans('Template Name')" :disabled="isLoadingTemplate" />
            <div v-if="isLoadingTemplate" class="text-left text-black mt-3 text-sm w-full">
                {{ ctrans('Please wait a moment. This may take a few seconds while the content is being converted to HTML ...')}}
            </div>
            <div class="flex justify-end mt-3 gap-3">
                <Button :type="'tertiary'" label="Cancel" @click="visibleSAveEmailTemplateModal = false" :disabled="isLoadingTemplate"></Button>
                <Button type="save" @click="saveTemplate" :loading="isLoadingTemplate" :disabled="isLoadingTemplate"></Button>
            </div>
        </div>
    </Dialog>

    <Dialog v-model:visible="visibleUnsubscribeWarningModal" modal :closable="false" :showHeader="false"
        :style="{ width: '30rem' }">
        <div class="pt-4">
            <div class="text-center mb-4">
                <div class="text-amber-500 text-4xl mb-3">⚠️</div>
                <div class="font-semibold text-lg mb-2"> {{ ctrans('Missing Unsubscribe Link') }}</div>
                <div class="text-gray-600"> {{ ctrans(`This mailshot/newsletter doesn't contain an unsubscribe link. Please consider
                    adding one to ensure compliance with email regulations and provide recipients with a clear option to
                    unsubscribe.`) }}</div>
            </div>
            <div class="flex justify-center mt-4">
                <Button @click="closeUnsubscribeWarningModal" label="OK" type="primary"></Button>
            </div>
        </div>
    </Dialog>


</template>
