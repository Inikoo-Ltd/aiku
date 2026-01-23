<script setup lang="ts">
import { ref, computed, watch, inject } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import Beetree from '@/Components/CMS/Website/Outboxes/Beefree.vue'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import Dialog from 'primevue/dialog';
import ModalConfirmation from '@/Components/Utils/ModalConfirmation.vue'
import PureInput from "@/Components/Pure/PureInput.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { trans } from "laravel-vue-i18n"
import 'v-calendar/style.css'
import Multiselect from "@vueform/multiselect"
import Tag from '@/Components/Tag.vue'
import { PageHeadingTypes } from "@/types/PageHeading";
import { library } from '@fortawesome/fontawesome-svg-core'
import { faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow, faPaperPlane, faPlus, faTrashAlt } from '@fal'
import { faUserCog } from '@fas'
import { routeType } from '@/types/route'
import EmptyState from '@/Components/Utils/EmptyState.vue'

library.add(faUserCog, faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow)

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    builder: string
    imagesUploadRoute: routeType
    updateRoute: routeType
    snapshot: routeType
    mergeTags: Array<any>
    sendTestRoute: routeType
    storeTemplateRoute: routeType
    deleteTemplateRoute: routeType
    organisationSlug: string
    indexRoute: routeType
}>()

const isLoading = ref(false)
const inProgress = ref(false)
const visibleEmailTestModal = ref(false)
const visibleSAveEmailTemplateModal = ref(false)
const email = ref([])
const templateName = ref('')
const temporaryData = ref()

const openSendTest = (data) => {
    visibleEmailTestModal.value = true
    temporaryData.value = {
        layout: data?.jsonFile,
        compiled_layout: data?.htmlFile
    }
}


const sendTestToServer = async () => {
    isLoading.value = true;
    try {
        const response = await axios.post(route(props.sendTestRoute.name, props.sendTestRoute.parameters),
            { ...temporaryData.value, emails: email.value }
        );
    } catch (error) {
        console.error("Error in sendTest:", error);
        visibleEmailTestModal.value = false
        temporaryData.value = null
        const errorMessage = error.response?.data?.message || error.message || "An unknown error occurred.";
        notify({
            title: "Something went wrong",
            text: errorMessage,
            type: "error",
        });
    } finally {
        isLoading.value = false;
    }
};

const onSaveTemplate = (data: any) => {
    visibleSAveEmailTemplateModal.value = true
    temporaryData.value = {
        layout: data?.jsonFile
    }
}

const saveTemplate = async (data: any) => {
    isLoading.value = true;
    axios
        .post(
            route(props.storeTemplateRoute.name, props.storeTemplateRoute.parameters),
            {
                name: templateName.value,
                layout: JSON.parse(temporaryData.value?.layout),
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
            isLoading.value = false;
        });
}

const onSave = async (data: any) => {
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
                title: trans('Success!'),
                text: trans('Template deleted successfully'),
                type: 'success',
            })
        })
        .catch((error) => {
            console.log(error);
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
        <template #other>
            <ModalConfirmation :title="trans('Are you sure you want to delete this template?')"
                :description="trans('This action cannot be undone. This will permanently delete this template')"
                isFullLoading>
                <template #default="{ isOpenModal, changeModel }">
                    <Button :disabled="inProgress" :icon="faTrashAlt" type="negative" @click="changeModel" />
                </template>
                <template #btn-yes>
                    <Button :label="trans('delete')" :loading="inProgress" :disabled="inProgress" @click="handleDelete"
                        type="negative" :icon="faTrashAlt" />
                </template>
            </ModalConfirmation>
        </template>
    </PageHeading>

    <!-- beefree -->
    <Beetree v-if="builder == 'beefree'" :updateRoute="updateRoute" :imagesUploadRoute="imagesUploadRoute"
        :snapshot="snapshot" :mergeTags="mergeTags" :organisationSlug="organisationSlug" @onSave="onSave"
        @sendTest="openSendTest" @saveTemplate="onSaveTemplate" ref="_beefree" />

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
            <Multiselect v-model="email" mode="tags" :close-on-select="false" :searchable="true" :create-option="true"
                :options="[]" :showOptions="false" :caret="false">
                <template #tag="{ option, handleTagRemove, disabled }">
                    <slot name="tag" :option="option" :handleTagRemove="handleTagRemove" :disabled="disabled">
                        <div class="px-0.5 py-[3px]">
                            <Tag :label="option.label" :closeButton="true" :stringToColor="true" size="sm"
                                @onClose="(event) => handleTagRemove(option, event)" />
                        </div>
                    </slot>
                </template>
            </Multiselect>
            <div class="flex justify-end mt-3 gap-3">
                <Button :type="'tertiary'" label="Cancel" @click="visibleEmailTestModal = false"></Button>
                <Button @click="sendTestToServer" :icon="faPaperPlane" label="Send"></Button>
            </div>
        </div>
    </Dialog>

    <Dialog v-model:visible="visibleSAveEmailTemplateModal" modal :closable="false" :showHeader="false"
        :style="{ width: '25rem' }">
        <div class="pt-4">
            <div class="font-semibold mb-3">Template Name</div>
            <PureInput v-model="templateName" placeholder="Template Name" />
            <div class="flex justify-end mt-3 gap-3">
                <Button :type="'tertiary'" label="Cancel" @click="visibleSAveEmailTemplateModal = false"></Button>
                <Button type="save" @click="saveTemplate"></Button>
            </div>
        </div>
    </Dialog>


</template>
