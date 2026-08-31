<script setup lang="ts">
import { ref, computed, watch, inject, onMounted } from 'vue'
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
import "@vueform/multiselect/themes/default.css"
import Tag from '@/Components/Tag.vue'
import { PageHeadingTypes } from "@/types/PageHeading";
import { library } from '@fortawesome/fontawesome-svg-core'
import { faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow, faPaperPlane, faPlus, faExclamationTriangle, faSyncAlt } from '@fal'
import { faUserCog } from '@fas'
import MailshotJourney from '@/Components/Navigation/MailshotJourney.vue'
import MailshotSubjectEdit from '@/Components/Workshop/Mailshot/MailshotSubjectEdit.vue'
import Tabs from "@/Components/Navigation/Tabs.vue";
import Modal from '@/Components/Utils/Modal.vue'
import { routeType } from '@/types/route'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import { data } from "autoprefixer"
import { useTabChange } from "@/Composables/tab-change";
import TableEmailTemplate from "@/Components/Tables/TableEmailTemplate.vue";
import TablePreviousMailshots from "@/Components/Tables/TablePreviousMailshots.vue"
import TableOtherStoreMailshots from "@/Components/Tables/TableOtherStoreMailshots.vue"
import TemplateGallery from "@/Components/Mailshot/TemplateGallery.vue"
import { faThLarge, faList } from '@fal'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { usePage } from "@inertiajs/vue3"

library.add(faThLarge, faList, faUserCog, faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow, faExclamationTriangle)

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    builder: string
    imagesUploadRoute: routeType
    updateRoute: routeType
    snapshot: routeType
    mergeTags: Array<any>
    status: string
    publishRoute: routeType
    sendTestRoute: routeType
    organisationSlug: string
    storeNewTemplateRoute: routeType
    unpublished_layout: any
    compiledLayout: string | null
    journey?: any
    openTemplateSelector?: boolean
    mailshot: { subject: string, name: string | null, preview_text: string | null }
    updateMailshotRoute: routeType
    suggestCopyRoute: routeType
}>()

const mailshotSavedSubject = ref(props.mailshot.subject)
const pageHeadData = computed(() => ({ ...props.pageHead, title: mailshotSavedSubject.value }))

const isPublished = ref(!!props.compiledLayout)
const showUnpublishedWarning = ref(false)
const pendingReviewRoute = ref<any>(null)
// ponytail: warns only when never published; edits after a publish slip through silently
const onReviewClick = (action: any) => {
    if (isPublished.value) {
        router.visit(route(action.route.name, action.route.parameters))
    } else {
        pendingReviewRoute.value = action.route
        showUnpublishedWarning.value = true
    }
}
const goToReviewAnyway = () => {
    showUnpublishedWarning.value = false
    router.visit(route(pendingReviewRoute.value.name, pendingReviewRoute.value.parameters))
}

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

