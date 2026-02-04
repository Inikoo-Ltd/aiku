<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { useTabChange } from "@/Composables/tab-change";
import { capitalize } from "@/Composables/capitalize";
import { computed, ref, watch } from "vue";
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
import TableMailshotRecipients from "@/Components/Tables/TableMailshotRecipients.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import axios from "axios"
import { notify } from '@kyvg/vue3-notification'
import { routeType } from "@/types/route";
import { Popover, ToggleSwitch, InputText, InputNumber } from 'primevue';
import VueDatePicker from '@vuepic/vue-datepicker';
import ModalConfirmation from '@/Components/Utils/ModalConfirmation.vue'
import { trans } from "laravel-vue-i18n"
import { useFormatTime } from "@/Composables/useFormatTime";

library.add(faEnvelope, faDraftingCompass, faStop, faUsers, faPaperPlane, faBullhorn, faClock);


const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    showcase?: string
    email_preview?: Object
    recipients?: {}
    dispatched_emails?: {}
    sendMailshotRoute?: routeType
    scheduleMailshotRoute?: routeType
    deleteMailshotRoute?: routeType
    indexRoute?: routeType
    cancelScheduleMailshotRoute?: routeType
    status?: string
    estimatedRecipients?: number
    mailshotType?: string
    setSecondWaveRoute?: routeType
}>();

const currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);
const TAB_HIDE_RULES: Record<string, string[]> = {
    in_process: ["recipients", "dispatched_emails"],
    ready: ["recipients", "dispatched_emails"],
}
const filteredTabs = computed(() => {
    const hiddenTabs = TAB_HIDE_RULES[props.status ?? ""] ?? []

    return Object.fromEntries(
        Object.entries(props.tabs.navigation).filter(
            ([key]) => !hiddenTabs.includes(key)
        )
    )
})

// Toggle switch state (second wave)
const checked = ref(false);
const subject = ref('');
const hour = ref<number | null>(null);
// Computed property to check if buttons should be shown
const shouldShowButtons = computed(() => {
    return props.status && props.status.toLowerCase() === 'ready';
});

const shouldShowDeleteButton = computed(() => {
    return props.status && ['ready', 'in_process'].includes(props.status.toLowerCase());
});

const shouldShowCancelScheduleButton = computed(() => {
    return props.status && props.status.toLowerCase() === 'scheduled';
});

// Schedule datetime picker state
const showSchedulePicker = ref(false);
const scheduleDateTime = ref(new Date());
const minDateTime = ref(new Date());
const schedulePicker = ref();

const inProgress = ref(false);
const scheduleInProgress = ref(false);

