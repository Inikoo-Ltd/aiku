<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import InputText from "primevue/inputtext"
import { ctrans } from "@/Composables/useTrans"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInfoCircle, faExclamationTriangle } from "@fal"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { useFormatTime, useRangeFromNow } from "@/Composables/useFormatTime"

library.add(faInfoCircle, faExclamationTriangle, faWhatsapp)

defineOptions({ inheritAttrs: false })

type RouteType = { name: string; parameters?: Record<string, unknown> }

type PhoneStatus = {
    status?: string
    code_verification_status?: string
    verified_name?: string
    quality_rating?: string
    display_phone_number?: string
    platform_type?: string
    is_on_biz_app?: boolean
}

declare global {
    interface Window {
        FB?: any
        fbAsyncInit?: () => void
    }
}

const props = defineProps<{
    fieldData: {
        routes: {
            status: RouteType
            request_code?: RouteType
            verify_code?: RouteType
            register?: RouteType
            subscribed_apps: RouteType
            subscribe_app?: RouteType
            embedded_signup?: RouteType
        }
        value?: { at: string; status: PhoneStatus } | null
        mode?: "cloud_api" | "coexistence"
        embedded_signup?: { app_id?: string | null; config_id?: string | null; api_version?: string }
    }
}>()

const isCoexistence = computed(() => props.fieldData.mode === "coexistence")

// Seeded from the last check stored on the shop, so the page opens with what Meta last said
// instead of a blank badge. It is a dated copy, never presented without its age.
const status = ref<PhoneStatus | null>(props.fieldData.value?.status ?? null)
const checkedAt = ref<string | null>(props.fieldData.value?.at ?? null)
const isChecking = ref(false)

// Not stored on the shop like the phone status, so it stays unknown until a check runs
// rather than opening on a dated claim that the webhooks are wired up.
const isSubscribed = ref<boolean | null>(null)
const otherSubscribedApps = ref<string[]>([])
const isSubscribing = ref(false)
const checkError = ref("")
const subscribeError = ref("")

const isOpen = ref(false)
const step = ref(1)
const stepError = ref("")
const isProcessing = ref(false)

const codeMethod = ref<"SMS" | "VOICE">("SMS")
const language = ref("en")
const code = ref("")
const pin = ref("")

const isConnected = computed(() => status.value?.status === "CONNECTED")

// Verification and connection are independent axes at Meta: a number can be verified but not yet
// registered, and Meta rejects request_code for it forever. The strict compare also keeps a status
// read that returned no payload on the full flow, rather than a register call Meta would reject.
const isVerified = computed(() => status.value?.code_verification_status === "VERIFIED")

const badge = computed(() => {
    if (!status.value) {
        return { label: ctrans("Not checked yet"), class: "bg-gray-100 text-gray-600 ring-gray-300" }
    }
    if (isConnected.value) {
        return { label: status.value.status, class: "bg-lime-100 text-lime-700 ring-lime-300" }
    }
    return { label: status.value.status || ctrans("Offline"), class: "bg-amber-100 text-amber-700 ring-amber-300" }
})

const subscriptionBadge = computed(() => {
    if (isSubscribed.value) {
        return { label: ctrans("App subscribed"), class: "bg-lime-100 text-lime-700 ring-lime-300" }
    }
    return { label: ctrans("App not subscribed"), class: "bg-amber-100 text-amber-700 ring-amber-300" }
})

const post = async (routeTarget: RouteType | undefined, data: Record<string, unknown> = {}) => {
    const response = await axios.post(route(routeTarget!.name, routeTarget!.parameters), data)
    return response.data
}

// Meta reports the number's real state, so a failed read must not leave a stale badge
// claiming the number is still connected.
const checkStatus = async () => {
    isChecking.value = true
    checkError.value = ""
    try {
        const data = await post(props.fieldData.routes.status)
        status.value = data.data ?? {}
        checkedAt.value = new Date().toISOString()

        const subscription = await post(props.fieldData.routes.subscribed_apps)
        isSubscribed.value = subscription.data?.subscribed ?? false
        otherSubscribedApps.value = isSubscribed.value
            ? []
            : (subscription.data?.apps ?? []).map((app: { id: string; name: string }) => app.name || app.id)
    } catch (error: any) {
        status.value = null
        checkedAt.value = null
        isSubscribed.value = null
        otherSubscribedApps.value = []
        checkError.value = error.response?.data?.message ?? ctrans("Could not read the status of this number.")
    }
    isChecking.value = false
}

