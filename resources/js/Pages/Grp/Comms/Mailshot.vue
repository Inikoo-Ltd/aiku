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
import { faDraftingCompass, faUsers, faPaperPlane, faBullhorn, faClock, faSpinner, faSave } from "@fal";
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
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";

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
    showLinkedMailShotRoute?: routeType
    status?: string
    estimatedRecipients?: number
    mailshotType?: string
    setSecondWaveRoute?: routeType
    updateSecondWaveRoute?: routeType
    isSecondWaveActive: boolean
    secondwaveSubject?: string
    secondwaveDelayHours?: number
    isHasParentMailshot: boolean
    numberSecondWaveRecipients?: number
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
const checked = ref(props.isSecondWaveActive ?? false)
const isEditingSecond = ref(false)

const subject = ref(props.secondwaveSubject ?? "")
const hour = ref(props.secondwaveDelayHours ?? 48)

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
    const previous = false
    isSavingToggle.value = true

    await axios.post(route(props.setSecondWaveRoute?.name, props.setSecondWaveRoute?.parameters), {
        status: value
    })
        .then((response) => {
            if (response.data) {
                isSavingToggle.value = false
            } else {
                checked.value = previous
                isSavingToggle.value = false
                notify({
                    type: 'error',
                    title: 'Error',
                    text: 'Failed to update second wave status',
                })
            }
        })
        .catch((exception) => {
            console.log(exception);
            checked.value = previous
            isSavingToggle.value = false
            notify({
                type: 'error',
                title: 'Error',
                text: 'Failed to update second wave status',
            })
        })
        .finally(() => {
            isSavingToggle.value = false
            router.reload()
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

const loading = ref(false)
const handleSaveSecond = async () => {
    if (!checked.value) return

    if (!subject.value.trim() || hour.value === null) {
        notify({
            type: 'error',
            title: 'Validation',
            text: 'Subject or delay hours are required',
        })
        return
    }

    if (!props.updateSecondWaveRoute) {
        return
    }

    await axios.patch(route(props.updateSecondWaveRoute?.name, props.updateSecondWaveRoute?.parameters), {
        subject: subject.value,
        send_delay_hours: hour.value
    })
        .then((response) => {
            if (response.data) {
                isEditingSecond.value = false
                notify({
                    type: 'success',
                    title: 'Success',
                    text: 'Second wave data updated successfully',
                })

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
            router.reload()
        })
}

const loadingFetch = ref(false)

const handleFetchActionWave = () => {
    if (!props.showLinkedMailShotRoute) return

    loadingFetch.value = true

    router.get(
        route(props.showLinkedMailShotRoute.name, props.showLinkedMailShotRoute.parameters),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                loadingFetch.value = true
            },
            onFinish: () => {
                loadingFetch.value = false
            },
            onSuccess: () => {
                notify({
                    type: 'success',
                    title: 'Success',
                    text: 'Wave data refreshed',
                })
            },
            onError: () => {
                notify({
                    type: 'error',
                    title: 'Error',
                    text: 'Failed to fetch wave data',
                })
            }
        }
    )
}

const waveLabel = computed(() =>
    props.isHasParentMailshot ? 'First Wave' : '2nd Wave'
)
const isWaveContext = computed(() =>
    props.isHasParentMailshot || props.isSecondWaveActive
)

const showWaveSettings = computed(() =>
    ['in_process', 'ready'].includes(props.status ?? '')
)

// for the input subject secondwave validation
const shouldShowWaveInfo = computed(() =>
    showWaveSettings.value || isWaveContext.value
)

// for the links
const canFetchWaveAction = computed(() =>
    !showWaveSettings.value && isWaveContext.value
)

watch(() => props.isSecondWaveActive, v => checked.value = v)
watch(
    filteredTabs,
    (tabs) => {
        if (!tabs[currentTab.value]) {
            currentTab.value = Object.keys(tabs)[0]
        }
    },
    { immediate: true }
)
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
    <div class="mx-4 my-4 space-y-3" v-if="shouldShowWaveInfo && props.status">
        <div class="inline-flex items-center gap-3 px-3 py-1.5 rounded-md
         bg-gray-50 border border-gray-200">
            <template v-if="shouldShowWaveInfo">
                <span class="text-sm font-medium whitespace-nowrap transition" :class="canFetchWaveAction
                    ? 'text-indigo-600 cursor-pointer hover:underline'
                    : 'text-gray-700'" @click="canFetchWaveAction && handleFetchActionWave()">
                    {{ waveLabel }}
                </span>
                <span class="text-sm font-medium text-gray-700 whitespace-nowrap"
                    v-if="!props.isHasParentMailshot && !showWaveSettings && props.status === 'sent'">
                    Recipients: {{ numberSecondWaveRecipients }}
                </span>
            </template>
            <template v-if="showWaveSettings">
                <ToggleSwitch v-model="checked" @update:modelValue="handleToggleSecondWave"
                    :disabled="isSavingToggle" />

                <Button v-if="checked" type="edit" label="Edit" class="!px-2 !py-1 text-xs h-8"
                    @click="isEditingSecond = !isEditingSecond" />

                <span class="h-4 w-px bg-gray-300"></span>
                <template v-if="checked">
                    <label class="block text-sm text-gray-600 mb-1 font-medium">Subject</label>
                    <InputText v-model="subject" placeholder="Subject" :disabled="!checked || !isEditingSecond"
                        class="!h-8 !py-1 !text-sm w-44" />

                    <div class="flex items-center gap-1 shrink-0">
                        <InputNumber v-model="hour" :min="1" :disabled="!checked || !isEditingSecond"
                            inputClass="!h-8 !py-1 !text-sm text-center w-20" />
                        <span class="text-sm font-medium text-gray-700 whitespace-nowrap">hours</span>
                    </div>

                    <Button v-if="isEditingSecond" type="save" @click="handleSaveSecond" :loading="loading" />
                </template>
            </template>
        </div>

    </div>
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />
</template>
