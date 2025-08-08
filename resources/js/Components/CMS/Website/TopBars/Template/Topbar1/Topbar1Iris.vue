<script setup lang="ts">
import { inject, ref } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus, faLanguage } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { getStyles } from "@/Composables/styles";
import { checkVisible, textReplaceVariables } from "@/Composables/Workshop";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { router, useForm } from '@inertiajs/vue3'
import Button from "@/Components/Elements/Buttons/Button.vue";
import LoadingText from '@/Components/Utils/LoadingText.vue'
import type { Language } from '@/types/Locale'
import { trans, loadLanguageAsync } from 'laravel-vue-i18n'
import { notify } from "@kyvg/vue3-notification"

library.add(faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus, faLanguage);

const props = defineProps<{
  screenType: 'desktop' | 'mobile' | 'tablet'
}>()

interface ModelTopbar1 {
  profile: {
    text: string
    visible: string
  };
  favourite: {
    text: string
    visible: string
  };
  cart: {
    text: string
    visible: string
  };
  register: {
    text: string
    visible: string
  };
  logout: {
    text: string
    visible: string
  };
  login: {
    text: string
    visible: string
  };
  greeting: {
    text: string
  };
  main_title: {
    text: string
    visible: string
  };
  container: {
    properties: {
      color: {}
      background: {}
    }
  };
}

const model = defineModel<ModelTopbar1>();
const isLoggedIn = inject("isPreviewLoggedIn", false);
const layout = inject("layout", {});
// const locale = inject('locale', aikuLocaleStructure)


// Method: generate url for Login
const urlLoginWithRedirect = () => {
  if (route()?.current() !== "retina.login.show" && route()?.current() !== "retina.register") {
    return `/app/login?ref=${encodeURIComponent(window?.location.pathname)}${window?.location.search ? encodeURIComponent(window?.location.search) : ""
      }`
  } else {
    return "/app/login"
  }
}

const form = useForm<{
  locale: string | null
}>({
  locale: null,
})


// Section: change language
console.log('vvvvv', layout.iris.website_i18n)
// const userLocale = layout.iris.locale
const onSelectLanguage = (languageCode: string) => {

    let routeToUpdateLanguage = {}
    if (route().current()?.startsWith('retina')) {
        routeToUpdateLanguage = {
            name: 'retina.locale.update',
            parameters: {
                locale: languageCode
            }
        }
    } else {
        routeToUpdateLanguage = {
            name: 'iris.models.locale.update',
            parameters: {
                locale: languageCode
            }
        }
    }
    // const routeUpdate = layout?.iris?.is_logged_in ? 'retina.models.profile.update' : 'retina.models.profile.update'

    // console.log('loaa', form)
    // console.log('loaa11', languageCode)

    // Section: Submit
    // console.log('44444444444444', routeToUpdateLanguage.name, route(routeToUpdateLanguage.name, routeToUpdateLanguage.parameters))
    router.patch(
        route(routeToUpdateLanguage.name, routeToUpdateLanguage.parameters),
        {
            locale: languageCode
        },
        {
            preserveScroll: true,
            // preserveState: true,
            onStart: () => { 
                // console.log('start 222222222222222222')
                // isLoading.value = true
            },
            onSuccess: () => {
                // console.log('succcccccceeeessssssss')
                layout.iris.locale = languageCode
                loadLanguageAsync(languageCode)
                notify({
                    title: trans("Success"),
                    text: trans("Successfully change the language. Please refresh the page."),
                    type: "success"
                })
            },
            onError: (errors) => {
                // console.log('gggg', errors)
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to set the language, try again."),
                    type: "error"
                })
            },
            onFinish: () => {
                // console.log('000000000000000000000000000')
                // isLoading.value = false
            },
        }
    )
    
    // if(form.locale != languageCode) {
    //     form.locale = languageCode
    //     form.patch(route(routeToUpdateLanguage.name, routeToUpdateLanguage.parameters), {
    //         preserveScroll: true,
    //         onSuccess: () => (
    //             // locale.language = language,
    //             loadLanguageAsync(languageCode)
    //         )
    //     })
    // }
}

// console.log(locale)
</script>

