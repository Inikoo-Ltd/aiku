<script setup lang="ts">
import { trans } from "laravel-vue-i18n";
import { inject } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { getStyles } from "@/Composables/styles";
import { checkVisible, textReplaceVariables } from "@/Composables/Workshop";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";

library.add(faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus);

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

// Method: generate url for Login
const urlLoginWithRedirect = () => {
  if (route().current() !== "retina.login.show" && route().current() !== "retina.register") {
    return `/app/login?ref=${encodeURIComponent(window?.location.pathname)}${
      window?.location.search ? encodeURIComponent(window?.location.search) : ""
    }`
  } else {
    return "/app/login"
  }
}
</script>

<template>
  <div></div>
  <div
    id="top_bar_1_iris"
    class="py-1 px-4 flex flex-col md:flex-row md:justify-between gap-x-4"
    :style="getStyles(model?.container.properties)"
  >
    <div class="flex-shrink flex flex-col md:flex-row items-center justify-between w-full ">
      <!-- Section: Main title -->
      <div
        v-if="checkVisible(model?.main_title?.visible || null, isLoggedIn) && textReplaceVariables(model?.main_title?.text, layout.iris_variables)"
        class="text-center flex items-center"
        v-html="textReplaceVariables(model?.main_title?.text, layout.iris_variables)"
      />
    </div>


    <div class="action_buttons flex justify-between md:justify-start items-center gap-x-1 flex-wrap md:flex-nowrap">

        <!-- Section: My account -->
        <ButtonWithLink
          v-if="checkVisible(model?.profile?.visible || null, isLoggedIn) && layout.retina?.type == 'dropshipping'"
          v-tooltip="trans('My account')"
          url="/app/dashboard"
          :label="trans('My account')"
        >
        </ButtonWithLink>
      
      <!-- Section: Profile -->
      <ButtonWithLink
        v-if="checkVisible(model?.profile?.visible || null, isLoggedIn)"
        v-tooltip="trans('Profile')"
        url="/app/profile"
        icon="fal fa-user"
      >
        <template #label>
          <span v-html="textReplaceVariables(model?.profile?.text, layout.iris_variables)" />
        </template>
      </ButtonWithLink>

      <!-- Section: Favourite -->
      <ButtonWithLink
        v-if="checkVisible(model?.favourite?.visible || null, isLoggedIn) && layout.retina?.type !== 'dropshipping'"
        v-tooltip="trans('Favourites')"
        url="/app/favourites"
        icon="fal fa-heart"
      >
        <template #label>
          <span v-html="textReplaceVariables(model?.favourite?.text, layout.iris_variables)" />
        </template>
      </ButtonWithLink>


      <!-- Section: Basket -->
      <ButtonWithLink
        v-if="checkVisible(model?.cart?.visible || null, isLoggedIn) && layout.retina?.type == 'b2b'"
        url="/app/basket"
        icon="fal fa-shopping-cart"
      >
        <template #label>
          <span v-html="textReplaceVariables(model?.cart?.text, layout.iris_variables)" />
        </template>
      </ButtonWithLink>

      <!-- Section: Register -->
      <ButtonWithLink
        v-if="checkVisible(model?.register?.visible || null, isLoggedIn)"
        url="/app/register"
        icon="fal fa-user-plus"
        type="transparent"
      >
        <template #icon>
          <FontAwesomeIcon icon="fal fa-user-plus" class="text-white" fixed-width aria-hidden="true" />
        </template>

        <template #label>
          <span v-html="textReplaceVariables(model?.register.text, layout.iris_variables)" class="text-white" />
        </template>
      </ButtonWithLink>

      <!-- Section: Login -->
      <ButtonWithLink
        v-if="checkVisible(model?.login?.visible || null, isLoggedIn)"
        :url="urlLoginWithRedirect()"
        icon="fal fa-sign-in"
      >
        <template #label>
          <span v-html="textReplaceVariables(model?.login?.text, layout.iris_variables)" />
        </template>
      </ButtonWithLink>

      <!-- Section: Logout -->
      <ButtonWithLink
        v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)"
        url="/app/logout"
        method="post"
        :data="{}"
        icon="fal fa-sign-out"
      >
        <template #label>
          <span v-html="textReplaceVariables(model?.logout?.text, layout.iris_variables)" />
        </template>
      </ButtonWithLink>
    </div>
  </div>
</template>
