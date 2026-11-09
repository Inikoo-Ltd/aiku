<script setup lang="ts">
import { ref, computed, watch, inject } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import EmailWorkshop from '@/Components/CMS/Website/Outboxes/EmailWorkshop/EmailWorkshop.vue'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import Dialog from 'primevue/dialog';
import ModalConfirmation from '@/Components/Utils/ModalConfirmation.vue'
import PureInput from "@/Components/Pure/PureInput.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { ctrans } from "@/Composables/useTrans"
import 'v-calendar/style.css'
import Multiselect from "@vueform/multiselect"
import "@vueform/multiselect/themes/default.css"
import Tag from '@/Components/Tag.vue'
import { PageHeadingTypes } from "@/types/PageHeading";
import { library } from '@fortawesome/fontawesome-svg-core'
import { faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow, faPaperPlane, faPlus, faTrashAlt, faExclamationTriangle } from '@fal'
import { faUserCog } from '@fas'
import { routeType } from '@/types/route'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { useFreshAfterHistoryRestore } from "@/Composables/useFreshAfterHistoryRestore"

library.add(faUserCog, faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow, faExclamationTriangle)

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    builder: string
    imagesUploadRoute: routeType
    videoThumbnailRoute?: routeType
    websiteTheme?: { color: string[], fontFamily: string | null } | null
    imageCategories?: Array<{ key: string, label: string, route: routeType }>
    updateRoute: routeType
    snapshot: routeType
    mergeTags: Array<any>
    socialIcons?: Record<string, string>
    sendTestRoute: routeType
    storeTemplateRoute: routeType
    deleteTemplateRoute: routeType
    organisationSlug: string
    shopSlug: string
    indexRoute: routeType
    can_edit?: boolean
}>()

const isRefreshingLayout = useFreshAfterHistoryRestore(['snapshot'], () => {})

const isLoading = ref(false)
const isLoadingTemplate = ref(false)
const inProgress = ref(false)
const visibleEmailTestModal = ref(false)
const visibleSAveEmailTemplateModal = ref(false)
const email = ref('')
const templateName = ref('')
const saveTemplateError = ref<string | null>(null)
const temporaryData = ref()

const openSendTest = (data: any) => {
    if (!props.can_edit) {
        return
    }

    visibleEmailTestModal.value = true
    temporaryData.value = {
        compiled_layout: data?.htmlFile
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
    })
        .finally(() => {
            isLoading.value = false;
            visibleEmailTestModal.value = false
            temporaryData.value = null
        });
};

const onSaveTemplate = (data: any) => {
    if (!props.can_edit) {
        return
    }

    visibleSAveEmailTemplateModal.value = true
    saveTemplateError.value = null
    temporaryData.value = {
        layout: data?.jsonFile,
        compiled_layout: data?.htmlFile,
    }
}

const saveTemplate = async (data: any) => {
    isLoadingTemplate.value = true;
    axios
        .post(
            route(props.storeTemplateRoute.name, props.storeTemplateRoute.parameters),
            {
                name: templateName.value,
                layout: JSON.parse(temporaryData.value?.layout),
                compiled_layout: temporaryData.value?.compiled_layout,
            },
        )
        .then(() => {
            visibleSAveEmailTemplateModal.value = false
            templateName.value = ''
            temporaryData.value = null
        })
        .catch((error) => {
            saveTemplateError.value = error.response?.data?.message || ctrans('Failed to save template')
        })
        .finally(() => {
            isLoadingTemplate.value = false
        });
}

const onSave = async (data: any) => {
    if (!props.can_edit) {
        notify({
            title: ctrans("View only"),
            text: ctrans("You can view this template but not change it."),
            type: "info",
        })
        return
    }

    try {
        const response = await axios.patch(route(props.updateRoute.name, props.updateRoute.parameters), {
            layout: JSON.parse(data?.jsonFile),
            compiled_layout: data?.htmlFile
        });

        if (response && response.status === 200) {
            notify({
                title: "Success",
                text: "Successfully updated template",
                type: "success",
            });
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


const handleDelete = async () => {
    if (inProgress.value) {
        return;
    }

    inProgress.value = true;

    if (!props.deleteTemplateRoute) {
        notify({
            title: 'Error',
            text: 'Delete route not configured',
        })
        inProgress.value = false;
        return;
    }

    await axios.delete(route(props.deleteTemplateRoute.name, props.deleteTemplateRoute.parameters))
        .then((response) => {
            notify({
                title: ctrans('Success!'),
                text: ctrans('Template deleted successfully'),
                type: 'success',
            })
        })
        .catch((error) => {
            if (error.response) {
                notify({
                    title: 'Error',
                    text: error.response.data.message || 'Failed to delete template',
                })
            } else {
                notify({
                    title: 'Error',
                    text: 'Failed to delete template',
                })
            }
            inProgress.value = false;
        })
        .finally(() => {
            inProgress.value = false;
            router.visit(route(props.indexRoute.name, props.indexRoute.parameters));
        })
}
</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <div v-if="can_edit">
                <span class="inline-flex items-center text-xs text-yellow-600">
                    <FontAwesomeIcon :icon="faExclamationTriangle" class="mr-0.5 flex-shrink-0" fixed-width />
                    {{ ctrans('Click the "SAVE" button in BeeFree Workspace before this template can be used') }}
                </span>
            </div>

        </template>
        <template #other>
            <ModalConfirmation v-if="can_edit" :title="ctrans('Are you sure you want to delete this template?')"
                :description="ctrans('This action cannot be undone. This will permanently delete this template')"
                isFullLoading>
                <template #default="{ isOpenModal, changeModel }">
                    <Button :disabled="inProgress" :icon="faTrashAlt" type="negative" @click="changeModel" />
                </template>
                <template #btn-yes>
                    <Button :label="ctrans('delete')" :loading="inProgress" :disabled="inProgress" @click="handleDelete"
                        type="negative" :icon="faTrashAlt" />
                </template>
            </ModalConfirmation>
        </template>
    </PageHeading>

    <div v-if="isRefreshingLayout" />
    <EmailWorkshop v-else-if="builder == 'beefree'" :updateRoute="updateRoute" :imagesUploadRoute="imagesUploadRoute" :videoThumbnailRoute="videoThumbnailRoute" :websiteTheme="websiteTheme" :imageCategories="imageCategories"
        :snapshot="snapshot" :mergeTags="mergeTags" :socialIcons="socialIcons" :organisationSlug="organisationSlug" @onSave="onSave"
        :shopSlug="shopSlug" @sendTest="openSendTest" @saveTemplate="onSaveTemplate" ref="_emailWorkshop" />

    <div v-else>
        <EmptyState :data="{
            title: 'Builder Not Set Up',
            description: 'you neeed to set up the builder'
        }" />
    </div>

    <Dialog v-model:visible="visibleEmailTestModal" modal :closable="false" :showHeader="false"
        :style="{ width: '25rem' }">
        <div class="pt-4">
            <div class="font-semibold mb-3">Email</div>
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
            <div v-if="saveTemplateError" class="mt-1 text-xs text-red-500">{{ saveTemplateError }}</div>
            <div class="flex justify-end mt-3 gap-3">
                <Button :type="'tertiary'" label="Cancel" @click="visibleSAveEmailTemplateModal = false" :disabled="isLoadingTemplate"></Button>
                <Button type="save" @click="saveTemplate" :loading="isLoadingTemplate" :disabled="isLoadingTemplate"></Button>
            </div>
        </div>
    </Dialog>


</template>
