<script setup lang="ts">
import { ref, reactive, computed, watch, inject , onMounted} from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import Unlayer from "@/Components/CMS/Website/Outboxes/Unlayer/UnlayerV2.vue"
import EmailWorkshop from '@/Components/CMS/Website/Outboxes/EmailWorkshop/EmailWorkshop.vue'
import Beetree from '@/Components/CMS/Website/Outboxes/Beefree.vue'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import Dialog from 'primevue/dialog';
import PureInput from "@/Components/Pure/PureInput.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { ctrans } from "@/Composables/useTrans"
import 'v-calendar/style.css'
import Multiselect from "@vueform/multiselect"
import "@vueform/multiselect/themes/default.css"
import Tag from '@/Components/Tag.vue'
import { PageHeadingTypes } from "@/types/PageHeading";
import { library } from '@fortawesome/fontawesome-svg-core'
import { faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow, faPaperPlane, faPlus, faExclamationTriangle, faThLarge, faLink } from '@fal'
import { faUserCog } from '@fas'
import MailshotJourney from '@/Components/Navigation/MailshotJourney.vue'
import MailshotSubjectEdit from '@/Components/Workshop/Mailshot/MailshotSubjectEdit.vue'
import MailshotUtmLinks from '@/Components/Workshop/Mailshot/MailshotUtmLinks.vue'
import Tabs from "@/Components/Navigation/Tabs.vue";
import { routeType } from '@/types/route'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import { data } from "autoprefixer"
import { useTabChange } from "@/Composables/tab-change";
import TemplatePicker from "@/Components/Mailshot/TemplatePicker.vue"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { usePage } from "@inertiajs/vue3"

library.add(faUserCog, faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow, faExclamationTriangle)

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    builder: string
    imagesUploadRoute: routeType
    videoThumbnailRoute?: routeType
    emailEditor?: 'aiku' | 'beefree'
    websiteTheme?: { color: string[], fontFamily: string | null } | null
    imageCategories?: Array<{ key: string, label: string, route: routeType }>
    updateRoute: routeType
    snapshot: routeType
    unpublished_layout: any
    compiledLayout: string | null
    mergeTags: Array<any>
    socialIcons?: Record<string, string>
    mergeContents: Array<any> | null
    status: string
    publishRoute: routeType
    sendTestRoute: routeType
    organisationSlug: string
    shopSlug: string
    shopId: number
    storeNewTemplateRoute: routeType
    mailshot: { subject: string, name: string | null, preview_text: string | null }
    openTemplateSelector?: boolean
    journey?: any
    updateMailshotRoute: routeType
    suggestCopyRoute: routeType
    utmLinksRoute: routeType
    updateUtmLinkRoute: routeType
    updateUtmSettingsRoute: routeType
}>()

const isUtmLinksModalOpen = ref(false)

const mailshotData = reactive({ ...props.mailshot })
const pageHeadData = computed(() => ({ ...props.pageHead, title: mailshotData.subject }))

