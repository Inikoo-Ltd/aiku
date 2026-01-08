<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { useTabChange } from "@/Composables/tab-change";
import { capitalize } from "@/Composables/capitalize";
import { computed, ref } from "vue";
import type { Component } from "vue";
import EmailPreview from "@/Components/Showcases/Org/Mailshot/EmailPreview.vue";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import { PageHeadingTypes } from "@/types/PageHeading";
import { Tabs as TSTabs } from "@/types/Tabs";
import MailshotShowcase from "@/Components/Showcases/Org/Mailshot/MailshotShowcase.vue";
import { faEnvelope, faStop } from "@fas";
import { faDraftingCompass, faUsers, faPaperPlane, faBullhorn, faClock } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import TableDispatchedEmails from "@/Components/Tables/TableDispatchedEmails.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import axios from "axios"
import { notify } from '@kyvg/vue3-notification'
import { routeType } from "@/types/route";
import { Popover } from 'primevue';
import VueDatePicker from '@vuepic/vue-datepicker';
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue";
import ModalConfirmation from '@/Components/Utils/ModalConfirmation.vue'
import { trans } from "laravel-vue-i18n"

library.add(faEnvelope, faDraftingCompass, faStop, faUsers, faPaperPlane, faBullhorn, faClock);


const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    showcase?: string
    email_preview?: Object
    dispatched_emails?: {}
    sendMailshotRoute?: routeType
    scheduleMailshotRoute?: routeType
    deleteMailshotRoute?: routeType
    indexRoute?: routeType
    status?: string
}>();


const currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

// Computed property to check if buttons should be shown
const shouldShowButtons = computed(() => {
    return props.status && props.status.toLowerCase() === 'ready';
});

const shouldShowDeleteButton = computed(() => {
    return props.status && ['ready', 'in_process'].includes(props.status.toLowerCase());
});

// Schedule datetime picker state
const showSchedulePicker = ref(false);
const scheduleDateTime = ref(new Date());
const minDateTime = ref(new Date());
const schedulePicker = ref();

const handleSendNow = async () => {
    // TODO: implement send now, now for testing

    if (!props.sendMailshotRoute) {
        notify({
            type: 'error',
            title: 'Error',
            text: 'Mailshot route not configured',
        })
        return;
    }

    await axios.post(route(props.sendMailshotRoute.name, props.sendMailshotRoute.parameters))
        .then((response) => {
            if (response.data) {
                notify({
                    type: 'success',
                    title: 'Success',
                    text: 'Mailshot sent successfully',
                })
            } else {
                notify({
                    type: 'error',
                    title: 'Error',
                    text: 'Failed to send mailshot',
                })
            }
        })
        .catch((exception) => {
            console.log(exception);
            notify({
                type: 'error',
                title: 'Error',
                text: 'Failed to send mailshot',
            })
        })
};


// Function to format datetime for API
const formatDateTime = (date: Date) => {
    return date.toISOString().replace('T', ' ').replace('Z', '.000 +0000');
};

// Function to handle schedule with datetime picker
const handleSchedule = async (event: Event) => {
    if (!props.scheduleMailshotRoute) {
        notify({
            type: 'error',
            title: 'Error',
            text: 'Mailshot route not configured',
        })
        return;
    }

    // Show the datetime picker using the ref
    if (schedulePicker.value) {
        schedulePicker.value.show(event);
    }
    showSchedulePicker.value = true;
};

// Function to confirm schedule
const confirmSchedule = async () => {
    if (!props.scheduleMailshotRoute) return;

    const formattedDateTime = formatDateTime(scheduleDateTime.value);

    await axios.post(route(props.scheduleMailshotRoute.name, props.scheduleMailshotRoute.parameters), {
        scheduled_at: formattedDateTime
    })
        .then((response) => {
            if (response.data) {
                notify({
                    type: 'success',
                    title: 'Success',
                    text: `Mailshot scheduled for ${scheduleDateTime.value.toLocaleString()}`,
                })
                showSchedulePicker.value = false;
            } else {
                notify({
                    type: 'error',
                    title: 'Error',
                    text: 'Failed to schedule mailshot',
                })
            }
        })
        .catch((exception) => {
            console.log(exception);
            notify({
                type: 'error',
                title: 'Error',
                text: 'Failed to schedule mailshot',
            })
        })
};

