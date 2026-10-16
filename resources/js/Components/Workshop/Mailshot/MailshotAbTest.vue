<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import { ToggleSwitch, InputNumber, RadioButton, Select } from 'primevue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faInfoCircle, faCheck, faPaperPlane, faFlask } from '@fal'
import { ctrans } from '@/Composables/useTrans'
import Button from '@/Components/Elements/Buttons/Button.vue'
import ModalConfirmation from '@/Components/Utils/ModalConfirmation.vue'
import MailshotSubjectEdit from '@/Components/Workshop/Mailshot/MailshotSubjectEdit.vue'
import { routeType } from '@/types/route'

interface AbTestVersion {
    label: 'A' | 'B'
    id: number
    subject: string
    sender_name: string
    state: string
    state_label: string
    route: routeType
    workshop_route: routeType
    copy: { subject: string, name: string | null, preview_text: string | null, sender_name: string | null }
    update_route: routeType
    suggest_copy_route: routeType
    stats: Record<string, number>
}

export interface AbTest {
    enabled: boolean
    is_variant: boolean
    can_edit_settings: boolean
    estimated_recipients: number | null
    winning_metric: string
    winning_metric_options: { value: string, label: string }[]
    is_blocked_by_second_wave: boolean
    size_percentage: number
    winner_mailshot_id: number | null
    winner_sent_at: string | null
    auto_send_winner: boolean
    can_send_winner: boolean
    variant_delay_seconds: number
    variant_sends_at: string | null
    tracking_hours: number
    tracking_ends_at: string | null
    results_tracked_until: string | null
    suggested_winner_id: number | null
    versions: AbTestVersion[]
    update_route: routeType
    send_winner_route: routeType
}

const props = defineProps<{
    abTest: AbTest
    canEdit?: boolean
}>()

function splitDuration(totalSeconds: number) {
    return {
        hours: Math.floor(totalSeconds / 3600),
        minutes: Math.floor(totalSeconds % 3600 / 60),
        seconds: totalSeconds % 60,
    }
}

const metrics: { key: string, label: string, isRate: boolean, isHigherBetter: boolean }[] = [
    { key: 'sent', label: ctrans('Sent'), isRate: false, isHigherBetter: true },
    { key: 'delivered', label: ctrans('Delivered'), isRate: false, isHigherBetter: true },
    { key: 'opened', label: ctrans('Total opened'), isRate: false, isHigherBetter: true },
    { key: 'clicked', label: ctrans('Total clicked'), isRate: false, isHigherBetter: true },
    { key: 'placed_orders', label: ctrans('Placed orders'), isRate: false, isHigherBetter: true },
    { key: 'open_rate', label: ctrans('Open rate'), isRate: true, isHigherBetter: true },
    { key: 'click_rate', label: ctrans('Click rate'), isRate: true, isHigherBetter: true },
    { key: 'placed_order_rate', label: ctrans('Placed order rate'), isRate: true, isHigherBetter: true },
    { key: 'spam_rate', label: ctrans('Spam rate'), isRate: true, isHigherBetter: false },
    { key: 'unsubscribe_rate', label: ctrans('Unsubscribe rate'), isRate: true, isHigherBetter: false },
    { key: 'bounce_rate', label: ctrans('Bounce rate'), isRate: true, isHigherBetter: false },
]

const sizePercentage = ref(props.abTest.size_percentage)
const variantDelay = ref(splitDuration(props.abTest.variant_delay_seconds))
const trackingDays = ref(Math.floor(props.abTest.tracking_hours / 24))
const trackingHoursOfDay = ref(props.abTest.tracking_hours % 24)
const winningMetric = ref(props.abTest.winning_metric)
const autoSendWinner = ref(props.abTest.auto_send_winner)
watch(() => props.abTest, abTest => {
    sizePercentage.value = abTest.size_percentage
    variantDelay.value = splitDuration(abTest.variant_delay_seconds)
    trackingDays.value = Math.floor(abTest.tracking_hours / 24)
    trackingHoursOfDay.value = abTest.tracking_hours % 24
    winningMetric.value = abTest.winning_metric
    autoSendWinner.value = abTest.auto_send_winner
})