const onMailshotSaved = (savedMailshot: { subject: string, name: string | null, preview_text: string | null }) => {
    Object.assign(mailshotData, savedMailshot)
}

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
const _emailWorkshop = ref()
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
            isPublished.value = true
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
            title: ctrans('Success!'),
            text: ctrans('Test email sent successfully'),
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
                title: ctrans('Success'),
                text: ctrans('Saved successfully'),
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
            onSuccess: () => {
                notify({
                    title: ctrans('Success!'),
                    text: ctrans('change status'),
                    type: 'success',
                })
            },
            onError: () => {
                notify({
                    title: ctrans('Something went wrong'),
                    text: ctrans('Unsuccessfully change status'),
                    type: 'error',
                })
            },
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
    } catch (error) {
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
            <MailshotSubjectEdit :mailshot="mailshotData" :updateMailshotRoute="updateMailshotRoute"
                :suggestCopyRoute="suggestCopyRoute" @saved="(subject, savedMailshot) => onMailshotSaved(savedMailshot)" />
        </template>
        <template #afterTitle2>
            <MailshotJourney :steps="journey" class="ml-4" />
        </template>
        <template v-if="builder == 'beefree' && emailEditor === 'beefree'" #otherBefore>
            <Button @click="() => isModalCloneTemplateEmail = true" :label="ctrans('Choose Template')"
                class="flex flex-wrap border border-gray-300 rounded-md overflow-hidden h-fit" type="secondary"
                :icon="faThLarge" :disabled="!isBeefreeReady" />
        </template>
        <template #button-utm="{ action }">
            <Button :label="action.label" type="tertiary" :icon="faLink" @click="isUtmLinksModalOpen = true" />
        </template>
        <template #button-review="{ action }">
            <Button :label="action.label" type="primary" iconRight="fal fa-arrow-right"
                @click="() => onReviewClick(action)" />
        </template>
    </PageHeading>

    <MailshotUtmLinks :isOpen="isUtmLinksModalOpen" :utmLinksRoute="utmLinksRoute"
        :updateUtmLinkRoute="updateUtmLinkRoute" :updateUtmSettingsRoute="updateUtmSettingsRoute"
        @onClose="isUtmLinksModalOpen = false" />

    <Dialog v-model:visible="showUnpublishedWarning" modal dismissableMask :draggable="false" :showHeader="false"
        :style="{ width: '28rem' }" :breakpoints="{ '640px': '95vw' }">
        <div class="pt-6 text-center">
            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500 text-3xl mb-3" fixed-width />
            <h2 class="text-lg font-semibold mb-2">{{ ctrans('Your email is not saved yet') }}</h2>
            <p class="text-gray-600 mb-4">
                {{ ctrans('Press Ctrl+S (⌘S on Mac) in the editor to publish your email, otherwise it cannot be sent.') }}
            </p>
            <div class="flex justify-center gap-x-2">
                <Button type="tertiary" :label="ctrans('Review & send anyway')" @click="goToReviewAnyway" />
                <Button :label="ctrans('Keep editing')" @click="showUnpublishedWarning = false" />
            </div>
        </div>
    </Dialog>

    <Dialog v-model:visible="isModalCloneTemplateEmail" modal :draggable="false" :header="ctrans('Choose a template')"
        :style="{ width: '80rem' }" :breakpoints="{ '1360px': '95vw' }"
        :pt="{ header: { class: '!px-5 !py-3 border-b border-gray-200' }, content: { class: '!px-5 !pb-5 !pt-2' } }">
        <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
        <TemplatePicker :key="currentTab" :data="tabData" :tab="currentTab"
            @select-snapshot="onSelectTemplateSnapshot" />
    </Dialog>

    <template v-if="builder == 'beefree'">
        <Beetree v-if="emailEditor === 'beefree'" :updateRoute="updateRoute" :imagesUploadRoute="imagesUploadRoute"
            :snapshot="activeSnapshot" :unpublished_layout="unpublished_layout" :mergeTags="mergeTags"
            :mergeContents="mergeContents" :organisationSlug="organisationSlug" :shopSlug="shopSlug" :shopId="shopId"
            @onSave="onSendPublish" @sendTest="openSendTest" @auto-save="autoSave" @saveTemplate="onSaveTemplate"
            ref="_beefree" @ready="isBeefreeReady = $event" />

        <EmailWorkshop v-else :updateRoute="updateRoute" :imagesUploadRoute="imagesUploadRoute" :videoThumbnailRoute="videoThumbnailRoute" :websiteTheme="websiteTheme" :imageCategories="imageCategories"
            :snapshot="activeSnapshot" :unpublished_layout="unpublished_layout" :mergeTags="mergeTags" :socialIcons="socialIcons"
            :mergeContents="mergeContents" :organisationSlug="organisationSlug" :shopSlug="shopSlug" :shopId="shopId"
            @onSave="onSendPublish" @sendTest="openSendTest" :autoSaveRoute="updateRoute" @saveTemplate="onSaveTemplate"
            :mailshot="mailshotData" :updateMailshotRoute="updateMailshotRoute" @mailshotSaved="onMailshotSaved"
            ref="_emailWorkshop">
            <template #toolbar>
                <button type="button" class="flex h-8 items-center gap-x-1.5 rounded px-3 text-[13px] text-gray-700 hover:bg-gray-100"
                    @click="isModalCloneTemplateEmail = true">
                    <FontAwesomeIcon :icon="faThLarge" fixed-width aria-hidden="true" />
                    {{ ctrans('Choose template') }}
                </button>
            </template>
        </EmailWorkshop>
    </template>

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
