<script setup lang="ts">
import { ref, computed, watch, inject } from 'vue'
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
import { faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow, faPaperPlane, faPlus } from '@fal'
import { faUserCog } from '@fas'
import { routeType } from '@/types/route'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import { data } from "autoprefixer"

library.add(faUserCog, faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow)

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
    storeTemplateRoute: routeType
    organisationSlug: string
}>()

const comment = ref('')
const isLoading = ref(false)
const visibleEmailTestModal = ref(false)
const visibleSAveEmailTemplateModal = ref(false)
const email = ref([])
const templateName = ref('')
const temporaryData = ref()
const active = ref(props.status)
const _popover = ref()
const _beefree = ref<any>(null)

type SaveIntent = 'save' | 'template' | null

const saveIntent = ref<SaveIntent>(null)

const lastBuilderResult = ref<{
    layout: string | null
    compiled_layout: string | null
}>({
    layout: null,
    compiled_layout: null,
})

const onBeefreeSave = (data: any) => {
    lastBuilderResult.value = {
        layout: data.jsonFile,
        compiled_layout: data.htmlFile,
    }

    if (saveIntent.value === 'save') {
        save(data.jsonFile)
    }

    if (saveIntent.value === 'template') {
        temporaryData.value = { ...lastBuilderResult.value }
        visibleSAveEmailTemplateModal.value = true
    }

    saveIntent.value = null
}

const openSendTest = (data) => {
    visibleEmailTestModal.value = true
    temporaryData.value = {
        layout: data?.jsonFile,
        compiled_layout: data?.htmlFile
    }
}

const onSaveTemplate = async (data: any) => {
    saveIntent.value = 'template'

    if (!_beefree.value?.beeInstance) {
        notify({
            title: 'Editor not ready',
            type: 'warning',
        })
        return
    }

    _beefree.value.beeInstance.save()
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

const saveTemplate = async (data: any) => {
    isLoading.value = true;

    axios
        .post(
            route(props.storeTemplateRoute.name, props.storeTemplateRoute.parameters),
            {
                name: templateName.value,
                layout: JSON.parse(temporaryData.value?.layout),
                compiled_layout: temporaryData.value?.compiled_layout,
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

const save = async (jsonFile) => {
    axios
        .patch(
            route(props.updateRoute.name, props.updateRoute.parameters),
            {
                layout: JSON.parse(jsonFile),
                /*  compiled_layout: htmlFile */
            },
        )
        .then((response) => {
            console.log("save successful:", response.data);
            // Handle success (equivalent to onFinish)
        })
        .catch((error) => {
            console.error("save failed:", error);
            notify({
                title: "Failed to save",
                type: "error",
            })
        })
        .finally(() => {
            console.log("save finished.");
        });
}
</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
        </template>
    </PageHeading>

    <!-- beefree -->
    <Beetree v-if="builder == 'beefree'" :updateRoute="updateRoute" :imagesUploadRoute="imagesUploadRoute"
        :snapshot="snapshot" :mergeTags="mergeTags" :organisationSlug="organisationSlug" @onSave="onBeefreeSave"
        @sendTest="openSendTest" @saveTemplate="onSaveTemplate" ref="_beefree" />

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