const handleSendNow = async () => {
    // Prevent multiple simultaneous requests
    if (inProgress.value) {
        return;
    }

    inProgress.value = true;

    if (!props.sendMailshotRoute) {
        notify({
            type: 'error',
            title: 'Error',
            text: 'Mailshot route not configured',
        })
        inProgress.value = false;
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
        .finally(() => {
            inProgress.value = false;
            router.reload();
        })
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

    scheduleInProgress.value = true;
    const formattedDateTime = useFormatTime(scheduleDateTime.value, { formatTime: 'yyyy-MM-dd HH:mm:ss' })

    showSchedulePicker.value = false;
    schedulePicker.value?.hide();

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
                schedulePicker.value?.hide();
            } else {
                schedulePicker.value?.hide();
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
        .finally(() => {
            showSchedulePicker.value = false;
            schedulePicker.value?.hide();
            scheduleInProgress.value = false;
            router.reload();
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

const formatNumber = (num: number | null | undefined) => {
    return new Intl.NumberFormat('en-GB').format(num ?? 0)
}

const component = computed(() => {
    const components: Component = {
        showcase: MailshotShowcase,
        email_preview: EmailPreview,
        history: TableHistories,
        dispatched_emails: TableDispatchedEmails,
        recipients: TableMailshotRecipients,
    };
    return components[currentTab.value];
});

const handleDelete = async () => {

    if (inProgress.value) {
        return;
    }

    inProgress.value = true;

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
            inProgress.value = false;
        })
        .catch((exception) => {
            console.log(exception);
            notify({
                type: 'error',
                title: 'Error',
                text: 'Failed to delete mailshot',
            })
            inProgress.value = false;
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
            inProgress.value = false
            router.visit(route(props.indexRoute.name, props.indexRoute.parameters));
        })
}

const handleCancelSchedule = async () => {
    if (!props.cancelScheduleMailshotRoute) {
        notify({
            type: 'error',
            title: 'Error',
            text: 'Cancel schedule mailshot route not configured',
        })
        return;
    }

    if (inProgress.value) {
        return;
    }

    inProgress.value = true;

    await axios.post(route(props.cancelScheduleMailshotRoute.name, props.cancelScheduleMailshotRoute.parameters))
        .then((response) => {
            if (response.data) {
                notify({
                    type: 'success',
                    title: 'Success',
                    text: 'Schedule cancelled successfully',
                })
            } else {
                notify({
                    type: 'error',
                    title: 'Error',
                    text: 'Failed to cancel schedule',
                })
            }
            inProgress.value = false;
        })
        .catch((exception) => {
            console.log(exception);
            notify({
                type: 'error',
                title: 'Error',
                text: 'Failed to cancel schedule',
            })
            inProgress.value = false;
        })
        .finally(() => {
            inProgress.value = false;
            router.reload();
        })
}

const isSavingToggle = ref(false)
const handleToggleSecondWave = async (value: boolean) => {
    if (!props.setSecondWaveRoute) {
        return
    }
    const previous = !value
    isSavingToggle.value = true

    await axios.post(route(props.setSecondWaveRoute?.name, props.setSecondWaveRoute?.parameters), {
        status: value
    })
        .then((response) => {
            if (response.data) {
                notify({
                    type: 'success',
                    title: 'Success',
                    text: 'Second wave status updated successfully',
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
        })
}

const handleSaveSecond = () => {
    if (!checked.value) return

    if (!subject.value.trim()) {
        alert("Subject required")
        return
    }

    console.log("SAVE DATA", {
        subject: subject.value,
        hour: hour.value
    })

    // contoh axios
    // axios.post('/api/second-wave', { subject: subject.value, hour: hour.value })
}
watch(
    filteredTabs,
    (tabs) => {
        if (!tabs[currentTab.value]) {
            currentTab.value = Object.keys(tabs)[0]
        }
    },
    { immediate: true }
)
console.log("props mailshot", props)
</script>

<template>

    <Head :title="capitalize(pageHead.title)" />

    <PageHeading :data="pageHead">
        <template #afterTitle v-if="
            props.mailshotType === 'marketing' &&
            ['in_process', 'ready', 'scheduled'].includes(props.status ?? '')
        ">
            <span>| Estimated Recipients : {{ formatNumber(props.estimatedRecipients) ?? 0 }}</span>
        </template>
        <template #otherBefore>
            <div class="flex" v-if="shouldShowButtons">
                <ModalConfirmation :title="trans('Are you sure you want to send this mailshot?')"
                    :description="trans('Please make sure your data or design is correct. This action will send an email to all customers')"
                    isFullLoading>
                    <template #default="{ isOpenModal, changeModel }">
                        <Button :label="trans('send now')" :disabled="inProgress" class="!border-r-none !rounded-r-none"
                            icon="fal fa-paper-plane" type="secondary" @click="changeModel" />
                    </template>
                    <template #btn-yes>
                        <Button :label="trans('send now')" :loading="inProgress" :disabled="inProgress"
                            @click="handleSendNow" type="secondary" icon="fal fa-paper-plane" />
                    </template>
                </ModalConfirmation>
                <Button :label="trans('Scheduled')" class="!border-l-none !rounded-l-none" icon="fal fa-clock"
                    type="secondary" @click="handleSchedule($event)" :loading="scheduleInProgress" />
            </div>
        </template>
        <template #other>
            <ModalConfirmation v-if="shouldShowDeleteButton"
                :title="trans('Are you sure you want to delete this mailshot?')"
                :description="trans('This action cannot be undone. This will permanently delete this mailshot')"
                isFullLoading>
                <template #default="{ isOpenModal, changeModel }">
                    <Button :disabled="inProgress" icon="fal fa-trash-alt" type="negative" @click="changeModel" />
                </template>
                <template #btn-yes>
                    <Button :label="trans('delete')" :loading="inProgress" :disabled="inProgress" @click="handleDelete"
                        type="negative" icon="fal fa-trash-alt" />
                </template>
            </ModalConfirmation>

            <ModalConfirmation @onYes="handleCancelSchedule" v-if="shouldShowCancelScheduleButton"
                :title="trans('Are you sure you want to cancel this schedule?')"
                :description="trans('This action will cancel the scheduled mailshot')" isFullLoading>
                <template #default="{ isOpenModal, changeModel }">
                    <Button :label="trans('Cancel Schedule')" :disabled="inProgress"
                        class="!border-r-none !rounded-r-none" icon="fal fa-clock" type="negative" @click="changeModel"
                        :tooltip="trans('This can still be canceled before it starts sending')" />
                </template>
                <template #btn-yes>
                    <Button :label="trans('Cancel Schedule')" :loading="inProgress" :disabled="inProgress"
                        @click="handleCancelSchedule" type="negative" icon="fal fa-clock" />
                </template>
            </ModalConfirmation>

        </template>

    </PageHeading>

    <!-- Schedule DateTime Picker Popover -->
    <Popover ref="schedulePicker" :visible="showSchedulePicker" @hide="cancelSchedule" appendTo="body">
        <div class="p-2 min-w-80 bg-white flex flex-col items-center">
            <h3 class="text-lg font-semibold mb-4 text-gray-900"> {{ trans('Timezone') }}: <span
                    class="text-red-600">(Europe/London)</span> </h3>
            <div class="mb-4 flex justify-center">
                <VueDatePicker v-model="scheduleDateTime" :min-date="minDateTime" :min-time="getMinTime()"
                    :text-input="true" :inline="true" :enable-time-picker="true" :is-24="true" :minutes-increment="1"
                    :seconds-increment="1" :auto-apply="true" :open-on-focus="true" :time-picker-inline="true"
                    class="w-full" placeholder="" :teleport="true" />
            </div>
            <div class="flex gap-2 justify-end w-full">
                <Button :label="trans('Cancel')" @click="cancelSchedule"
                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-md" type="secondary" />
                <Button :label="trans('Confirm Schedule')" @click="confirmSchedule" class="px-4 py-2 rounded-md"
                    type="negative" />
            </div>
        </div>
    </Popover>
    <Tabs :current="currentTab" :navigation="filteredTabs" @update:tab="handleTabUpdate" />
    <div class="mx-4 my-4 space-y-3" v-if="['in_process', 'ready'].includes(props.status ?? '')">

        <div class="flex items-center gap-2">
            <small class="text-gray-500 text-sm">2nd Wave</small>
            <ToggleSwitch v-model="checked" @update:modelValue="handleToggleSecondWave" :disabled="isSavingToggle" />
        </div>

        <template v-if="checked">
            <h2 class="text-lg font-semibold text-gray-700">
                Second Wave
            </h2>

            <form @submit.prevent="handleSaveSecond" class="space-y-3 border rounded-lg p-4 bg-gray-50">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Subject</label>
                    <InputText v-model="subject" placeholder="Enter value" class="w-full" required />
                </div>

                <div>
                    <label class="block text-sm text-gray-600 mb-2">Delay (Hour)</label>
                    <InputNumber v-model="hour" :min="1" placeholder="Default 48" class="w-full" />
                    <small class="text-gray-400">Default: 48 hours</small>
                </div>

                <Button label="Save" type="submit" icon="save" />
            </form>
        </template>
    </div>
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />
</template>