const variantDelaySeconds = computed(() =>
    (variantDelay.value.hours ?? 0) * 3600 + (variantDelay.value.minutes ?? 0) * 60 + (variantDelay.value.seconds ?? 0)
)

const trackingHours = computed(() => trackingDays.value * 24 + trackingHoursOfDay.value)

const hourOptions = Array.from({ length: 24 }, (_, hours) => ({ value: hours, label: ctrans(':count hours', { count: hours }) }))
const dayOptions = Array.from({ length: 31 }, (_, days) => ({ value: days, label: ctrans(':count days', { count: days }) }))

const versionPercentage = computed(() => Math.round((sizePercentage.value ?? 0) / 2 * 10) / 10)
const winnerPercentage = computed(() => 100 - (sizePercentage.value ?? 0))
const estimatedWinnerRecipients = computed(() =>
    props.abTest.estimated_recipients === null
        ? null
        : Math.round(props.abTest.estimated_recipients * winnerPercentage.value / 100)
)

const isSettingsChanged = computed(() =>
    sizePercentage.value !== props.abTest.size_percentage
    || variantDelaySeconds.value !== props.abTest.variant_delay_seconds
    || trackingHours.value !== props.abTest.tracking_hours
    || winningMetric.value !== props.abTest.winning_metric
    || autoSendWinner.value !== props.abTest.auto_send_winner
)

const saveAllSettings = () => saveSettings({
    size_percentage: sizePercentage.value,
    variant_delay_seconds: variantDelaySeconds.value,
    tracking_hours: trackingHours.value,
    winning_metric: winningMetric.value,
    auto_send_winner: autoSendWinner.value,
})

const isTrackingResults = computed(() =>
    !!props.abTest.tracking_ends_at && new Date(props.abTest.tracking_ends_at) > new Date()
)

const formatDate = (date: string) => new Date(date).toLocaleString()

const isSaving = ref(false)

const hasResults = computed(() => props.abTest.versions.every(version => version.stats.delivered > 0))

const isLeading = (metric: typeof metrics[number], version: AbTestVersion): boolean => {
    if (!metric.isRate || !hasResults.value) {
        return false
    }
    const other = props.abTest.versions.find(candidate => candidate.id !== version.id)
    if (!other || other.stats[metric.key] === version.stats[metric.key]) {
        return false
    }

    return metric.isHigherBetter
        ? version.stats[metric.key] > other.stats[metric.key]
        : version.stats[metric.key] < other.stats[metric.key]
}

const canEditVersionContent = (version: AbTestVersion) =>
    props.canEdit && ['in_process', 'ready'].includes(version.state)

const errorMessage = (exception: any, fallback: string): string => {
    const errors = exception?.response?.data?.errors ?? {}
    return (Object.values(errors)[0] as string[] | undefined)?.[0] ?? fallback
}

const saveSettings = async (payload: { enabled?: boolean, size_percentage?: number, variant_delay_seconds?: number, tracking_hours?: number, winning_metric?: string, auto_send_winner?: boolean }) => {
    isSaving.value = true
    try {
        await axios.post(route(props.abTest.update_route.name, props.abTest.update_route.parameters), payload)
    } catch (exception) {
        notify({
            type: 'error',
            title: ctrans('Error'),
            text: errorMessage(exception, ctrans('Failed to update the A/B test')),
        })
    } finally {
        isSaving.value = false
        router.reload()
    }
}

const sendWinner = async (version: AbTestVersion) => {
    isSaving.value = true
    try {
        await axios.post(route(props.abTest.send_winner_route.name, props.abTest.send_winner_route.parameters), {
            winner_mailshot_id: version.id,
        })
        notify({
            type: 'success',
            title: ctrans('Success'),
            text: ctrans('Version :label is being sent to the rest of the list', { label: version.label }),
        })
    } catch (exception) {
        notify({
            type: 'error',
            title: ctrans('Error'),
            text: errorMessage(exception, ctrans('Failed to send the winner')),
        })
    } finally {
        isSaving.value = false
        router.reload()
    }
}

const winner = computed(() => props.abTest.versions.find(version => version.id === props.abTest.winner_mailshot_id))
</script>

