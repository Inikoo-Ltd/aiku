<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLink, faRedo, faExclamationTriangle } from '@fal'
import { faSpinnerThird } from '@fad'
import Modal from '@/Components/Utils/Modal.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import Toggle from '@/Components/Pure/Toggle.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'

interface UtmField {
    name: string
    label: string
    hint: string
    placeholder: string
}

interface UtmLink {
    url: string
    utm: Record<string, string>
    roles: string[]
    is_internal: boolean
    is_tracking_redirect: boolean
}

interface UtmSettings {
    is_enabled: boolean
    source: string
    medium: string
    campaign: string
    campaign_id: string
    default_campaign: string
}

const props = defineProps<{
    isOpen: boolean
    utmLinksRoute: routeType
    updateUtmLinkRoute: routeType
    updateUtmSettingsRoute: routeType
}>()

const emits = defineEmits<{
    (e: 'onClose'): void
}>()

const links = ref<UtmLink[]>([])
const fields = ref<UtmField[]>([])
const settings = ref<UtmSettings | null>(null)
const drafts = reactive<Record<string, Record<string, string>>>({})
const selectedUrl = ref<string | null>(null)
const isLoading = ref(false)
const isSaving = ref(false)
const isSavingSettings = ref(false)
const loadError = ref<string | null>(null)

const selectedLink = computed(() => links.value.find(link => link.url === selectedUrl.value) ?? null)
const draft = computed(() => (selectedUrl.value ? drafts[selectedUrl.value] : null))

const emptyDraft = () => Object.fromEntries(fields.value.map(field => [field.name, '']))

const isTagged = (link: UtmLink) => Object.keys(link.utm).length > 0

const overriddenCount = computed(() => links.value.filter(isTagged).length)

const automaticParameters = computed<Record<string, string>>(() => {
    if (!settings.value?.is_enabled || !selectedLink.value?.is_internal) return {}

    return {
        utm_source: settings.value.source,
        utm_medium: settings.value.medium,
        utm_campaign: settings.value.campaign,
        utm_id: settings.value.campaign_id,
        utm_content: selectedLink.value?.roles[0] ?? '',
    }
})

const effectiveParameters = computed<Record<string, string>>(() => {
    const overrides = Object.fromEntries(
        Object.entries(draft.value ?? {}).filter(([, value]) => value)
    )

    return { ...automaticParameters.value, ...overrides }
})

const finalUrl = computed(() => {
    if (!selectedUrl.value) return ''

    const [withoutFragment, fragment] = selectedUrl.value.split('#')
    const [base, query] = withoutFragment.split('?')
    const parts = (query ?? '').split('&').filter(part => part && !part.toLowerCase().startsWith('utm_'))

    for (const field of fields.value) {
        const value = effectiveParameters.value[field.name]
        if (value) parts.push(`${field.name}=${encodeURIComponent(value)}`)
    }

    return base + (parts.length ? '?' + parts.join('&') : '') + (fragment ? '#' + fragment : '')
})

const loadLinks = async () => {
    isLoading.value = true
    loadError.value = null

    try {
        const { data } = await axios.get(route(props.utmLinksRoute.name, props.utmLinksRoute.parameters))
        fields.value = data.fields
        links.value = data.links
        settings.value = data.settings

        for (const link of links.value) {
            drafts[link.url] = { ...emptyDraft(), ...link.utm }
        }

        selectedUrl.value = links.value[0]?.url ?? null
    } catch (error: any) {
        loadError.value = error?.response?.data?.message || trans('Could not read the links of this email')
    } finally {
        isLoading.value = false
    }
}

const saveSettings = async () => {
    if (!settings.value) return

    isSavingSettings.value = true

    try {
        await axios.patch(
            route(props.updateUtmSettingsRoute.name, props.updateUtmSettingsRoute.parameters),
            {
                is_enabled: settings.value.is_enabled,
                source: settings.value.source,
                medium: settings.value.medium,
                campaign: settings.value.campaign,
            }
        )

        notify({
            title: trans('Saved'),
            text: trans('Automatic tagging updated'),
            type: 'success'
        })
    } catch (error: any) {
        notify({
            title: trans('Something went wrong'),
            text: error?.response?.data?.message || trans('Failed to save the automatic tagging'),
            type: 'error'
        })
    } finally {
        isSavingSettings.value = false
    }
}

