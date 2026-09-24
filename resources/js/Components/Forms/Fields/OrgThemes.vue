<script setup lang='ts'>
import { computed, inject } from 'vue'
import { Switch } from '@headlessui/vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faTimes } from '@fal'
import ColorPicker from '@/Components/Utils/ColorPicker.vue'
import type { ColorValue } from '@/Components/Utils/colorValue'
import { buildNavigationTheme } from '@/Composables/useNavigationTheme'
import { ctrans } from '@/Composables/useTrans'
import { layoutStructure } from '@/Composables/useLayoutStructure'

library.add(faTimes)

type OrganisationColour = {
    organisation_id: number
    colour: string
}

const props = defineProps<{
    form: any
    fieldName: string
    options?: {
        organisations?: {
            id: number
            slug: string
            code: string
            label: string
        }[]
    }
    fieldData: {}
}>()

const organisations = computed(() => props.options?.organisations ?? [])

const value = computed<{ enabled: boolean, themes: OrganisationColour[] }>(() => ({
    enabled: !!props.form[props.fieldName]?.enabled,
    themes: props.form[props.fieldName]?.themes ?? [],
}))

const layout = inject('layout', layoutStructure)

const slugOf = (organisationId: number) => organisations.value.find(organisation => organisation.id === organisationId)?.slug

/*
 * The colours reach the app in a first load only prop, so without this the left navigation would
 * keep the colours it was given at page load until the next full reload.
 */
const applyToLayout = (enabled: boolean, themes: OrganisationColour[]) => {
    const colours: { [key: string]: string } = {}

    if (enabled) {
        themes.forEach(organisationColour => {
            const slug = slugOf(organisationColour.organisation_id)

            if (slug) {
                colours[slug] = organisationColour.colour
            }
        })
    }

    layout.app.organisation_colours = colours
}

const setValue = (enabled: boolean, themes: OrganisationColour[]) => {
    props.form[props.fieldName] = { enabled, themes }
    applyToLayout(enabled, themes)
}

const isEnabled = computed({
    get: () => value.value.enabled,
    set: (enabled: boolean) => setValue(enabled, value.value.themes),
})

const colourOf = (organisationId: number) => {
    return value.value.themes.find(organisationColour => organisationColour.organisation_id === organisationId)?.colour
}

const navigationTheme = (organisationId: number) => buildNavigationTheme(colourOf(organisationId))

const setColour = (organisationId: number, colour: string | null) => {
    const themes = value.value.themes.filter(organisationColour => organisationColour.organisation_id !== organisationId)

    if (colour) {
        themes.push({ organisation_id: organisationId, colour })
    }

    setValue(value.value.enabled, themes)
}

const onChangeColour = (organisationId: number, picked: ColorValue) => {
    const colour = picked.hex?.slice(0, 7).toLowerCase()

    if (colour && /^#[0-9a-f]{6}$/.test(colour)) {
        setColour(organisationId, colour)
    }
}
</script>

<template>
    <div class="relative w-full">
        <div class="flex items-start gap-x-3">
            <Switch
                v-model="isEnabled"
                class="mt-0.5 relative inline-flex h-6 w-12 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                :class="isEnabled ? 'bg-indigo-500' : 'bg-indigo-100'"
            >
                <span
                    aria-hidden="true"
                    :class="isEnabled ? 'translate-x-6 bg-white' : 'translate-x-0 bg-gray-50'"
                    class="pointer-events-none h-full w-1/2 transform rounded-full shadow-lg ring-0 transition"
                />
            </Switch>

            <div class="text-sm text-gray-500">
                {{ ctrans('Give the left navigation a different colour for each organisation') }}
            </div>
        </div>

        <div v-if="isEnabled" class="mt-4 max-w-xl divide-y divide-gray-100 border-t border-gray-100">
            <div v-if="!organisations.length" class="py-3 text-sm text-gray-500">
                {{ ctrans('You have access to no organisation yet') }}
            </div>

            <div
                v-for="organisation in organisations"
                :key="organisation.id"
                class="flex items-center gap-x-3 py-2"
            >
                <ColorPicker
                    :color="colourOf(organisation.id) ?? '#e5e7eb'"
                    @changeColor="(picked) => onChangeColour(organisation.id, picked)"
                >
                    <template #button>
                        <div
                            class="h-9 w-16 flex items-center justify-center rounded ring-1 ring-gray-300 hover:ring-2 hover:ring-gray-500 cursor-pointer text-[11px] font-semibold"
                            :style="{
                                backgroundColor: navigationTheme(organisation.id)?.[0] ?? '#f3f4f6',
                                color: navigationTheme(organisation.id)?.[1] ?? '#9ca3af',
                            }"
                        >
                            {{ organisation.code }}
                        </div>
                    </template>
                </ColorPicker>

                <div class="flex-1 text-sm text-gray-700">
                    {{ organisation.label }}
                </div>

                <button
                    v-if="colourOf(organisation.id)"
                    type="button"
                    class="text-xs text-red-400 hover:text-red-600"
                    @click="() => setColour(organisation.id, null)"
                >
                    <FontAwesomeIcon icon="fal fa-times" class="mr-1" fixed-width aria-hidden="true" />
                    {{ ctrans('Remove') }}
                </button>
                <span v-else class="text-xs text-gray-400 italic">
                    {{ ctrans('Same as theme colour') }}
                </span>
            </div>
        </div>
    </div>
</template>
