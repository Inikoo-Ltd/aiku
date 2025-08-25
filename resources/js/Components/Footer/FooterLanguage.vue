<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 04 Sep 2023 10:22:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
// This file is used on CustomerApp, PublicApp

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLanguage } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faLanguage, faSpinnerThird)
import FooterTab from '@/Components/Footer/FooterTab.vue'
import { trans, loadLanguageAsync } from 'laravel-vue-i18n'
import { useForm } from '@inertiajs/vue3'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import type { Language } from '@/types/Locale'
import LoadingText from '@/Components/Utils/LoadingText.vue'
import { inject } from 'vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

const form = useForm<{
    language_id: number | null
}>({
    language_id: null,
})

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', layoutStructure)

const onSelectLanguage = (language: Language) => {
    const routeUpdate = layout.app.name === 'Aiku' ? 'grp.models.profile.update' : 'retina.models.profile.update'

    if(form.language_id != language.id) {
        form.language_id = language.id
        form.patch(route(routeUpdate), {
            preserveScroll: true,
            onSuccess: () => (
                locale.language = language,
                loadLanguageAsync(language.code)
            )
        })
    }
    
}

</script>

<template>
    <Popover v-slot="{ open }" class="relative h-full">
        <PopoverButton :class="open ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 text-gray-200'"
            class="group inline-flex items-center px-3 h-full font-medium ">
            <!-- Label: Loading on select language -->
            <template v-if="form.processing">
                <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin text-xs' fixed-width aria-hidden='true' />
                <LoadingText class="h-full font-extralight text-xs flex items-center gap-x-1 leading-none" />
            </template>

            <!-- Label: Language -->
            <template v-else>
                <FontAwesomeIcon icon='fal fa-language' class='text-xs' fixed-width aria-hidden='true' />
                <div class="ml-1 h-full font-extralight text-xs flex items-center gap-x-1 leading-none text-gray-500">
                    {{ locale.language.name }}
                </div>
            </template>
        </PopoverButton>

        <!-- Section: Popup -->
        <transition name="headlessui">
            <PopoverPanel class="absolute bottom-full right-0 z-10 sm:px-0">
                <FooterTab tabName="language" :header="false">
                    <template #header>
                        <div class="overflow-hidden bg-gray-800 h-6 flex justify-start items-center gap-x-1 px-2 border-b border-gray-500"
                            :style="{
                                // background: `linear-gradient(to right, color-mix(in srgb, ${layout?.app?.theme[0]} 30%, black), color-mix(in srgb, ${layout?.app?.theme[0]} 20%, white))`
                            }"
                        >
                            <FontAwesomeIcon icon='fal fa-language' class='text-xs' fixed-width aria-hidden='true' />
                            <div class="relative">
                                <Transition name="spin-to-down">
                                    <div :key="locale.language.name" class="font-extralight text-xs flex items-center gap-x-1 leading-none">
                                        {{ locale.language.name }}
                                    </div>
                                </Transition>
                            </div>
                        </div>
                    </template>

                    <template #default>
                        <form v-if="Object.keys(locale.languageOptions).length > 0"
                            v-for="(language, index) in locale.languageOptions"
                            @submit.prevent="() => onSelectLanguage(language)"
                            :class="[ language.id == locale.language.id ? 'bg-gray-700 text-[#FDD835]' : 'hover:bg-white/20 ', 'grid ']"
                        >
                            <button
                                type="submit"
                                class="py-1.5">
                                {{ language.name }}
                            </button>
                        </form>
                        <div v-else class="grid pt-2.5 pb-1.5">{{ trans('Nothing to show here') }}</div>
                    </template>
                </FooterTab>
            </PopoverPanel>
        </transition>
    </Popover>
</template>