const onSendPublish = async (data) => {
    compiledLayout.value = data?.htmlFile

    try {
        const response = await axios.post(route(props.publishRoute.name, props.publishRoute.parameters), {
            comment: comment.value,
            layout: JSON.parse(data?.jsonFile),
            compiled_layout: data?.htmlFile
        });

        if (response && response.status === 200) {
            isPublished.value = true
            // if (response.data.has_unsubscribelink === false) {
            //     visibleUnsubscribeWarningModal.value = true

            //     notify({
            //         title: "Warning",
            //         text: "Saved successfully, but no unsubscribe link was found.",
            //         type: "warning",
            //     });
            // } else {
            notify({
                title: "Success",
                text: "Saved successfully",
                type: "success",
            });
            // }
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
        layout: data?.jsonFile,
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
                title: trans('Success!'),
                text: trans('Success to save template'),
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

const templateView = ref<'gallery' | 'list'>((localStorage.getItem('mailshot-template-view') as 'gallery' | 'list') ?? 'gallery')

const setTemplateView = (view: 'gallery' | 'list') => {
    templateView.value = view
    localStorage.setItem('mailshot-template-view', view)
}

const component = computed(() => {
    if (templateView.value === 'gallery') {
        return TemplateGallery
    }

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

onMounted(() => {
  window.addEventListener('popstate', () => {
    router.reload()
  });

  if (props.openTemplateSelector) {
    isModalCloneTemplateEmail.value = true
  }
})
</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHeadData">
        <template #afterTitle>
            <MailshotSubjectEdit :mailshot="mailshot" :updateMailshotRoute="updateMailshotRoute"
                :suggestCopyRoute="suggestCopyRoute" @saved="subject => mailshotSavedSubject = subject" />
        </template>
        <template #afterTitle2>
            <MailshotJourney :steps="journey" class="ml-4" />
        </template>
        <template #otherBefore>
            <Button @click="() => isModalCloneTemplateEmail = true" :label="trans('Choose Template')"
                class="flex flex-wrap border border-gray-300 rounded-md overflow-hidden h-fit" type="secondary"
                :icon="faSyncAlt" :disabled="!isBeefreeReady" />
        </template>
        <template #button-index-0="{ action }">
            <Button :label="action.label" type="primary" iconRight="fal fa-arrow-right"
                @click="() => onReviewClick(action)" />
        </template>
    </PageHeading>

    <Modal :isOpen="showUnpublishedWarning" @onClose="showUnpublishedWarning = false" width="w-full max-w-md">
        <div class="p-2 text-center">
            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500 text-3xl mb-3" fixed-width />
            <h2 class="text-lg font-semibold mb-2">{{ trans('Your email is not saved yet') }}</h2>
            <p class="text-gray-600 mb-4">
                {{ trans('Press the SAVE button in the editor to publish your email, otherwise it cannot be sent.') }}
            </p>
            <div class="flex justify-center gap-x-2">
                <Button type="tertiary" :label="trans('Review & send anyway')" @click="goToReviewAnyway" />
                <Button :label="trans('Keep editing')" @click="showUnpublishedWarning = false" />
            </div>
        </div>
    </Modal>

    <Modal :isOpen="isModalCloneTemplateEmail" @onClose="isModalCloneTemplateEmail = false" width="w-full max-w-6xl">

        <div class="flex items-start justify-between gap-x-4">
            <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" class="flex-1" />
            <div class="flex items-center rounded-md border border-gray-300 overflow-hidden shrink-0">
                <button
                    v-tooltip="trans('Gallery')"
                    class="px-2 py-1"
                    :class="templateView === 'gallery' ? 'bg-gray-100 text-gray-900' : 'text-gray-400 hover:text-gray-600'"
                    @click="setTemplateView('gallery')">
                    <FontAwesomeIcon icon="fal fa-th-large" fixed-width aria-hidden="true" />
                </button>
                <button
                    v-tooltip="trans('List')"
                    class="px-2 py-1"
                    :class="templateView === 'list' ? 'bg-gray-100 text-gray-900' : 'text-gray-400 hover:text-gray-600'"
                    @click="setTemplateView('list')">
                    <FontAwesomeIcon icon="fal fa-list" fixed-width aria-hidden="true" />
                </button>
            </div>
        </div>


        <component :is="component" :key="currentTab + templateView" :data="tabData" :tab="currentTab"
            @select-snapshot="onSelectTemplateSnapshot" />

    </Modal>

    <!-- beefree -->
    <Beetree v-if="builder == 'beefree'" :updateRoute="updateRoute" :imagesUploadRoute="imagesUploadRoute"
        :snapshot="activeSnapshot" :mergeTags="mergeTags" :organisationSlug="organisationSlug" @onSave="onSendPublish"
        @sendTest="openSendTest" @auto-save="autoSave" @saveTemplate="onSaveTemplate" ref="_beefree"
        :unpublished_layout="unpublished_layout" @ready="isBeefreeReady = $event" />

    <!-- unlayer -->
    <Unlayer v-else-if="builder == 'unlayer'" :updateRoute="updateRoute" :imagesUploadRoute="imagesUploadRoute"
        :snapshot="snapshot" ref="_unlayer" />

    <div v-if="builder == 'beefree' && compiledLayoutSize > 102"
        class="flex justify-end items-center gap-2 px-4 py-2 border-t border-gray-200 text-xs text-yellow-600">
        <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500" v-tooltip="emailSizeWarningTooltip" fixed-width />
        Estimated email size <span class="font-semibold">{{ compiledLayoutSize }} KB</span> exceeds Gmail's 102 KB limit
    </div>

    <div v-if="builder != 'beefree' && builder != 'unlayer'">
        <EmptyState :data="{
            title: 'Builder Not Set Up',
            description: 'you need to set up the builder'
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
            <div class="font-semibold mb-3">Template Name</div>
            <PureInput v-model="templateName" placeholder="Template Name" :disabled="isLoadingTemplate" />
            <div v-if="isLoadingTemplate" class="text-left text-gray-500 mt-3 text-sm">
                Please wait a moment. This may take a few seconds while the content is being converted to HTML ...
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
                <div class="font-semibold text-lg mb-2">Missing Unsubscribe Link</div>
                <div class="text-gray-600">This mailshot/newsletter doesn't contain an unsubscribe link. Please consider
                    adding one to ensure compliance with email regulations and provide recipients with a clear option to
                    unsubscribe.</div>
            </div>
            <div class="flex justify-center mt-4">
                <Button @click="closeUnsubscribeWarningModal" label="OK" type="primary"></Button>
            </div>
        </div>
    </Dialog>

</template>