// A number can be connected while its WABA has no app subscribed, which is what silently
// keeps inbound messages from ever reaching Aiku.
const subscribeApp = async () => {
    isSubscribing.value = true
    subscribeError.value = ""
    try {
        await post(props.fieldData.routes.subscribe_app)
        await checkStatus()
    } catch (error: any) {
        subscribeError.value = error.response?.data?.message ?? ctrans("Could not subscribe the app to this account.")
    }
    isSubscribing.value = false
}

// Embedded Signup is Meta's own popup: Facebook Login hands back a short lived code, and the
// signup page posts the account and number it connected to this window as a message. Both are
// needed, in either order, before the server can finish the connection.
const canConnect = computed(() => !!(props.fieldData.routes.embedded_signup && props.fieldData.embedded_signup?.app_id && props.fieldData.embedded_signup?.config_id))
const isConnecting = ref(false)
const connectError = ref("")
let signupCode: string | null = null
let signupAccount: { waba_id?: string; phone_number_id?: string } | null = null

const loadFacebookSdk = () =>
    new Promise<void>((resolve, reject) => {
        if (window.FB) {
            return resolve()
        }
        window.fbAsyncInit = () => {
            window.FB.init({
                appId: props.fieldData.embedded_signup?.app_id,
                autoLogAppEvents: true,
                xfbml: false,
                version: props.fieldData.embedded_signup?.api_version,
            })
            resolve()
        }
        const script = document.createElement("script")
        script.src = "https://connect.facebook.net/en_US/sdk.js"
        script.async = true
        script.crossOrigin = "anonymous"
        script.onerror = () => reject()
        document.body.appendChild(script)
    })

// The code works once, so it is taken before the request: a late second event finds nothing to send.
const finishSignup = async () => {
    if (!signupCode || !signupAccount?.waba_id) {
        return
    }
    const payload = { code: signupCode, ...signupAccount }
    signupCode = null
    signupAccount = null
    try {
        await post(props.fieldData.routes.embedded_signup, payload)
        router.reload()
    } catch (error: any) {
        connectError.value = error.response?.data?.message ?? ctrans("Could not connect this number.")
    }
    isConnecting.value = false
}

const onSignupMessage = (event: MessageEvent) => {
    if (event.origin !== "https://www.facebook.com" && !event.origin.endsWith(".facebook.com")) {
        return
    }
    let message: any
    try {
        message = typeof event.data === "string" ? JSON.parse(event.data) : event.data
    } catch {
        return
    }
    if (message?.type !== "WA_EMBEDDED_SIGNUP") {
        return
    }
    if (String(message.event).startsWith("FINISH")) {
        signupAccount = { waba_id: message.data?.waba_id, phone_number_id: message.data?.phone_number_id }
        finishSignup()
    } else if (message.event === "CANCEL" || message.event === "ERROR") {
        connectError.value = message.data?.error_message ?? ctrans("The WhatsApp signup was not finished.")
        isConnecting.value = false
    }
}

window.addEventListener("message", onSignupMessage)
onBeforeUnmount(() => window.removeEventListener("message", onSignupMessage))

const connect = async () => {
    isConnecting.value = true
    connectError.value = ""
    try {
        await loadFacebookSdk()
    } catch {
        connectError.value = ctrans("Facebook could not be reached to open the WhatsApp signup.")
        isConnecting.value = false
        return
    }
    window.FB.login(
        (response: any) => {
            if (!response.authResponse?.code) {
                connectError.value ||= ctrans("The WhatsApp signup was not finished.")
                isConnecting.value = false
                return
            }
            signupCode = response.authResponse.code
            finishSignup()
        },
        {
            config_id: props.fieldData.embedded_signup?.config_id,
            response_type: "code",
            override_default_response_type: true,
            extras: { setup: {}, featureType: "whatsapp_business_app_onboarding", sessionInfoVersion: "3" },
        }
    )
}

