<!--
  - Author: eka yudinata (https://github.com/ekayudinata)
  - Copyright (c) 2026, eka yudinata
  -->

<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from "vue"
import { router, useForm } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { faCheck } from "@fortawesome/free-solid-svg-icons"
import { Checkbox } from "primevue"
import { ctrans } from "@/Composables/useTrans"
import PhoneField from "@/Components/Forms/Fields/Phone.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { routeType } from "@/types/route"

export interface WhatsappOptInData {
    anchor_id: string
    is_subscribed: boolean
    whatsapp_phone: string | null
    suggested_phone: string | null
    default_country: string | null
    consent_text: string
    privacy_policy_url: string
    subscribe_route: routeType
    unsubscribe_route: routeType
    dismiss_route?: routeType | null
}

const props = defineProps<{
    data: WhatsappOptInData
}>()

const ANCHOR_ID = props.data.anchor_id

const cardEl = ref<HTMLElement | null>(null)
const headingEl = ref<HTMLElement | null>(null)

const isChangingNumber = ref(false)
const isUsingKnownNumber = computed(() => !!props.data.whatsapp_phone && !isChangingNumber.value)
const isEditingSubscription = ref(false)
const isUnsubscribing = ref(false)
const requestError = ref("")

const utm = (() => {
    if (typeof window === "undefined") {
        return {}
    }
    const params = new URLSearchParams(window.location.search)
    const values: Record<string, string> = {}
    for (const key of ["utm_source", "utm_medium", "utm_campaign"]) {
        const value = params.get(key)
        if (value) {
            values[key] = value.slice(0, 100)
        }
    }
    return values
})()

const form = useForm({
    phone: props.data.suggested_phone ?? "",
    consent: false,
})

const phoneFieldOptions = { defaultCountry: props.data.default_country ?? undefined }
const phoneFieldData = { placeholder: ctrans("Enter mobile number") }

const showForm = computed(() => !props.data.is_subscribed || isEditingSubscription.value)

const submit = () => {
    form.clearErrors()
    requestError.value = ""

    let phone: string | null = props.data.whatsapp_phone
    if (!isUsingKnownNumber.value) {
        if ((form.phone ?? "").replace(/\D/g, "").length <= 4) {
            form.setError("phone", ctrans("Please enter your WhatsApp number."))
            return
        }

        phone = form.phone
    }

    if (!form.consent) {
        form.setError("consent", ctrans("Please tick the box to confirm you would like WhatsApp updates."))
        return
    }

    form
        .transform((data) => ({ ...data, phone, ...utm }))
        .post(route(props.data.subscribe_route.name, props.data.subscribe_route.parameters), {
            preserveScroll: true,
            onSuccess: () => {
                isChangingNumber.value = false
                isEditingSubscription.value = false
                form.reset()
                nextTick(() => headingEl.value?.focus({ preventScroll: true }))
            },
            onError: (errors) => {
                if (!errors.phone && !errors.consent) {
                    requestError.value = ctrans("We could not save your WhatsApp preference, please try again.")
                }
            },
        })
}

const isDismissing = ref(false)

const dismiss = () => {
    if (!props.data.dismiss_route) {
        return
    }
    router.post(route(props.data.dismiss_route.name, props.data.dismiss_route.parameters), {}, {
        preserveScroll: true,
        onStart: () => {
            isDismissing.value = true
            requestError.value = ""
        },
        onFinish: () => (isDismissing.value = false),
        onError: () => {
            requestError.value = ctrans("We could not update your WhatsApp preference, please try again.")
        },
    })
}

const unsubscribe = () => {
    router.patch(
        route(props.data.unsubscribe_route.name, props.data.unsubscribe_route.parameters),
        { is_subscribed_to_whatsapp_newsletter: false },
        {
            preserveScroll: true,
            onStart: () => {
                isUnsubscribing.value = true
                requestError.value = ""
            },
            onFinish: () => (isUnsubscribing.value = false),
            onError: () => {
                requestError.value = ctrans("We could not update your WhatsApp preference, please try again.")
            },
        }
    )
}

const changeNumber = () => {
    isChangingNumber.value = true
    isEditingSubscription.value = true
    form.consent = false
}

const CARD_GAP_BELOW_HEADER = 16
const REALIGN_DELAYS_MS = [150, 600, 1500]
const USER_SCROLL_EVENTS = ["wheel", "touchstart", "keydown", "mousedown"] as const

const stickyHeaderHeight = (): number => {
    let height = 0
    document.querySelectorAll<HTMLElement>("[class*='sticky'], [class*='fixed']").forEach((element) => {
        if (cardEl.value && element.contains(cardEl.value)) {
            return
        }
        const style = getComputedStyle(element)
        const rect = element.getBoundingClientRect()
        const isTopBar = (style.position === "sticky" || style.position === "fixed") && parseFloat(style.top) === 0
        const isHeaderSized = rect.height > 0 && rect.height < window.innerHeight / 2 && rect.width > window.innerWidth / 2
        if (isTopBar && isHeaderSized) {
            height = Math.max(height, rect.height)
        }
    })
    return height
}

const alignCardBelowHeader = () => {
    if (!cardEl.value) {
        return
    }
    cardEl.value.style.scrollMarginTop = `${stickyHeaderHeight() + CARD_GAP_BELOW_HEADER}px`
    cardEl.value.scrollIntoView({ behavior: "auto", block: "start" })
}

onMounted(() => {
    if (window.location.hash !== `#${ANCHOR_ID}`) {
        return
    }

    let hasUserScrolled = false
    const stopRealigning = () => {
        hasUserScrolled = true
        USER_SCROLL_EVENTS.forEach((eventName) => window.removeEventListener(eventName, stopRealigning))
    }
    USER_SCROLL_EVENTS.forEach((eventName) => window.addEventListener(eventName, stopRealigning, { passive: true }))

    const focusHeadingUnlessFocusMoved = () => {
        if (!document.activeElement || document.activeElement === document.body) {
            headingEl.value?.focus({ preventScroll: true })
        }
    }

    const realignUnlessUserScrolled = () => {
        if (!hasUserScrolled) {
            alignCardBelowHeader()
            focusHeadingUnlessFocusMoved()
        }
    }

    nextTick(() => {
        alignCardBelowHeader()
        focusHeadingUnlessFocusMoved()
        REALIGN_DELAYS_MS.forEach((delay) => window.setTimeout(realignUnlessUserScrolled, delay))
        if (document.readyState !== "complete") {
            window.addEventListener("load", realignUnlessUserScrolled, { once: true })
        }
        window.setTimeout(stopRealigning, REALIGN_DELAYS_MS[REALIGN_DELAYS_MS.length - 1] + 100)
    })
})
</script>

<template>
    <section
        :id="ANCHOR_ID"
        ref="cardEl"
        aria-labelledby="whatsapp-updates-heading"
        class="scroll-mt-36 rounded-lg border border-emerald-200 bg-emerald-50/70 p-4"
    >
        <div class="flex items-center gap-3">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-[#25D366] text-white" aria-hidden="true">
                <FontAwesomeIcon :icon="faWhatsapp" class="text-lg" fixed-width />
            </span>

                <h2
                    id="whatsapp-updates-heading"
                    ref="headingEl"
                    tabindex="-1"
                    class="min-w-0 flex-1 text-lg font-semibold text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-800 focus-visible:ring-offset-2 rounded"
                >
                    {{ ctrans("Your updates, on WhatsApp") }}
                </h2>
        </div>
        <div>
            <div>
                <p class="mt-2 text-sm text-gray-700">
                    {{ ctrans("Stay informed about your orders and membership, get restock alerts, and receive occasional offers, all in one place.") }}
                </p>
                <ul class="mt-2 flex flex-wrap gap-1.5" :aria-label="ctrans('What you will receive')">
                    <li class="rounded-full border border-emerald-300 bg-white px-2 py-0.5 text-xs font-medium text-green-800">{{ ctrans("Order updates") }}</li>
                    <li class="rounded-full border border-emerald-300 bg-white px-2 py-0.5 text-xs font-medium text-green-800">{{ ctrans("Membership updates") }}</li>
                    <li class="rounded-full border border-emerald-300 bg-white px-2 py-0.5 text-xs font-medium text-green-800">{{ ctrans("Restocks") }}</li>
                    <li class="rounded-full border border-emerald-300 bg-white px-2 py-0.5 text-xs font-medium text-green-800">{{ ctrans("Occasional offers") }}</li>
                </ul>

                <div v-if="!showForm" class="mt-3" role="status">
                    <p class="flex items-center gap-2 text-sm font-medium text-green-800">
                        <span class="flex size-6 items-center justify-center rounded-full bg-green-800 text-white" aria-hidden="true">
                            <FontAwesomeIcon :icon="faCheck" class="text-xs" fixed-width />
                        </span>
                        <span v-if="data.whatsapp_phone">{{ ctrans("You are subscribed to WhatsApp updates on :phone.", { phone: data.whatsapp_phone }) }}</span>
                        <span v-else>{{ ctrans("You are subscribed to WhatsApp updates.") }}</span>
                    </p>
                    <div class="mt-1 flex flex-wrap gap-x-5 text-sm">
                        <button type="button" class="min-h-11 text-gray-700 underline hover:text-gray-900" @click="changeNumber">
                            {{ data.whatsapp_phone ? ctrans("Use a different number") : ctrans("Add your WhatsApp number") }}
                        </button>
                        <button
                            type="button"
                            class="min-h-11 text-gray-700 underline hover:text-gray-900 disabled:opacity-50"
                            :disabled="isUnsubscribing"
                            @click="unsubscribe"
                        >
                            {{ ctrans("Unsubscribe") }}
                        </button>
                    </div>
                    <p v-if="requestError" class="mt-1 text-xs text-red-600" role="alert">{{ requestError }}</p>
                </div>

                <form v-else class="mt-3 max-w-2xl" novalidate @submit.prevent="submit">
                    <div v-if="isUsingKnownNumber">
                        <p class="text-sm font-medium text-gray-900">{{ ctrans("Your WhatsApp number") }}</p>
                        <p class="mt-1 flex flex-wrap items-center gap-x-4 text-sm text-gray-800">
                            <span class="font-semibold">{{ data.whatsapp_phone }}</span>
                            <button type="button" class="min-h-11 text-gray-600 underline hover:text-gray-900" @click="changeNumber">
                                {{ ctrans("Use a different number") }}
                            </button>
                        </p>
                    </div>

                    <div v-else>
                        <p class="mb-1 block text-sm font-medium text-gray-900">
                            {{ ctrans("Your WhatsApp number") }} <span class="text-red-500">*</span>
                        </p>
                        <div class="mb-6 max-w-sm rounded-md bg-white">
                            <PhoneField
                                :form="form"
                                fieldName="phone"
                                :options="phoneFieldOptions"
                                :fieldData="phoneFieldData"
                            />
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="flex items-start gap-3 text-xs text-gray-800">
                            <Checkbox
                                v-model="form.consent"
                                inputId="whatsapp-optin-consent"
                                name="whatsapp_optin_consent"
                                binary
                                :invalid="!!form.errors.consent"
                                :ariaLabelledby="'whatsapp-optin-consent-label'"
                                :pt="{ input: { 'aria-describedby': form.errors.consent ? 'whatsapp-optin-consent-error whatsapp-optin-helper' : 'whatsapp-optin-helper' } }"
                                class="mt-0.5 shrink-0"
                            />
                            <label id="whatsapp-optin-consent-label" for="whatsapp-optin-consent" class="cursor-pointer">{{ data.consent_text }}</label>
                        </div>
                        <p v-if="form.errors.consent" id="whatsapp-optin-consent-error" class="ml-8 mt-1 text-xs text-red-600">{{ form.errors.consent }}</p>
                        <p id="whatsapp-optin-helper" class="ml-8 mt-1 text-xs text-gray-600">
                            {{ ctrans("Unsubscribe at any time in your dashboard or by replying STOP.") }}
                            <a
                                :href="data.privacy_policy_url"
                                target="_blank"
                                rel="noopener"
                                class="ml-1 underline hover:text-gray-900"
                            >{{ ctrans("Privacy policy") }}</a>
                        </p>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1">
                        <button
                            type="submit"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-green-800 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-800 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            <LoadingIcon v-if="form.processing" />
                            <FontAwesomeIcon v-else :icon="faWhatsapp" fixed-width aria-hidden="true" />
                            {{ ctrans("Keep me updated on WhatsApp") }}
                        </button>
                        <button
                            v-if="data.is_subscribed"
                            type="button"
                            class="min-h-11 text-sm text-gray-700 underline hover:text-gray-900"
                            @click="isEditingSubscription = false; isChangingNumber = false; requestError = ''; form.clearErrors()"
                        >
                            {{ ctrans("Cancel") }}
                        </button>
                        <button
                            v-if="data.dismiss_route && !data.is_subscribed"
                            type="button"
                            class="ml-auto min-h-11 text-xs text-gray-500 underline hover:text-gray-700 disabled:opacity-60"
                            :disabled="isDismissing"
                            @click="dismiss"
                        >
                            {{ ctrans("Not interested, thanks") }}
                        </button>
                    </div>
                    <p v-if="requestError" class="mt-2 text-xs text-red-600" role="alert">{{ requestError }}</p>
                </form>
            </div>
        </div>
    </section>
</template>