<template>
    <section class="mx-4 my-4 space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between gap-x-4">
            <div class="flex items-start gap-x-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[--app-accent-soft] text-lg text-[--app-accent]">
                    <FontAwesomeIcon :icon="faFlask" fixed-width aria-hidden="true" />
                </span>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">{{ ctrans('A/B test') }}</h3>
                    <p class="text-sm text-gray-500">
                        <template v-if="abTest.is_variant">{{ ctrans('This is version B. Sending is controlled from version A.') }}</template>
                        <template v-else-if="abTest.can_edit_settings && abTest.is_blocked_by_second_wave">{{ ctrans('Turn off the 2nd wave to run an A/B test.') }}</template>
                        <template v-else>{{ ctrans('Send two versions to part of the list, then the winner to the rest.') }}</template>
                    </p>
                </div>
            </div>

            <ToggleSwitch v-if="abTest.can_edit_settings" :modelValue="abTest.enabled" :aria-label="ctrans('A/B test')"
                :disabled="isSaving || abTest.is_blocked_by_second_wave"
                @update:modelValue="(value: boolean) => saveSettings({ enabled: value })" />
        </div>

        <div v-if="abTest.enabled && abTest.can_edit_settings" class="space-y-5 border-t border-gray-100 pt-4">
            <fieldset>
                <legend class="text-sm font-semibold text-gray-800">{{ ctrans('Winning metric') }}</legend>
                <p class="text-xs text-gray-500">{{ ctrans('Select the metric that will determine the test winner.') }}</p>
                <div class="mt-2 space-y-1.5">
                    <label v-for="option in abTest.winning_metric_options" :key="option.value"
                        class="flex w-fit cursor-pointer items-center gap-2 text-sm text-gray-700">
                        <RadioButton v-model="winningMetric" :value="option.value" name="winning_metric"
                            :disabled="isSaving" />
                        {{ option.label }}
                    </label>
                </div>
            </fieldset>

            <div class="rounded-md border border-gray-200">
                <div class="border-b border-gray-200 px-4 py-3">
                    <div class="text-sm font-semibold text-gray-800">{{ ctrans('Size distribution') }}</div>
                    <p class="text-xs text-gray-500">
                        {{ ctrans('If you choose 100%, everyone on the list randomly gets version A or B at the send time, and there is no winner to send afterwards.') }}
                    </p>
                </div>
                <div class="grid grid-cols-1 items-center gap-x-6 gap-y-4 px-4 py-3 md:grid-cols-[1fr_auto_10rem]">
                    <div>
                        <div class="text-sm text-gray-800">{{ ctrans('Test size') }}</div>
                        <p class="text-xs text-gray-500">{{ ctrans('Percentage of recipients that will randomly receive one of the versions.') }}</p>
                    </div>
                    <InputNumber v-model="sizePercentage" :min="10" :max="100" suffix=" %" :disabled="isSaving"
                        inputClass="!h-8 !py-1 !text-sm w-28" />
                    <div class="flex gap-2 text-xs">
                        <span v-for="label in ['A', 'B']" :key="label"
                            class="rounded-full bg-[--app-accent-soft] px-2 py-0.5 font-medium text-[--app-accent-strong]">
                            {{ label }} {{ versionPercentage }}%
                        </span>
                    </div>

                    <div>
                        <div class="text-sm text-gray-800">{{ ctrans('Winning version') }}</div>
                        <p class="text-xs text-gray-500">{{ ctrans('Percentage of recipients that will receive the highest performing version.') }}</p>
                    </div>
                    <div class="text-sm text-gray-800">
                        {{ winnerPercentage }}%
                        <div v-if="estimatedWinnerRecipients !== null && winnerPercentage > 0" class="text-xs text-gray-500">
                            {{ ctrans('Est. :count profiles', { count: estimatedWinnerRecipients.toLocaleString() }) }}
                        </div>
                    </div>
                    <div></div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <div class="text-sm font-semibold text-gray-800">{{ ctrans('Send version B') }}</div>
                    <p class="text-xs text-gray-500">{{ ctrans('Time after version A starts sending. 0 sends both at the same time.') }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <InputNumber v-model="variantDelay.hours" :min="0" :max="168" :suffix="` ${ctrans('h')}`"
                            :disabled="isSaving" :aria-label="ctrans('Hours')" inputClass="!h-8 !py-1 !text-sm w-20" />
                        <InputNumber v-model="variantDelay.minutes" :min="0" :max="59" :suffix="` ${ctrans('min')}`"
                            :disabled="isSaving" :aria-label="ctrans('Minutes')" inputClass="!h-8 !py-1 !text-sm w-20" />
                        <InputNumber v-model="variantDelay.seconds" :min="0" :max="59" :suffix="` ${ctrans('s')}`"
                            :disabled="isSaving" :aria-label="ctrans('Seconds')" inputClass="!h-8 !py-1 !text-sm w-20" />
                        <span class="text-sm text-gray-600">{{ ctrans('after A') }}</span>
                    </div>
                    <p v-if="variantDelaySeconds > 0" class="mt-1 text-xs text-gray-500">
                        {{ ctrans('Sent within a minute of that time, once A has finished sending.') }}
                    </p>
                </div>
                <div>
                    <div class="flex items-center gap-1.5 text-sm font-semibold text-gray-800">
                        {{ ctrans('Test duration') }}
                        <FontAwesomeIcon :icon="faInfoCircle" class="text-gray-400" fixed-width
                            v-tooltip="ctrans('Opens, clicks and orders are counted from each version\'s send until this long after the last version finished sending. Then the winner can be sent.')" />
                    </div>
                    <p class="text-xs text-gray-500">{{ ctrans('How long results are tracked before you pick the winner.') }}</p>
                    <div class="mt-2 flex gap-2">
                        <Select v-model="trackingHoursOfDay" :options="hourOptions" optionLabel="label" optionValue="value"
                            :disabled="isSaving" class="w-36" size="small" />
                        <Select v-model="trackingDays" :options="dayOptions" optionLabel="label" optionValue="value"
                            :disabled="isSaving" class="w-36" size="small" />
                    </div>
                    <p v-if="trackingHours < 1" class="mt-1 text-xs text-red-600">{{ ctrans('Track results for at least 1 hour.') }}</p>
                    <p v-if="trackingHours > 720" class="mt-1 text-xs text-red-600">{{ ctrans('Track results for at most 30 days.') }}</p>
                </div>
            </div>

            <label v-if="winnerPercentage > 0" class="flex w-fit cursor-pointer items-center gap-3">
                <ToggleSwitch v-model="autoSendWinner" :disabled="isSaving" />
                <span>
                    <span class="block text-sm font-semibold text-gray-800">{{ ctrans('Send the winner automatically') }}</span>
                    <span class="block text-xs text-gray-500">{{ ctrans('When the test duration ends, the version leading on the winning metric is sent to the rest of the list. Version A is sent on a tie.') }}</span>
                </span>
            </label>

            <div v-if="isSettingsChanged" class="flex justify-end">
                <Button type="tertiary" :label="ctrans('Save A/B test settings')" :loading="isSaving"
                    :disabled="trackingHours < 1 || trackingHours > 720 || !sizePercentage || variantDelaySeconds > 604800" @click="saveAllSettings">
                    <template #icon>
                        <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </div>
        <p v-if="abTest.variant_sends_at" class="text-sm text-gray-700">
            {{ ctrans('Version B sends on :date', { date: formatDate(abTest.variant_sends_at) }) }}
        </p>
        <p v-if="isTrackingResults" class="text-sm text-gray-700">
            {{ abTest.auto_send_winner && abTest.size_percentage < 100
                ? ctrans('Tracking results until :date, then the winner is sent automatically.', { date: formatDate(abTest.tracking_ends_at!) })
                : ctrans('Tracking results until :date, then the winner can be sent.', { date: formatDate(abTest.tracking_ends_at!) }) }}
        </p>

        <div v-if="abTest.enabled" class="overflow-x-auto border-t border-gray-100 pt-4">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left">
                        <th class="py-2 pr-4 font-medium text-gray-500"></th>
                        <th v-for="version in abTest.versions" :key="version.id" class="py-2 pr-4 align-top font-normal">
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-[--app-accent-soft] px-2 py-0.5 font-semibold text-[--app-accent-strong]">
                                    {{ version.label }}
                                </span>
                                <span v-if="winner?.id === version.id"
                                    class="rounded bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">
                                    {{ ctrans('Winner') }}
                                </span>
                                <span v-else-if="!winner && abTest.suggested_winner_id === version.id"
                                    v-tooltip="ctrans('Higher :metric', { metric: abTest.winning_metric_options.find(option => option.value === abTest.winning_metric)?.label.toLowerCase() ?? '' })"
                                    class="rounded bg-green-50 px-2 py-0.5 text-xs font-semibold text-green-700">
                                    {{ ctrans('Suggested winner') }}
                                </span>
                                <span class="text-xs text-gray-500">{{ version.state_label }}</span>
                            </div>
                            <div class="mt-1 flex items-center gap-1 font-medium text-gray-800">
                                {{ version.subject }}
                                <MailshotSubjectEdit v-if="canEditVersionContent(version)"
                                    :mailshot="version.copy" :updateMailshotRoute="version.update_route"
                                    :suggestCopyRoute="version.suggest_copy_route" @saved="router.reload()" />
                            </div>
                            <div class="text-xs text-gray-500">{{ ctrans('From') }}: {{ version.sender_name }}</div>
                            <div class="mt-1 flex gap-3 text-xs">
                                <Link :href="route(version.route.name, version.route.parameters)"
                                    class="text-[--app-accent] hover:underline">
                                    {{ ctrans('View') }}
                                </Link>
                                <Link v-if="canEditVersionContent(version)"
                                    :href="route(version.workshop_route.name, version.workshop_route.parameters)"
                                    v-tooltip="ctrans('Header and hero image, call-to-action buttons, body and offers')"
                                    class="text-[--app-accent] hover:underline">
                                    {{ ctrans('Edit email design') }}
                                </Link>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="metric in metrics" :key="metric.key" class="border-b border-gray-100">
                        <td class="py-1.5 pr-4 text-gray-500">{{ metric.label }}</td>
                        <td v-for="version in abTest.versions" :key="version.id" class="py-1.5 pr-4 tabular-nums"
                            :class="isLeading(metric, version) ? 'font-semibold text-green-700' : 'text-gray-800'">
                            {{ version.stats[metric.key] }}{{ metric.isRate ? '%' : '' }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="abTest.results_tracked_until" class="mt-2 text-xs text-gray-500">
                {{ ctrans('Results counted until :date, the end of the test. Later opens, clicks and orders, and the winner sent to the rest of the list, show in each version\'s own stats.', { date: formatDate(abTest.results_tracked_until) }) }}
            </p>
        </div>

        <div v-if="abTest.can_send_winner" class="flex flex-wrap items-center gap-3">
            <span class="text-sm text-gray-700">
                {{ ctrans('Send the winner to the remaining :rest% of the list:', { rest: 100 - abTest.size_percentage }) }}
            </span>
            <ModalConfirmation v-for="version in abTest.versions" :key="version.id"
                :title="ctrans('Send version :label to the rest of the list?', { label: version.label })"
                :description="ctrans('Everyone on the list who did not receive the test will get version :label. This cannot be undone.', { label: version.label })">
                <template #default="{ changeModel }">
                    <Button :label="abTest.suggested_winner_id === version.id ? ctrans('Send :label (suggested)', { label: version.label }) : ctrans('Send :label', { label: version.label })"
                        type="tertiary" :disabled="isSaving" @click="changeModel">
                        <template #icon>
                            <FontAwesomeIcon :icon="faPaperPlane" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </template>
                <template #btn-yes="{ closeModal }">
                    <Button :label="ctrans('Yes, send :label', { label: version.label })" type="tertiary" :loading="isSaving"
                        @click="() => { closeModal(); sendWinner(version) }">
                        <template #icon>
                            <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </template>
            </ModalConfirmation>
        </div>
        <p v-else-if="abTest.enabled && abTest.size_percentage < 100 && !abTest.winner_sent_at" class="text-sm text-gray-500">
            {{ ctrans('Once both versions have been sent and the results tracked, you can send the winner to the remaining :rest% of the list.', { rest: 100 - abTest.size_percentage }) }}
        </p>
        <p v-if="winner && abTest.winner_sent_at" class="text-sm text-gray-700">
            {{ ctrans('Version :label was sent to the rest of the list on :date', { label: winner.label, date: new Date(abTest.winner_sent_at).toLocaleString() }) }}
        </p>
    </section>
</template>
