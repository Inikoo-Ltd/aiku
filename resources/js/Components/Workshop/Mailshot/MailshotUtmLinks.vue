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
}

const props = defineProps<{
    isOpen: boolean
    utmLinksRoute: routeType
    updateUtmLinkRoute: routeType
}>()

const emits = defineEmits<{
    (e: 'onClose'): void
}>()

const links = ref<UtmLink[]>([])
const fields = ref<UtmField[]>([])
const drafts = reactive<Record<string, Record<string, string>>>({})
const selectedUrl = ref<string | null>(null)
const isLoading = ref(false)
const isSaving = ref(false)
const loadError = ref<string | null>(null)

const draft = computed(() => (selectedUrl.value ? drafts[selectedUrl.value] : null))

const emptyDraft = () =>
    Object.fromEntries(fields.value.map(field => [field.name, '']))

const isTagged = (link: UtmLink) => Object.keys(link.utm).length > 0

const taggedCount = computed(() => links.value.filter(isTagged).length)

const finalUrl = computed(() => {
    if (!selectedUrl.value || !draft.value) return ''

    try {
        const url = new URL(selectedUrl.value)
        for (const [name, value] of Object.entries(draft.value)) {
            if (value) url.searchParams.set(name, value)
        }
        return url.toString()
    } catch {
        return selectedUrl.value
    }
})

const loadLinks = async () => {
    isLoading.value = true
    loadError.value = null

    try {
        const { data } = await axios.get(route(props.utmLinksRoute.name, props.utmLinksRoute.parameters))
        fields.value = data.fields
        links.value = data.links

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

const save = async () => {
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

const shortUrl = (url: string) => url.replace(/^https?:\/\//, '')

watch(() => props.isOpen, isOpen => {
    if (isOpen) loadLinks()
})
</script>

<template>
    <Modal :isOpen="isOpen" @onClose="emits('onClose')" width="w-full max-w-4xl">
        <div class="flex items-start justify-between gap-x-4 border-b border-gray-200 pb-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ trans('Link tracking') }}</h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ trans('Tag the links of this email so you can tell in your analytics which link brought a visitor.') }}
                </p>
            </div>
            <button v-if="links.length" type="button" @click="loadLinks" :disabled="isLoading"
                class="shrink-0 text-sm text-gray-500 hover:text-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded px-2 py-1 disabled:opacity-50">
                <FontAwesomeIcon :icon="faRedo" fixed-width aria-hidden="true" />
                {{ trans('Reload links') }}
            </button>
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

        <div v-else-if="!links.length" class="py-16 text-center">
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
                    {{ trans(':tagged of :total links tagged', { tagged: taggedCount, total: links.length }) }}
                </div>
                <div class="max-h-40 md:max-h-96 overflow-y-auto pr-1 space-y-1">
                    <button v-for="link in links" :key="link.url" type="button" @click="selectedUrl = link.url"
                        class="w-full text-left px-3 py-2 rounded-md text-sm truncate focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        :class="selectedUrl === link.url ? 'bg-gray-800 text-white' : 'text-gray-700 hover:bg-gray-100'"
                        :title="link.url">
                        <span v-if="isTagged(link)" class="mr-1.5 text-xs"
                            :class="selectedUrl === link.url ? 'text-emerald-300' : 'text-emerald-600'"
                            :aria-label="trans('Tagged')">●</span>
                        {{ shortUrl(link.url) }}
                    </button>
                </div>
            </div>

            <div v-if="draft" class="flex-1 border-t md:border-t-0 md:border-l border-gray-200 pt-4 md:pt-0 md:pl-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div v-for="field in fields" :key="field.name">
                        <label :for="`utm-${field.name}`" class="block text-sm font-medium text-gray-800">
                            {{ field.label }}
                        </label>
                        <div class="text-xs text-gray-500 mb-1">{{ field.hint }}</div>
                        <PureInput :inputName="`utm-${field.name}`" v-model="draft[field.name]"
                            :placeholder="field.placeholder" :maxLength="255" />
                    </div>
                </div>

                <div class="mt-4">
                    <div class="text-xs uppercase text-gray-500 mb-1">{{ trans('Link that will be sent') }}</div>
                    <p class="text-xs text-gray-700 break-all bg-gray-50 border border-gray-200 rounded-md p-2">
                        {{ finalUrl }}
                    </p>
                </div>

                <div class="flex justify-end mt-4">
                    <Button type="save" :loading="isSaving" :disabled="isSaving" @click="save" />
                </div>
            </div>
        </div>
    </Modal>
</template>