<template>
  <div></div>
  <div id="top_bar_1_iris" class="py-1 px-4 flex flex-col md:flex-row md:justify-between gap-x-4" :style="{
    ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
    margin: 0,
    ...getStyles(model.container?.properties, screenType)
  }">
    <div class="flex-shrink flex flex-col md:flex-row items-center justify-between w-full ">
      <!-- Section: Main title -->
      <div
        v-if="checkVisible(model?.main_title?.visible || null, isLoggedIn) && textReplaceVariables(model?.main_title?.text, layout.iris_variables)"
        class="text-center flex items-center"
        v-html="textReplaceVariables(model?.main_title?.text, layout.iris_variables)" />
    </div>

    <Popover v-if="layout.app.environment === 'local'"  class="relative h-full">
      <PopoverButton aria-label="Language Selector">
        <div v-if="form.processing">
          <FontAwesomeIcon icon="fad fa-spinner-third" class="animate-spin text-xs" fixed-width aria-hidden="true" />
          <LoadingText class="h-full font-extralight text-xs flex items-center gap-x-1 leading-none" />
        </div>

        <div v-else class="text-yellow-600 hover:text-yellow-400 flex gap-x-2 items-center py-1 hover:underline">
            <FontAwesomeIcon icon="fal fa-language" class="" fixed-width aria-hidden="true" />
            {{ layout.iris.locale }}
        </div>
      </PopoverButton>

      <!-- Panel -->
      <transition name="headlessui">
        <PopoverPanel
          class="absolute top-full mt-1 right-0 z-10 w-48 bg-white text-gray-900 rounded shadow-lg ring-1 ring-black/10 overflow-hidden">
          <div tabName="language" :header="false">

            <!-- Language Options -->
            <div>
              <div v-if="Object.keys(layout.iris.website_i18n?.language_options).length > 0" class="flex flex-col">
                <!-- Language: system -->
                <button key="website_language" type="button"
                    @click="onSelectLanguage(layout.iris.website_i18n?.language?.code)" :class="[
                        'w-full text-left px-3 py-2 text-sm transition rounded-none border-b border-gray-300',
                        layout.iris.website_i18n?.language?.code === layout.iris.locale
                            ? 'bg-gray-200 text-blue-600 font-semibold'
                            : 'hover:bg-gray-100 text-gray-800'
                    ]">
                    {{ layout.iris.website_i18n?.language?.name }}
                </button>

                <hr class="border-b border-gray-200 !my-2" />

                <!-- Language: options list -->
                <button v-for="(language, index) in layout.iris.website_i18n?.language_options" :key="language.id" type="button"
                    @click="onSelectLanguage(language.code)" :class="[
                        'w-full text-left px-3 py-2 text-sm transition rounded-none',
                        language.code === layout.iris.locale
                        ? 'bg-gray-200 text-blue-600 font-semibold'
                        : 'hover:bg-gray-100 text-gray-800'
                    ]">
                    {{ language.name }}
                    <!-- {{ language.code }}+
                {{ layout.iris.locale }} -->
                </button>

              </div>

              <div v-else class="text-xs text-gray-400 py-2 px-3">
                {{ trans('Nothing to show here') }}
              </div>

            </div>
          </div>
        </PopoverPanel>
      </transition>
    </Popover>


    <div class="action_buttons flex justify-between md:justify-start items-center gap-x-1 flex-wrap md:flex-nowrap">

      <!-- Section: My account -->
      <ButtonWithLink
        v-if="checkVisible(model?.profile?.visible || null, isLoggedIn) && layout.retina?.type == 'dropshipping'"
        v-tooltip="trans('My account')" url="/app/dashboard" :label="trans('My account')">
      </ButtonWithLink>

      <!-- Section: Profile -->
      <ButtonWithLink v-if="checkVisible(model?.profile?.visible || null, isLoggedIn)" v-tooltip="trans('Profile')"
        url="/app/profile" icon="fal fa-user">
        <template #label>
          <span v-html="textReplaceVariables(model?.profile?.text, layout.iris_variables)" />
        </template>
      </ButtonWithLink>

      <!-- Section: Favourite -->
      <ButtonWithLink
        v-if="checkVisible(model?.favourite?.visible || null, isLoggedIn) && layout.retina?.type !== 'dropshipping'"
        v-tooltip="trans('Favourites')" url="/app/favourites" icon="fal fa-heart">
        <template #label>
          <span v-html="textReplaceVariables(model?.favourite?.text, layout.iris_variables)" />
        </template>
      </ButtonWithLink>


      <!-- Section: Basket -->
      <ButtonWithLink v-if="checkVisible(model?.cart?.visible || null, isLoggedIn) && layout.retina?.type == 'b2b'"
        url="/app/basket" icon="fal fa-shopping-cart">
        <template #label>
          <span v-html="textReplaceVariables(model?.cart?.text, layout.iris_variables)" />
        </template>
      </ButtonWithLink>

      <!-- Section: Register -->
      <ButtonWithLink v-if="checkVisible(model?.register?.visible || null, isLoggedIn)" url="/app/register"
        icon="fal fa-user-plus" type="transparent">
        <template #icon>
          <FontAwesomeIcon icon="fal fa-user-plus" class="text-white" fixed-width aria-hidden="true" />
        </template>

        <template #label>
          <span v-html="textReplaceVariables(model?.register.text, layout.iris_variables)" class="text-white" />
        </template>
      </ButtonWithLink>

      <!-- Section: Login -->
      <ButtonWithLink v-if="checkVisible(model?.login?.visible || null, isLoggedIn)" :url="urlLoginWithRedirect()"
        icon="fal fa-sign-in">
        <template #label>
          <span v-html="textReplaceVariables(model?.login?.text, layout.iris_variables)" />
        </template>
      </ButtonWithLink>

      <!-- Section: Logout -->
      <ButtonWithLink v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)" url="/app/logout" method="post"
        :data="{}" icon="fal fa-sign-out">
        <template #label>
          <span v-html="textReplaceVariables(model?.logout?.text, layout.iris_variables)" />
        </template>
      </ButtonWithLink>
    </div>
  </div>
</template>