const saveOverride = async () => {
    if (!selectedUrl.value || !draft.value) return

    isSaving.value = true

    try {
        await axios.patch(
            route(props.updateUtmLinkRoute.name, props.updateUtmLinkRoute.parameters),
            { url: selectedUrl.value, ...draft.value }
        )

        const link = links.value.find(link => link.url === selectedUrl.value)
        if (link) {
            link.utm = Object.fromEntries(Object.entries(draft.value).filter(([, value]) => value))
        }

        notify({
            title: trans('Saved'),
            text: trans('Tracking saved for this link'),
            type: 'success'
        })
    } catch (error: any) {
        notify({
            title: trans('Something went wrong'),
            text: error?.response?.data?.message || trans('Failed to save the tracking of this link'),
            type: 'error'
        })
    } finally {
        isSaving.value = false
    }
}

const placeholderFor = (field: UtmField) => {
    if (!settings.value?.is_enabled || !selectedLink.value?.is_internal) return ''
    if (field.name === 'utm_content') return selectedLink.value?.roles[0] ?? ''
    if (field.name === 'utm_term') return ''

    return field.placeholder
}

const shortUrl = (url: string) => url.replace(/^https?:\/\//, '')

watch(() => props.isOpen, isOpen => {
    if (isOpen) loadLinks()
})
</script>

<template>
    <Modal :isOpen="isOpen" @onClose="emits('onClose')" width="w-full max-w-4xl">
        <div class="border-b border-gray-200 pb-4">
            <div class="flex items-start justify-between gap-x-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ trans('Link tracking') }}</h2>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ trans('Links to your own websites are tagged automatically, so your analytics can tell which email and which element brought a visitor. Links to other sites are left untouched.') }}
                    </p>
                </div>
                <button v-if="links.length" type="button" @click="loadLinks" :disabled="isLoading"
                    class="shrink-0 text-sm text-gray-500 hover:text-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded px-2 py-1 disabled:opacity-50">
                    <FontAwesomeIcon :icon="faRedo" fixed-width aria-hidden="true" />
                    {{ trans('Reload links') }}
                </button>
            </div>
        </div>

        <div v-if="isLoading" class="py-16 text-center text-gray-500">
            <FontAwesomeIcon :icon="faSpinnerThird" spin fixed-width aria-hidden="true" />
            <p class="mt-2 text-sm">{{ trans('Reading the links of your saved email') }}</p>
        </div>

        <div v-else-if="loadError" class="py-16 text-center">
            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-amber-500 text-2xl" fixed-width aria-hidden="true" />
            <p class="mt-2 text-sm text-gray-700">{{ loadError }}</p>
            <Button class="mt-4" type="tertiary" :label="trans('Try again')" @click="loadLinks" />
        </div>

        <template v-else>
            <div v-if="settings" class="py-4 border-b border-gray-200">
                <div class="flex items-start justify-between gap-x-4">
                    <div>
                        <div class="text-sm font-medium text-gray-800">{{ trans('Tag links automatically') }}</div>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ trans('Turn this off to send your links untouched, apart from the ones you set by hand below.') }}
                        </p>
                    </div>
                    <Toggle v-model="settings.is_enabled" size="md" @update:modelValue="saveSettings" />
                </div>

                <div v-if="settings.is_enabled" class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label for="utm-settings-source" class="block text-sm font-medium text-gray-800">{{ trans('Source') }}</label>
                        <div class="text-xs text-gray-500 mb-1">{{ trans('Same for every newsletter') }}</div>
                        <PureInput inputName="utm-settings-source" v-model="settings.source" :maxLength="255" @blur="saveSettings" />
                    </div>
                    <div>
                        <label for="utm-settings-medium" class="block text-sm font-medium text-gray-800">{{ trans('Medium') }}</label>
                        <div class="text-xs text-gray-500 mb-1">{{ trans('Same for every email you send') }}</div>
                        <PureInput inputName="utm-settings-medium" v-model="settings.medium" :maxLength="255" @blur="saveSettings" />
                    </div>
                    <div>
                        <label for="utm-settings-campaign" class="block text-sm font-medium text-gray-800">{{ trans('Campaign') }}</label>
                        <div class="text-xs text-gray-500 mb-1">{{ trans('Name of this send, taken from the subject. Keep it short, it is added to every link') }}</div>
                        <PureInput inputName="utm-settings-campaign" v-model="settings.campaign"
                            :placeholder="settings.default_campaign" :maxLength="255" @blur="saveSettings" />
                    </div>
                </div>

                <p v-if="settings.is_enabled" class="text-xs text-gray-500 mt-3">
                    {{ trans('Campaign ID is the date this email goes out (:date), and content is filled per element, so a click on the hero image reads differently from a click on a button.', { date: settings.campaign_id }) }}
                </p>
            </div>

            <div v-if="!links.length" class="py-16 text-center">
                <FontAwesomeIcon :icon="faLink" class="text-gray-300 text-2xl" fixed-width aria-hidden="true" />
                <p class="mt-2 text-sm text-gray-700">{{ trans('This email has no links yet') }}</p>
                <p class="text-sm text-gray-500">
                    {{ trans('Add a link to a button, image or text in the editor, then check again.') }}
                </p>
                <Button class="mt-4" type="tertiary" :label="trans('Check again')" @click="loadLinks" />
            </div>

            <div v-else class="flex flex-col md:flex-row gap-6 pt-4">
                <div class="w-full md:w-72 shrink-0">
                    <div class="text-xs uppercase text-gray-500 mb-2">
                        {{ trans(':count links, :overridden set by hand', { count: links.length, overridden: overriddenCount }) }}
                    </div>
                    <div class="max-h-40 md:max-h-96 overflow-y-auto pr-1 space-y-1">
                        <button v-for="link in links" :key="link.url" type="button" @click="selectedUrl = link.url"
                            class="w-full text-left px-3 py-2 rounded-md text-sm truncate focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            :class="selectedUrl === link.url ? 'bg-gray-800 text-white' : 'text-gray-700 hover:bg-gray-100'"
                            :title="link.url">
                            <span v-if="!link.is_internal"
                                class="mr-1.5 text-[10px] uppercase rounded px-1 py-0.5"
                                :class="selectedUrl === link.url ? 'bg-gray-600 text-gray-100' : 'bg-gray-100 text-gray-600'">{{ trans('ext') }}</span>
                            <span v-if="isTagged(link)" class="mr-1.5 text-xs"
                                :class="selectedUrl === link.url ? 'text-emerald-300' : 'text-emerald-600'"
                                :aria-label="trans('Set by hand')">●</span>
                            {{ shortUrl(link.url) }}
                        </button>
                    </div>
                </div>

                <div v-if="draft" class="flex-1 border-t md:border-t-0 md:border-l border-gray-200 pt-4 md:pt-0 md:pl-6">
                    <div v-if="selectedLink?.roles.length" class="mb-3 text-xs text-gray-600">
                        {{ trans('Used by') }}:
                        <span v-for="role in selectedLink.roles" :key="role"
                            class="inline-block bg-gray-100 text-gray-700 rounded px-1.5 py-0.5 mr-1 font-mono">{{ role }}</span>
                    </div>

                    <div v-if="selectedLink && !selectedLink.is_internal"
                        class="mb-3 text-xs text-gray-700 bg-gray-50 border border-gray-200 rounded-md p-2">
                        {{ trans('This link points outside your own websites, so it is left alone by the automatic tagging. Anything you type here is still applied to it.') }}
                    </div>

                    <div v-if="selectedLink?.is_tracking_redirect"
                        class="mb-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-md p-2">
                        <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
                        {{ trans('This link is already a tracking redirect, so the parameters stop there instead of reaching your website. Replace it with the real address.') }}
                    </div>

                    <p class="text-xs text-gray-500 mb-3">
                        {{ trans('Leave a field empty to keep the automatic value. Anything you type here wins for this link.') }}
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div v-for="field in fields" :key="field.name">
                            <label :for="`utm-${field.name}`" class="block text-sm font-medium text-gray-800">
                                {{ field.label }}
                            </label>
                            <div class="text-xs text-gray-500 mb-1">{{ field.hint }}</div>
                            <PureInput :inputName="`utm-${field.name}`" v-model="draft[field.name]"
                                :placeholder="placeholderFor(field)" :maxLength="255" />
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="text-xs uppercase text-gray-500 mb-1">{{ trans('Link that will be sent') }}</div>
                        <p class="text-xs text-gray-700 break-all bg-gray-50 border border-gray-200 rounded-md p-2">
                            {{ finalUrl }}
                        </p>
                    </div>

                    <div class="flex justify-end mt-4">
                        <Button type="save" :loading="isSaving" :disabled="isSaving" @click="saveOverride" />
                    </div>
                </div>
            </div>
        </template>
    </Modal>
</template>