const openModal = () => {
    step.value = isVerified.value ? 3 : 1
    stepError.value = ""
    code.value = ""
    pin.value = ""
    isOpen.value = true
}

// The PIN is never stored, here or on the server, so it must not survive the modal either.
const closeModal = () => {
    isOpen.value = false
    pin.value = ""
    code.value = ""
    stepError.value = ""
}

const runStep = async (routeTarget: RouteType | undefined, data: Record<string, unknown>, onDone: () => void) => {
    isProcessing.value = true
    stepError.value = ""
    try {
        await post(routeTarget, data)
        onDone()
    } catch (error: any) {
        stepError.value = error.response?.data?.message ?? ctrans("Something went wrong.")
    }
    isProcessing.value = false
}

const requestCode = () =>
    runStep(
        props.fieldData.routes.request_code,
        { code_method: codeMethod.value, language: language.value },
        () => {
            step.value = 2
        }
    )

const verifyCode = () =>
    runStep(props.fieldData.routes.verify_code, { code: code.value }, () => {
        step.value = 3
    })

const register = () =>
    runStep(props.fieldData.routes.register, { pin: pin.value }, () => {
        closeModal()
        checkStatus()
    })
</script>

<template>
    <div class="w-full">
        <div class="flex flex-wrap items-center gap-2">
            <span
                class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset"
                :class="badge.class">
                {{ badge.label }}
            </span>

            <span
                v-tooltip="isCoexistence ? ctrans('The number stays on the WhatsApp Business app on the shop phone and Aiku reads and writes it too.') : ''"
                class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset bg-gray-100 text-gray-600 ring-gray-300">
                {{ isCoexistence ? ctrans("Coexistence") : ctrans("Cloud API") }}
            </span>

            <span
                v-if="isSubscribed !== null"
                class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset"
                :class="subscriptionBadge.class">
                {{ subscriptionBadge.label }}
            </span>

            <span
                v-if="checkedAt"
                v-tooltip="useFormatTime(checkedAt, { formatTime: 'hms' })"
                class="text-xs text-gray-500">
                {{ ctrans("checked :ago ago", { ago: useRangeFromNow(checkedAt) }) }}
            </span>

            <Button
                :style="'tertiary'"
                size="xs"
                :label="ctrans('Check status')"
                :loading="isChecking"
                @click="checkStatus" />

            <Button
                v-if="status && !isConnected && !isCoexistence && fieldData.routes.register"
                :style="'save'"
                size="xs"
                :label="ctrans('Verify number')"
                @click="openModal" />

            <Button
                v-if="isSubscribed === false && fieldData.routes.subscribe_app"
                :style="'save'"
                size="xs"
                :label="ctrans('Subscribe app')"
                :loading="isSubscribing"
                @click="subscribeApp" />

            <Button
                v-if="canConnect"
                :style="'tertiary'"
                size="xs"
                icon="fab fa-whatsapp"
                :label="ctrans('Connect WhatsApp Business app')"
                :loading="isConnecting"
                @click="connect" />
        </div>

        <p v-for="error in [checkError, subscribeError, connectError].filter(Boolean)" :key="error" class="mt-2 text-xs text-red-600">
            {{ error }}
        </p>

        <p v-if="isCoexistence" class="mt-2 text-xs text-amber-700">
            <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mr-1" fixed-width aria-hidden="true" />
            {{ ctrans("WhatsApp calls to this number ring on the shop phone only, not in Aiku. Open the WhatsApp Business app on that phone at least every 13 days, or Meta disconnects the number from Aiku.") }}
        </p>

        <p v-if="otherSubscribedApps.length" class="mt-2 text-xs text-amber-700">
            {{ ctrans("Only another Meta app is subscribed to this account (:apps), so its messages go there instead of Aiku.", { apps: otherSubscribedApps.join(", ") }) }}
        </p>

        <dl v-if="status" class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-500 sm:max-w-md">
            <template v-for="key in ['display_phone_number', 'verified_name', 'code_verification_status', 'quality_rating', 'platform_type', 'is_on_biz_app']" :key="key">
                <dt v-if="status[key] !== undefined && status[key] !== ''" class="truncate">{{ key.replaceAll('_', ' ') }}</dt>
                <dd v-if="status[key] !== undefined && status[key] !== ''" class="truncate font-medium text-gray-700">{{ status[key] === true ? ctrans("Yes") : status[key] === false ? ctrans("No") : status[key] }}</dd>
            </template>
        </dl>

        <Modal :isOpen="isOpen" @onClose="closeModal" width="w-full max-w-lg">
            <div class="text-left">
                <h3 class="text-base font-semibold text-gray-900">{{ ctrans("Verify WhatsApp number") }}</h3>

                <ol v-if="!isVerified" class="mt-3 flex gap-4 text-xs">
                    <li v-for="(stepLabel, index) in [ctrans('Send code'), ctrans('Verify'), ctrans('Register')]" :key="stepLabel"
                        :class="step === index + 1 ? 'font-semibold text-gray-900' : 'text-gray-400'">
                        {{ index + 1 }}. {{ stepLabel }}
                    </li>
                </ol>
                <p v-else class="mt-3 text-xs font-semibold text-gray-900">{{ ctrans("Register") }}</p>

                <div v-if="stepError" class="mt-3 rounded-sm border border-red-300 bg-red-50 px-2 py-1 text-xs text-red-700">
                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mr-1" fixed-width aria-hidden="true" />
                    {{ stepError }}
                </div>

                <div v-if="step === 1" class="mt-4 space-y-3">
                    <p class="text-xs text-gray-500">
                        {{ ctrans("Meta sends a one time code to this number to confirm you control it.") }}
                    </p>
                    <SegmentedToggle
                        v-model="codeMethod"
                        :options="[{ label: ctrans('SMS'), value: 'SMS' }, { label: ctrans('Voice call'), value: 'VOICE' }]" />
                    <label class="block text-sm">
                        <span class="text-gray-500">{{ ctrans("Language") }}</span>
                        <InputText v-model="language" size="small" class="mt-1 block w-24" />
                    </label>
                    <div class="flex justify-end">
                        <Button :style="'save'" :label="ctrans('Send code')" :loading="isProcessing" @click="requestCode" />
                    </div>
                </div>

                <div v-else-if="step === 2" class="mt-4 space-y-3">
                    <label class="block text-sm">
                        <span class="text-gray-500">{{ ctrans("Verification code") }}</span>
                        <InputText v-model="code" inputmode="numeric" autocomplete="one-time-code" size="small"
                            class="mt-1 block w-32 tracking-widest" />
                    </label>
                    <div class="flex justify-between">
                        <Button :style="'tertiary'" :label="ctrans('Back')" @click="step = 1" />
                        <Button :style="'save'" :label="ctrans('Verify')" :loading="isProcessing" @click="verifyCode" />
                    </div>
                </div>

                <div v-else class="mt-4 space-y-3">
                    <label class="block text-sm">
                        <span class="text-gray-500">{{ ctrans("Two step verification PIN") }}</span>
                        <InputText v-model="pin" inputmode="numeric" maxlength="6" size="small"
                            class="mt-1 block w-32 tracking-widest" />
                    </label>
                    <p class="text-xs text-amber-700">
                        <FontAwesomeIcon icon="fal fa-info-circle" class="mr-1" fixed-width aria-hidden="true" />
                        {{ ctrans("This PIN is not stored by Aiku. Meta asks for it again if the number is ever registered anew, so keep a record of it.") }}
                    </p>
                    <div class="flex" :class="isVerified ? 'justify-end' : 'justify-between'">
                        <Button v-if="!isVerified" :style="'tertiary'" :label="ctrans('Back')" @click="step = 2" />
                        <Button :style="'save'" :label="ctrans('Register')" :loading="isProcessing" @click="register" />
                    </div>
                </div>
            </div>
        </Modal>
    </div>
</template>
