<script setup lang="ts">
import { inject, ref } from "vue"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faEnvelope } from "@fal"
import { faCheckCircle } from "@fas"
import { getStyles } from "@/Composables/styles"
import { ctrans } from "@/Composables/useTrans"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { hasText, useDialogTemplate } from "@/Components/Websites/WebsiteDialog/Templates/useDialogTemplate"
import type { WebsiteDialogTemplateData } from "@/types/WebsiteDialog"

library.add(faEnvelope, faCheckCircle)

const props = defineProps<{
    dialogData?: WebsiteDialogTemplateData
    isEditable?: boolean
}>()

const { screenType, fields, accent, accentSoft, editable } = useDialogTemplate(props)

const layout = inject<{ iris?: { website?: { id?: number } } }>("layout", {})

const inputEmail = ref("")
const honeypot = ref("")
const isLoading = ref(false)
const state = ref<"" | "success" | "error">("")
const errorMessage = ref("")

const onSubmit = async () => {
    if (props.isEditable || honeypot.value || isLoading.value) {
        return
    }

    isLoading.value = true
    state.value = ""
    errorMessage.value = ""

    if (!layout?.iris?.website?.id) {
        setTimeout(() => {
            inputEmail.value = ""
            state.value = "success"
            isLoading.value = false
        }, 600)
        return
    }

    try {
        await axios.post(window.origin + "/app/webhooks/subscribe-newsletter", { email: inputEmail.value })
        inputEmail.value = ""
        state.value = "success"
    } catch (error: any) {
        const emailErrors = error?.response?.data?.errors?.email
        state.value = "error"
        errorMessage.value = (Array.isArray(emailErrors) ? emailErrors[0] : emailErrors) || ctrans("An error occurred while subscribing.")
    } finally {
        isLoading.value = false
    }
}
</script>

<template>
    <div
        class="relative max-w-[calc(100vw-2rem)] overflow-hidden shadow-2xl"
        :style="getStyles(dialogData?.container_properties, screenType)"
        v-bind="editable('container')"
    >
        <div class="h-1.5 w-full" :style="{ background: accent }" />

        <div class="flex flex-col items-center gap-y-3 px-8 pb-9 pt-8 text-center sm:px-10">
            <div class="flex h-12 w-12 items-center justify-center rounded-full text-xl" :style="{ background: accentSoft, color: accent }">
                <FontAwesomeIcon icon="fal fa-envelope" fixed-width aria-hidden="true" />
            </div>

            <div
                v-if="hasText(fields.eyebrow?.text) || isEditable"
                class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wider"
                :style="{ background: accentSoft, color: accent }"
                v-bind="editable('eyebrow')"
                v-html="fields.eyebrow?.text || '&nbsp;'"
            />

            <div v-if="hasText(fields.title?.text)" class="w-full text-2xl leading-tight sm:text-3xl" v-bind="editable('title')" v-html="fields.title?.text" />

            <div v-if="hasText(fields.description?.text)" class="w-full text-base leading-relaxed opacity-80" v-bind="editable('description')" v-html="fields.description?.text" />

            <div v-if="state === 'success'" class="mt-3 flex flex-col items-center gap-y-2 text-green-600" role="status">
                <FontAwesomeIcon icon="fas fa-check-circle" class="text-4xl" fixed-width aria-hidden="true" />
                <span class="font-medium">{{ fields.subscribe?.success_text || ctrans("You have successfully subscribed!") }}</span>
            </div>

            <form v-else class="mt-3 w-full" v-bind="editable('subscribe')" @submit.prevent="onSubmit">
                <input v-model="honeypot" type="text" class="sr-only" aria-hidden="true" tabindex="-1" autocomplete="off" />

                <div class="flex w-full flex-col gap-2 sm:flex-row">
                    <label class="relative min-w-0 flex-1">
                        <span class="sr-only">{{ ctrans("Email address") }}</span>
                        <FontAwesomeIcon icon="fal fa-envelope" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fixed-width aria-hidden="true" />
                        <input
                            v-model="inputEmail"
                            type="email"
                            required
                            autocomplete="email"
                            :placeholder="fields.subscribe?.placeholder || ctrans('Your email address')"
                            :disabled="isLoading || isEditable"
                            class="w-full rounded-full border-0 bg-white py-3 pl-9 pr-4 text-sm text-gray-800 shadow-sm ring-1 ring-gray-300 placeholder:text-gray-400 focus:ring-2"
                            :style="{ '--tw-ring-color': state === 'error' ? 'rgb(239 68 68)' : undefined }"
                        />
                    </label>

                    <button
                        type="submit"
                        class="inline-flex shrink-0 items-center justify-center gap-x-2 font-semibold shadow-sm transition-transform duration-150 hover:-translate-y-0.5 disabled:opacity-70"
                        :style="getStyles(fields.button?.container?.properties, screenType)"
                        :disabled="isLoading"
                        v-bind="editable('button')"
                    >
                        <LoadingIcon v-if="isLoading" />
                        <span v-html="fields.button?.text || ctrans('Subscribe')" />
                    </button>
                </div>

                <p v-if="state === 'error'" class="mt-2 text-left text-sm text-red-600">{{ errorMessage }}</p>
            </form>

            <div v-if="hasText(fields.note?.text)" class="mt-1 w-full text-xs opacity-60" v-bind="editable('note')" v-html="fields.note?.text" />
        </div>
    </div>
</template>
