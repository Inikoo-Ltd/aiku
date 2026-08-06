<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { Checkbox } from "primevue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { routeType } from "@/types/route"

const props = defineProps<{
    label: string
    updateRoute: routeType
}>()

const isLoading = ref(false)
const hasOptedIn = ref(false)
const isChecked = ref(false)

const optIn = (value: boolean) => {
    if (!value) {
        return
    }

    router.patch(
        route(props.updateRoute.name, props.updateRoute.parameters),
        { is_subscribed_to_whatsapp_newsletter: true },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                isLoading.value = true
            },
            onFinish: () => {
                isLoading.value = false
            },
            onSuccess: () => {
                hasOptedIn.value = true
            },
            onError: (error) => {
                console.error(error)
                isChecked.value = false
                notify({
                    title: trans("Something went wrong."),
                    text: trans("Failed to subscribe to the WhatsApp newsletter"),
                    type: "error",
                })
            },
        }
    )
}
</script>

<template>
    <div
        v-if="hasOptedIn"
        class="relative mt-6 md:mx-10 rounded border border-green-300 bg-green-50 px-4 py-3 flex items-center gap-3 text-green-800">
        <FontAwesomeIcon :icon="faWhatsapp" class="text-xl text-green-600" fixed-width aria-hidden="true" />
        <span>{{ trans("You are subscribed. We will send our offers to your WhatsApp.") }}</span>
    </div>

    <div
        v-else
        class="whatsapp-optin relative mt-6 md:mx-10 rounded border border-green-300 bg-green-50 px-4 py-3 flex items-start gap-3">
        <FontAwesomeIcon :icon="faWhatsapp" class="mt-0.5 text-xl text-green-600" fixed-width aria-hidden="true" />

        <Checkbox
            v-model="isChecked"
            @update:model-value="optIn"
            :disabled="isLoading"
            inputId="opt_in_whatsapp_newsletter_checkout"
            name="opt_in_whatsapp_newsletter_checkout"
            binary
            class="mt-0.5" />

        <label for="opt_in_whatsapp_newsletter_checkout" class="cursor-pointer text-green-900">
            {{ label }}
        </label>
    </div>
</template>

<style scoped>
.whatsapp-optin::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    z-index: -1;
    box-shadow:
        0 0 18px rgba(37, 211, 102, 0.55),
        0 0 42px rgba(37, 211, 102, 0.35);
    opacity: 0.6;
    animation: whatsapp-optin-glow 1.6s ease-in-out 3;
}

@keyframes whatsapp-optin-glow {
    0%, 100% {
        opacity: 0.45;
        transform: scale(1);
    }
    50% {
        opacity: 1;
        transform: scale(1.03);
    }
}

@media (prefers-reduced-motion: reduce) {
    .whatsapp-optin::after {
        animation: none;
    }
}
</style>
