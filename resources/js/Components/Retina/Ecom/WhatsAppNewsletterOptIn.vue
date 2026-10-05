<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { ctrans as trans } from "@/Composables/useTrans"
import { notify } from "@kyvg/vue3-notification"
import { Checkbox } from "primevue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { faCheck } from "@fortawesome/free-solid-svg-icons"
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
    <Transition name="whatsapp-optin-swap" mode="out-in">
        <div
            v-if="hasOptedIn"
            key="subscribed"
            class="mx-8 md:mx-12 mb-4 flex items-center gap-2.5 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs text-green-800">
            <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-[#25D366] text-white">
                <FontAwesomeIcon :icon="faCheck" class="text-[10px]" fixed-width aria-hidden="true" />
            </span>
            <span class="font-medium">{{ trans("You are subscribed. We will send our offers to your WhatsApp.") }}</span>
        </div>

        <label
            v-else
            key="opt-in"
            for="opt_in_whatsapp_newsletter_checkout"
            class="whatsapp-optin mx-8 md:mx-12 mb-4 flex cursor-pointer items-center gap-2.5 rounded-lg border border-green-200 bg-gradient-to-r from-green-50 to-white px-3 py-2 text-xs text-gray-700 transition-colors hover:border-green-400"
            :class="{ 'pointer-events-none opacity-60': isLoading }">
            <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-[#25D366] text-white">
                <FontAwesomeIcon :icon="faWhatsapp" class="text-sm" fixed-width aria-hidden="true" />
            </span>
            <span class="flex-1 leading-snug">{{ label }}</span>
            <Checkbox
                v-model="isChecked"
                @update:model-value="optIn"
                :disabled="isLoading"
                inputId="opt_in_whatsapp_newsletter_checkout"
                name="opt_in_whatsapp_newsletter_checkout"
                binary
                class="shrink-0" />
        </label>
    </Transition>
</template>

<style scoped>
.whatsapp-optin {
    animation: whatsapp-optin-glow 1.6s ease-in-out 3;
}

@keyframes whatsapp-optin-glow {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(37, 211, 102, 0);
    }
    50% {
        box-shadow: 0 0 0 4px rgba(37, 211, 102, 0.25), 0 0 16px rgba(37, 211, 102, 0.35);
    }
}

.whatsapp-optin-swap-enter-active,
.whatsapp-optin-swap-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.whatsapp-optin-swap-enter-from,
.whatsapp-optin-swap-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

@media (prefers-reduced-motion: reduce) {
    .whatsapp-optin {
        animation: none;
    }

    .whatsapp-optin-swap-enter-active,
    .whatsapp-optin-swap-leave-active {
        transition: none;
    }
}
</style>
