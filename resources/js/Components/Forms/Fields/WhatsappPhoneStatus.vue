<script setup lang="ts">
import { computed, ref } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInfoCircle, faExclamationTriangle } from "@fal"

library.add(faInfoCircle, faExclamationTriangle)

defineOptions({ inheritAttrs: false })

type RouteType = { name: string; parameters?: Record<string, unknown> }

type PhoneStatus = {
    status?: string
    code_verification_status?: string
    verified_name?: string
    quality_rating?: string
    display_phone_number?: string
}

const props = defineProps<{
    fieldData: {
        routes: {
            status: RouteType
            request_code: RouteType
            verify_code: RouteType
            register: RouteType
        }
    }
}>()

const status = ref<PhoneStatus | null>(null)
const isChecking = ref(false)

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
        return { label: trans("Not checked yet"), class: "bg-gray-100 text-gray-600 ring-gray-300" }
    }
    if (isConnected.value) {
        return { label: status.value.status, class: "bg-lime-100 text-lime-700 ring-lime-300" }
    }
    return { label: status.value.status || trans("Offline"), class: "bg-amber-100 text-amber-700 ring-amber-300" }
})

const post = async (routeTarget: RouteType, data: Record<string, unknown> = {}) => {
    const response = await axios.post(route(routeTarget.name, routeTarget.parameters), data)
    return response.data
}

// Meta reports the number's real state, so a failed read must not leave a stale badge
// claiming the number is still connected.
const checkStatus = async () => {
    isChecking.value = true
    try {
        const data = await post(props.fieldData.routes.status)
        status.value = data.data ?? {}
    } catch (error: any) {
        status.value = null
        notify({
            title: trans("Something went wrong."),
            text: error.response?.data?.message ?? trans("Could not read the status of this number."),
            type: "error",
        })
    }
    isChecking.value = false
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

const runStep = async (routeTarget: RouteType, data: Record<string, unknown>, onDone: () => void) => {
    isProcessing.value = true
    stepError.value = ""
    try {
        await post(routeTarget, data)
        onDone()
    } catch (error: any) {
        stepError.value = error.response?.data?.message ?? trans("Something went wrong.")
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
        notify({
            title: trans("Number registered"),
            text: trans("This number can now send and receive WhatsApp messages."),
            type: "success",
        })
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

            <Button
                :style="'tertiary'"
                size="xs"
                :label="trans('Check status')"
                :loading="isChecking"
                @click="checkStatus" />

            <Button
                v-if="status && !isConnected"
                :style="'save'"
                size="xs"
                :label="trans('Verify number')"
                @click="openModal" />
        </div>

        <dl v-if="status" class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-500 sm:max-w-md">
            <template v-for="key in ['display_phone_number', 'verified_name', 'code_verification_status', 'quality_rating']" :key="key">
                <dt v-if="status[key]" class="truncate">{{ key.replaceAll('_', ' ') }}</dt>
                <dd v-if="status[key]" class="truncate font-medium text-gray-700">{{ status[key] }}</dd>
            </template>
        </dl>

        <Modal :isOpen="isOpen" @onClose="closeModal" width="w-full max-w-lg">
            <div class="text-left">
                <h3 class="text-base font-semibold text-gray-900">{{ trans("Verify WhatsApp number") }}</h3>

                <ol v-if="!isVerified" class="mt-3 flex gap-4 text-xs">
                    <li v-for="(stepLabel, index) in [trans('Send code'), trans('Verify'), trans('Register')]" :key="stepLabel"
                        :class="step === index + 1 ? 'font-semibold text-gray-900' : 'text-gray-400'">
                        {{ index + 1 }}. {{ stepLabel }}
                    </li>
                </ol>
                <p v-else class="mt-3 text-xs font-semibold text-gray-900">{{ trans("Register") }}</p>

                <div v-if="stepError" class="mt-3 rounded-sm border border-red-300 bg-red-50 px-2 py-1 text-xs text-red-700">
                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mr-1" fixed-width aria-hidden="true" />
                    {{ stepError }}
                </div>

                <div v-if="step === 1" class="mt-4 space-y-3">
                    <p class="text-xs text-gray-500">
                        {{ trans("Meta sends a one time code to this number to confirm you control it.") }}
                    </p>
                    <div class="flex gap-4 text-sm">
                        <label v-for="method in ['SMS', 'VOICE']" :key="method" class="flex items-center gap-1">
                            <input v-model="codeMethod" type="radio" :value="method" />
                            {{ method === "SMS" ? trans("SMS") : trans("Voice call") }}
                        </label>
                    </div>
                    <label class="block text-sm">
                        <span class="text-gray-500">{{ trans("Language") }}</span>
                        <input v-model="language" type="text" class="mt-1 block w-24 rounded-md border-gray-300 text-sm" />
                    </label>
                    <div class="flex justify-end">
                        <Button :style="'save'" :label="trans('Send code')" :loading="isProcessing" @click="requestCode" />
                    </div>
                </div>

                <div v-else-if="step === 2" class="mt-4 space-y-3">
                    <label class="block text-sm">
                        <span class="text-gray-500">{{ trans("Verification code") }}</span>
                        <input v-model="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                            class="mt-1 block w-32 rounded-md border-gray-300 tracking-widest" />
                    </label>
                    <div class="flex justify-between">
                        <Button :style="'tertiary'" :label="trans('Back')" @click="step = 1" />
                        <Button :style="'save'" :label="trans('Verify')" :loading="isProcessing" @click="verifyCode" />
                    </div>
                </div>

                <div v-else class="mt-4 space-y-3">
                    <label class="block text-sm">
                        <span class="text-gray-500">{{ trans("Two step verification PIN") }}</span>
                        <input v-model="pin" type="text" inputmode="numeric" maxlength="6"
                            class="mt-1 block w-32 rounded-md border-gray-300 tracking-widest" />
                    </label>
                    <p class="text-xs text-amber-700">
                        <FontAwesomeIcon icon="fal fa-info-circle" class="mr-1" fixed-width aria-hidden="true" />
                        {{ trans("This PIN is not stored by Aiku. Meta asks for it again if the number is ever registered anew, so keep a record of it.") }}
                    </p>
                    <div class="flex" :class="isVerified ? 'justify-end' : 'justify-between'">
                        <Button v-if="!isVerified" :style="'tertiary'" :label="trans('Back')" @click="step = 2" />
                        <Button :style="'save'" :label="trans('Register')" :loading="isProcessing" @click="register" />
                    </div>
                </div>
            </div>
        </Modal>
    </div>
</template>
