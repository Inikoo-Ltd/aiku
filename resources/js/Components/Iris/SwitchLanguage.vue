<script setup lang="ts">
// import { Popover, PopoverButton, PopoverPanel } from "@headlessui/vue"
import { Popover } from "primevue"
import { router } from "@inertiajs/vue3"
import { trans, loadLanguageAsync } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { computed, inject, ref } from "vue"
import LoadingIcon from "../Utils/LoadingIcon.vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faLanguage } from "@fal"
import { faLaptopCode } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
library.add(faLanguage, faLaptopCode)

const layout = inject("layout", retinaLayoutStructure)

// const userLocale = layout.iris.locale
const isLoadingChangeLanguage = ref<string | null>(null)
const onSelectLanguage = (languageCode: string, type: string) => {

    let routeToUpdateLanguage = {}
    if (route().current()?.startsWith("retina")) {
        routeToUpdateLanguage = {
            name: "retina.models.locale.update",
            parameters: {
                locale: languageCode
            }
        }
    } else {
        routeToUpdateLanguage = {
            name: "iris.locale.update",
            parameters: {
                locale: languageCode
            }
        }
    }

    router.patch(
        route(routeToUpdateLanguage.name, routeToUpdateLanguage.parameters),
        {
            locale: languageCode
        },
        {
            preserveScroll: true,
            onStart: () => {
                isLoadingChangeLanguage.value = `${type}${languageCode}`
            },
            onSuccess: () => {
                layout.iris.locale = languageCode
                loadLanguageAsync(languageCode)
            },
            onError: (errors) => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to set the language, try again."),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingChangeLanguage.value = null
            }
        }
    )
}

const _popover = ref(null)
const languageOptionsArray = Object.values(layout.iris.website_i18n?.language_options)
const compFilterLanguageOptions = computed(() => {
    return languageOptionsArray.filter(language => language.code !== layout.iris.website_i18n?.shop_language?.code)
})
</script>

<template>
    <div v-if="layout.app.environment === 'local' && Object.keys(layout.iris.website_i18n?.language_options).length > 0">
        <Button
            @click="(e) => _popover?.toggle(e)"
            v-tooltip="trans('Change language of the website')"
            icon="fal fa-language"
            class="text-white"
            xlabel="Object.values(layout.iris.website_i18n?.language_options || {})?.find(language => language.code === layout.iris.locale)?.name"
            :loading="!!isLoadingChangeLanguage"
            type="transparent"
        >
            <template #label>
                {{ Object.values(layout.iris.website_i18n?.language_options || {})?.find(language => language.code === layout.iris.website_i18n.current_language?.code)?.name }}
                <img class="inline pr-1 pl-1 h-[1em]" :src="`/flags/${layout.iris.website_i18n.current_language?.flag}`" xalt="language.code"   xtitle='capitalize(countryName)'  />
            </template>
        </Button>
        <Popover ref="_popover">
            <div>
                <!-- Language Options -->
                <div v-if="compFilterLanguageOptions.length > 0" class="flex flex-col">
                    <!-- Language: system -->
                    <button key="website_language" type="button"
                        @click="onSelectLanguage(layout.iris.website_i18n?.shop_language?.code, 'system')"
                        class="flex items-center gap-x-1 w-full text-left px-3 py-2 text-sm transition rounded-none border-b border-gray-300"
                        :class="[
                            layout.iris.website_i18n?.shop_language?.code === layout.iris.website_i18n?.current_language?.code
                                ? 'bg-gray-200 text-blue-600 font-semibold'
                                : 'hover:bg-gray-100 text-gray-700'
                        ]"
                    >
                        {{ layout.iris.website_i18n?.shop_language?.name }}
                        <FontAwesomeIcon v-tooltip="trans('Default language by system')" icon="fas fa-laptop-code" class="text-gray-400" fixed-width aria-hidden="true" />
                        <img class="inline pr-1 pl-1 h-[1em]" :src="`/flags/${layout.iris.website_i18n.current_language?.flag}`" xalt="language.code"   xtitle='capitalize(countryName)'  />
                        <LoadingIcon v-if="isLoadingChangeLanguage == `system${layout.iris.website_i18n?.shop_language?.code}`" />
                    </button>

                    <hr class="border-b border-gray-200 !my-2" />

                    <!-- Language: options list -->
                    <button
                        v-for="(language, index) in compFilterLanguageOptions"
                        :key="language.id"
                        type="button"
                        @click="onSelectLanguage(language.code, 'option')" :class="[
                        'w-full text-left px-3 py-2 text-sm transition rounded-none',
                        language.code === layout.iris.website_i18n?.current_language?.code
                        ? 'bg-gray-200 text-blue-600 font-semibold'
                        : 'hover:bg-gray-100 text-gray-700'
                    ]">
                        {{ language.name }}
                        <img class="inline pr-1 pl-1 h-[1em]" :src="`/flags/${language.flag}`" xalt="language.code"   xtitle='capitalize(countryName)'  />
                        <LoadingIcon v-if="isLoadingChangeLanguage == `option${language.code}`" />
                    </button>
                </div>

                <div v-else class="text-xs text-gray-400 py-2 px-3">
                    {{ trans("Nothing to show here") }}
                </div>

            </div>
        </Popover>
    </div>
</template>