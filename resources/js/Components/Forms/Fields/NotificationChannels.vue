<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCircle, faCheckDouble, faCommentLines, faAt, faQuestionCircle, faBell } from "@fal"
import { faCheckCircle as fasCheckCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faCircle, faCheckDouble, faCommentLines, faAt, faQuestionCircle, faBell, fasCheckCircle)

type Push = {
    public_key: string | null
    devices_count: number
    store_route: { name: string }
    delete_route: { name: string }
}

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData: {
        events: { value: string, label: string, icon?: string }[]
        channels: { value: string, label: string, available?: boolean, unavailable_reason?: string, push?: Push }[]
    }
}>()

const push = computed(() => props.fieldData.channels.find(channel => channel.push)?.push)

const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent)
const isStandalone = window.matchMedia("(display-mode: standalone)").matches || (navigator as any).standalone === true
const isSupported = "serviceWorker" in navigator && "PushManager" in window && "Notification" in window

const permission = ref<NotificationPermission | "unsupported">(isSupported ? Notification.permission : "unsupported")
const deviceEndpoint = ref<string | null>(null)
const isBusy = ref(false)
const pushError = ref<string | null>(null)

const browserBlockedReason = computed((): string | null => {
    if (isIos && !isStandalone) {
        return trans("On iPhone or iPad, add Aiku to your home screen (Share → Add to Home Screen) and open it from there")
    }
    if (!isSupported) {
        return trans("This browser does not support notifications")
    }
    if (permission.value === "denied") {
        return trans("Notifications are blocked for Aiku in this browser's site settings")
    }
    return null
})

const channelAvailable = (channel: { value: string, available?: boolean }): boolean => channel.available !== false && !(channel.value === "browser" && browserBlockedReason.value)

const channelReason = (channel: { value: string, available?: boolean, unavailable_reason?: string }): string | undefined => {
    if (channel.available === false) {
        return channel.unavailable_reason
    }
    return channel.value === "browser" ? browserBlockedReason.value ?? undefined : undefined
}

const urlBase64ToUint8Array = (base64: string): Uint8Array => {
    const padded = (base64 + "=".repeat((4 - base64.length % 4) % 4)).replace(/-/g, "+").replace(/_/g, "/")
    return Uint8Array.from(atob(padded), char => char.charCodeAt(0))
}

onMounted(async () => {
    if (!isSupported || !push.value?.public_key) {
        return
    }
    const registration = await navigator.serviceWorker.getRegistration("/")
    deviceEndpoint.value = (await registration?.pushManager.getSubscription())?.endpoint ?? null
})

const enableOnThisDevice = async () => {
    if (!push.value?.public_key) {
        return
    }
    pushError.value = null
    permission.value = await Notification.requestPermission()
    if (permission.value !== "granted") {
        return
    }
    isBusy.value = true
    try {
        const registration = await navigator.serviceWorker.register("/aiku-sw.js", { scope: "/" })
        await navigator.serviceWorker.ready
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(push.value.public_key),
        })
        await axios.post(route(push.value.store_route.name), subscription.toJSON())
        deviceEndpoint.value = subscription.endpoint
    } catch (error) {
        pushError.value = trans("Could not enable notifications on this device")
    } finally {
        isBusy.value = false
    }
}

const disableOnThisDevice = async () => {
    if (!push.value || !deviceEndpoint.value) {
        return
    }
    isBusy.value = true
    try {
        const registration = await navigator.serviceWorker.getRegistration("/")
        await (await registration?.pushManager.getSubscription())?.unsubscribe()
        await axios.delete(route(push.value.delete_route.name), { data: { endpoint: deviceEndpoint.value } })
        deviceEndpoint.value = null
    } finally {
        isBusy.value = false
    }
}

const isChosen = (event: string, channel: string): boolean => (props.form[props.fieldName]?.[event] ?? []).includes(channel)

const toggle = (event: string, channel: string) => {
    const current: string[] = props.form[props.fieldName]?.[event] ?? []
    props.form[props.fieldName] = {
        ...props.form[props.fieldName],
        [event]: isChosen(event, channel) ? current.filter(value => value !== channel) : [...current, channel],
    }
}
</script>

<template>
    <div class="w-full">
        <div
            v-for="event in fieldData.events"
            :key="event.value"
            class="grid grid-cols-1 sm:grid-cols-2 gap-x-1.5 px-2 items-center border-b border-gray-200 even:bg-gray-50"
        >
            <div class="flex items-center gap-x-1.5 py-3 text-gray-700">
                <FontAwesomeIcon v-if="event.icon" :icon="event.icon" class="text-gray-400" fixed-width aria-hidden="true" />
                {{ event.label }}
            </div>

            <div class="flex flex-wrap items-center gap-x-4 pb-2 sm:pb-0">
                <button
                    v-for="channel in fieldData.channels"
                    :key="channel.value"
                    type="button"
                    role="checkbox"
                    :aria-checked="channelAvailable(channel) && isChosen(event.value, channel.value)"
                    :aria-disabled="!channelAvailable(channel)"
                    v-tooltip="channelReason(channel)"
                    @click.prevent="channelAvailable(channel) && toggle(event.value, channel.value)"
                    class="group flex items-center gap-x-1.5 rounded-md py-2 px-3 font-medium cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 "
                    :class="!channelAvailable(channel) ? 'cursor-not-allowed opacity-40' : ''"
                >
                    <FontAwesomeIcon v-if="channelAvailable(channel) && isChosen(event.value, channel.value)" icon="fas fa-check-circle" class="text-green-500" fixed-width aria-hidden="true" />
                    <FontAwesomeIcon v-else icon="fal fa-circle" class="text-gray-400 group-hover:text-gray-700" fixed-width aria-hidden="true" />
                    <span class="text-gray-700">{{ channel.label }}</span>
                </button>
            </div>
        </div>

        <div v-if="push?.public_key" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-2 py-3 text-gray-700">
            <FontAwesomeIcon icon="fal fa-bell" class="text-gray-400" fixed-width aria-hidden="true" />
            <template v-if="browserBlockedReason">
                <span class="text-gray-500">{{ browserBlockedReason }}</span>
            </template>
            <template v-else-if="deviceEndpoint">
                <span>{{ trans("Browser notifications are on for this device") }}</span>
                <button type="button" :disabled="isBusy" @click.prevent="disableOnThisDevice" class="font-medium text-indigo-600 hover:underline disabled:opacity-50">
                    {{ trans("Turn off") }}
                </button>
            </template>
            <template v-else>
                <button type="button" :disabled="isBusy" @click.prevent="enableOnThisDevice" class="rounded-md bg-indigo-600 px-3 py-1.5 font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                    {{ trans("Enable browser notifications on this device") }}
                </button>
                <span v-if="push.devices_count" class="text-gray-500">{{ trans(":count other devices enabled", { count: String(push.devices_count) }) }}</span>
            </template>
            <span v-if="pushError" class="text-red-600">{{ pushError }}</span>
        </div>
    </div>
</template>