// Function to get dynamic min time based on selected date
const getMinTime = () => {
    const now = new Date();
    const selectedDate = scheduleDateTime.value;

    // If selected date is today, set min time to current time
    if (selectedDate && selectedDate.toDateString() === now.toDateString()) {
        return {
            hours: now.getHours(),
            minutes: now.getMinutes(),
            seconds: now.getSeconds()
        };
    }

    // Otherwise, allow any time from start of day
    return { hours: 0, minutes: 0, seconds: 0 };
};

// Function to cancel schedule
const cancelSchedule = () => {
    if (schedulePicker.value) {
        schedulePicker.value.hide();
    }
    showSchedulePicker.value = false;
    scheduleDateTime.value = new Date();
};

const component = computed(() => {
    const components: Component = {
        showcase: MailshotShowcase,
        email_preview: EmailPreview,
        history: TableHistories,
        dispatched_emails: TableDispatchedEmails
    };
    return components[currentTab.value];
});

const handleDelete = async () => {
    if (!props.deleteMailshotRoute) {
        notify({
            type: 'error',
            title: 'Error',
            text: 'Delete mailshot route not configured',
        })
        return;
    }

    await axios.delete(route(props.deleteMailshotRoute.name, props.deleteMailshotRoute.parameters))
        .then((response) => {
            if (response.data) {
                notify({
                    type: 'success',
                    title: 'Success',
                    text: 'Mailshot deleted successfully',
                })
                // Redirect to newsletters index page

            } else {
                notify({
                    type: 'error',
                    title: 'Error',
                    text: 'Failed to delete mailshot',
                })
            }
        })
        .catch((exception) => {
            console.log(exception);
            notify({
                type: 'error',
                title: 'Error',
                text: 'Failed to delete mailshot',
            })
        })
        .finally(() => {
            if (!props.indexRoute) {
                notify({
                    type: 'error',
                    title: 'Error',
                    text: 'Mailshot index route not configured',
                })
                return;
            }
            router.visit(route(props.indexRoute.name, props.indexRoute.parameters));
        })
}

</script>


<template>

    <Head :title="capitalize(pageHead.title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <div class="flex" v-if="shouldShowButtons">
                <ModalConfirmation @onYes="handleSendNow" :title="trans('Are you sure you want to send this mailshot?')"
                    :description="trans('Please make sure your data or design is correct. This action will send an email to all customers')"
                    isFullLoading>
                    <template #default="{ isOpenModal, changeModel }">
                        <Button :label="trans('send now')" :disabled="false" class="!border-r-none !rounded-r-none"
                            icon="fal fa-paper-plane" type="positive" @click="changeModel" />
                    </template>
                </ModalConfirmation>
                <Button label="Schedule" class="!border-l-none !rounded-l-none" icon="fal fa-clock" type="positive"
                    @click="handleSchedule($event)" />
            </div>
        </template>
        <template #other>
            <ModalConfirmationDelete @onYes="handleDelete" v-if="shouldShowDeleteButton"
                :title="trans('Are you sure you want to delete this mailshot?')"
                :description="trans('This action cannot be undone. This will permanently delete this mailshot')"
                isFullLoading>
                <template #default="{ isOpenModal, changeModel }">
                    <Button :disabled="false" icon="fal fa-trash-alt" type="negative" @click="changeModel" />
                </template>
            </ModalConfirmationDelete>
        </template>

    </PageHeading>

    <!-- Schedule DateTime Picker Popover -->
    <Popover ref="schedulePicker" :visible="showSchedulePicker" @hide="cancelSchedule" appendTo="body">
        <div class="p-4 min-w-80 bg-white flex flex-col items-center">
            <h3 class="text-lg font-semibold mb-4 text-gray-900">Schedule Mailshot</h3>
            <div class="mb-4 flex justify-center">
                <VueDatePicker v-model="scheduleDateTime" :min-date="minDateTime" :min-time="getMinTime()"
                    :max-time="{ hours: 23, minutes: 59, seconds: 59 }" :text-input="true" :inline="true"
                    :enable-time-picker="true" :is-24="true" :minutes-increment="1" :seconds-increment="1"
                    :auto-apply="true" :open-on-focus="true" :time-picker-inline="true" class="w-full" placeholder=""
                    :teleport="true" />
            </div>
            <div class="flex gap-2 justify-end w-full">
                <Button label="Cancel" @click="cancelSchedule"
                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-md" />
                <Button label="Confirm Schedule" @click="confirmSchedule"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md" />
            </div>
        </div>
    </Popover>
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />
</template>
