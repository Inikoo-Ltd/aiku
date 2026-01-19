<script setup lang="ts">
import { ref, computed, watch } from 'vue'
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
import { faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow, faPaperPlane } from '@fal'
import { faUserCog } from '@fas'
import Tabs from "@/Components/Navigation/Tabs.vue";
import Modal from '@/Components/Utils/Modal.vue'
import { routeType } from '@/types/route'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import { data } from "autoprefixer"
import { useTabChange } from "@/Composables/tab-change";
import TableEmailTemplate from "@/Components/Tables/TableEmailTemplate.vue";

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
const openTemplates = ref(false)
const _beefree = ref()
const _unlayer = ref()
const visibleEmailTestModal = ref(false)
const visibleSAveEmailTemplateModal = ref(false)
const email = ref([])
const templateName = ref('')
const temporaryData = ref()
const active = ref(props.status)
const _popover = ref()
const date = ref(new Date())
const options = ref([
    { name: 'Active', value: "active" },
    { name: 'Suspended', value: "suspended" },
]);

const onSendPublish = async (data) => {
    try {
        const response = await axios.post(route(props.publishRoute.name, props.publishRoute.parameters), {
            comment: comment.value,
            layout: JSON.parse(data?.jsonFile),
            compiled_layout: data?.htmlFile
        });

        if (response && response.status === 200) {
            notify({
                title: "Success",
                text: "Save and publish email successfully",
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


const saveTemplate = async () => {
    isLoading.value = true;

    axios
        .post(
            route(props.storeTemplateRoute.name, props.storeTemplateRoute.parameters),
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
            isLoading.value = false;
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
            console.log("autosave successful:", response.data);
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
            console.log("autosave finished.");
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

const dummyTabs = {
    current: "templates",
    navigation: {
        templates: {
            title: "Templates",
            icon: "fal fa-layer-group",
        },
        previous_mailshots: {
            title: "Previous Mailshots",
            icon: "fal fa-history",
        },
        store_mailshots: {
            title: "Other Store Mailshots",
            icon: "fal fa-store",
        },
    },
}

type TabKey =
    | "templates"
    | "previous_mailshots"
    | "store_mailshots"

const isModalConfirmationOrder = ref(false)

const tabs = computed(() => props.tabs ?? dummyTabs)

const currentTab = ref<TabKey>(
    (tabs.value.current as TabKey) ?? "templates"
)

const handleTabUpdate = (tabSlug: string) =>
    useTabChange(tabSlug, currentTab)

const dummyTemplates = [
    {
        "id": 6,
        "recipient_type": null,
        "state": { "tooltip": "sent", "icon": "fal fa-paper-plane", "class": "text-green-600" },
        "email_address": "matei_bogdan65@yahoo.com",
        "sent_at": "2026-01-15 09:11:46+08",
        "customer_name": "Bogdan Matei"
    }
]

const dummyPreviousMailshots = [
    {
        id: 10,
        subject: "January Newsletter",
        sent_at: "2026-01-15 09:11",
        total_recipients: 1240,
        status: "sent",
    },
    {
        id: 11,
        subject: "New Product Launch",
        sent_at: "2026-01-05 10:00",
        total_recipients: 980,
        status: "sent",
    },
]

const dummyStoreMailshots = [
    {
        id: 20,
        store_name: "Ancient Wisdom Ltd",
        subject: "Holiday Sale",
        created_at: "2026-01-02",
    },
    {
        id: 21,
        store_name: "Global Market",
        subject: "New Collection",
        created_at: "2025-12-28",
    },
]
const tabData = computed<Record<TabKey, any[]>>(() => ({
    templates: props.templates ?? dummyTemplates,
    previous_mailshots: props.previous_mailshots ?? dummyPreviousMailshots,
    store_mailshots: props.store_mailshots ?? dummyStoreMailshots,
}))

const component = computed<Component>(() => TableEmailTemplate)

const activeData = computed(() => {
    const data = tabData.value[currentTab.value]
    return data
})

const activeSnapshot = ref(props.snapshot)

console.log("outbox", props)
</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <Button @click="() => isModalConfirmationOrder = true" :label="trans('Choose Template')"
                class="flex flex-wrap border border-gray-300 rounded-md overflow-hidden h-fit" />

        </template>
    </PageHeading>

    <Modal :isOpen="isModalConfirmationOrder" @onClose="isModalConfirmationOrder = false" width="w-full max-w-4xl">
        <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
        <component :is="component" :data="activeData" :tab="currentTab" />
    </Modal>

    <!-- beefree -->
    <Beetree v-if="builder == 'beefree'" :updateRoute="updateRoute" :imagesUploadRoute="imagesUploadRoute"
        :snapshot="activeSnapshot" :mergeTags="mergeTags" :organisationSlug="organisationSlug" @onSave="onSendPublish"
        @sendTest="openSendTest" @auto-save="autoSave" @saveTemplate="onSaveTemplate" ref="_beefree" />

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
