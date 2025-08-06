<script setup lang="ts">
import { inject } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus, faLanguage } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { getStyles } from "@/Composables/styles";
import { checkVisible, textReplaceVariables } from "@/Composables/Workshop";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { useForm } from '@inertiajs/vue3'
import Button from "@/Components/Elements/Buttons/Button.vue";
import LoadingText from '@/Components/Utils/LoadingText.vue'
import type { Language } from '@/types/Locale'
import { trans, loadLanguageAsync } from 'laravel-vue-i18n'

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
const locale = inject('locale', aikuLocaleStructure)


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
  language_id: number | null
}>({
  language_id: null,
})


const onSelectLanguage = (language: Language) => {
     const routeUpdate = layout?.iris?.is_logged_in ? 'retina.models.profile.update' : 'retina.models.profile.update'
 
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

console.log(locale)
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

    <Popover v-if="layout.app.environment === 'local'"  v-slot="{ open }" class="relative h-full">
      <PopoverButton aria-label="Language Selector">
        <template v-if="form.processing">
          <FontAwesomeIcon icon="fad fa-spinner-third" class="animate-spin text-xs" fixed-width aria-hidden="true" />
          <LoadingText class="h-full font-extralight text-xs flex items-center gap-x-1 leading-none" />
        </template>
        <template v-else>
          <Button type="transparent">
            <template #icon>
              <FontAwesomeIcon :icon="faLanguage" class="text-white" fixed-width aria-hidden="true" />
            </template>

            <template #label>
              <span class="text-white">{{ locale.language.name }}</span>
            </template>
          </Button>
        </template>
      </PopoverButton>

      <!-- Panel -->
      <transition name="headlessui">
        <PopoverPanel
          class="absolute top-full mt-1 right-0 z-10 w-48 bg-white text-gray-900 rounded shadow-lg ring-1 ring-black/10 overflow-hidden">
          <FooterTab tabName="language" :header="false">
            <!-- Header -->
            <template #header>
              <div class="bg-gray-100 h-7 flex items-center gap-x-1 px-3 border-b border-gray-300">
                <FontAwesomeIcon icon="fal fa-language" class="text-xs text-gray-600" fixed-width />
                <Transition name="spin-to-down">
                  <span :key="locale.language.name" class="text-xs font-light leading-none text-gray-600">
                    {{ locale.language.name }}
                  </span>
                </Transition>
              </div>
            </template>

            <!-- Language Options -->
            <template #default>
              <div v-if="Object.keys(locale.languageOptions).length > 0" class="flex flex-col">
                <button v-for="(language, index) in locale.languageOptions" :key="language.id" type="button"
                  @click="onSelectLanguage(language)" :class="[
                    'w-full text-left px-3 py-2 text-sm transition rounded-none',
                    language.id === locale.language.id
                      ? 'bg-gray-200 text-blue-600 font-semibold'
                      : 'hover:bg-gray-100 text-gray-800'
                  ]">
                  {{ language.name }}
                </button>
              </div>

              <div v-else class="text-xs text-gray-400 py-2 px-3">
                {{ trans('Nothing to show here') }}
              </div>

            </template>
          </FooterTab>
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
