<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 09 May 2025 14:26:55 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">

import { trans } from "laravel-vue-i18n";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faCheckCircle } from "@fas";
import { faExclamationTriangle, faClock } from "@fad";
import { library } from "@fortawesome/fontawesome-svg-core";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";

library.add(faCheckCircle, faClock, faExclamationTriangle);

const props = defineProps<{
  mitSavedCards: {}
  delete_route: {}
}>();


interface Notification {
  status: string;
  mit_saved_card: {
    data: {
      id: number
      token: string
      last_four_digits: string
      card_type: string
      failure_status: string
      expires_at: string
      processed_at: string
      priority: number
      state: string
      label: null | string
      created_at: string
      updated_at: string
    }
  };
}

const getDataWarning = (notif: Notification) => {
  if (notif.status === "success") {
    return {
      message: trans("Success!"),
      bgColor: "bg-green-200",
      textColor: "text-green-600",
      icon: "fas fa-check-circle",
      description: `Your ${notif.mit_saved_card.data.card_type} ending in ${notif.mit_saved_card.data.last_four_digits} has been saved successfully.`
    };
  } else if (notif.status === "failure") {
    return {
      message: notif.mit_saved_card.data.failure_status,
      bgColor: "bg-red-200",
      textColor: "text-red-600",
      icon: "fad fa-exclamation-triangle",
      description: `Your ${notif.mit_saved_card.data.card_type} ending in ${notif.mit_saved_card.data.last_four_digits} is not successfully stored. Please contact administrator.`
    };
  } else {
    return {
      message: trans("Pending"),
      bgColor: "bg-gray-200",
      textColor: "text-gray-600",
      icon: "fad fa-clock",
      description: `We will review your ${notif.mit_saved_card.data.card_type} card ending in ${notif.mit_saved_card.data.last_four_digits}. It may take awhile to process.`
    };
  }
};
</script>

<template>
  <div>


    <Transition name="slide-to-right">
      <div v-if="$page.props.flash.notification" class="p-4">
        <div class="px-4 py-3 rounded-md"
             :class="getDataWarning($page.props.flash.notification).bgColor"
        >
          <div class="flex items-center" :class="getDataWarning($page.props.flash.notification).textColor">
            <div class="text-3xl">
              <FontAwesomeIcon :icon="getDataWarning($page.props.flash.notification).icon" class="" fixed-width aria-hidden="true" />
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-bold">{{ getDataWarning($page.props.flash.notification).message }}</h3>
              <div class="text-xs opacity-90 ">
                <p>{{ getDataWarning($page.props.flash.notification).description }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>


    <div v-if="props.mitSavedCards?.data?.length" class="mt-8 flow-root px-8">

      <div class="flex justify-end">
        <ButtonWithLink
          :routeTarget="{
                name: 'retina.dropshipping.mit_saved_cards.create'
              }"
          :label="trans('Add credit card')"
          type="secondary"
          icon="fas fa-plus"
        />
      </div>

      <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
          <table class="min-w-full divide-y divide-gray-300">
            <thead>
            <tr>
              <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold  sm:pl-0">Image</th>
              <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold ">Card type</th>
              <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold ">Last 4 digits</th>
              <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-0">
                <span class="sr-only">Edit</span>
              </th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-for="card in props.mitSavedCards.data" :key="card.id">
              <td class="whitespace-nowrap py-5 pl-4 pr-3 text-sm sm:pl-0">
                <div class="flex items-center">
                  <div class="size-11 shrink-0">
                    <img class="size-11 rounded-full" :src="card.image" alt="" />
                  </div>

                </div>
              </td>

              <td class="whitespace-nowrap px-3 py-5 text-sm">
                <div class="font-bold">{{ card.card_type }}</div>
                <div class="mt-1 text-gray-500">{{ card.expires_at }}</div>
              </td>


              <td class="whitespace-nowrap px-3 py-5 text-sm">{{ card.last_four_digits }}</td>

              <td class="relative whitespace-nowrap py-5 pl-3 pr-4 text-right text-sm font-medium sm:pr-0">

              </td>
            </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-else class="relative isolate overflow-hidden">
      <svg class="absolute inset-0 -z-10 size-full stroke-gray-200 [mask-image:radial-gradient(100%_100%_at_top_right,white,transparent)]"
           aria-hidden="true">
        <defs>
          <pattern id="0787a7c5-978c-4f66-83c7-11c213f99cb7" width="200" height="200" x="50%" y="-1"
                   patternUnits="userSpaceOnUse">
            <path d="M.5 200V.5H200" fill="none" />
          </pattern>
        </defs>
        <rect width="100%" height="100%" stroke-width="0" fill="url(#0787a7c5-978c-4f66-83c7-11c213f99cb7)" />
      </svg>

      <div class="mx-auto max-w-7xl px-6 pb-12 pt-10 lg:flex lg:px-14 ">
        <div class="mx-auto max-w-2xl lg:mx-0 lg:shrink-0 lg:pt-8">
          <div class="">
            <a href="#" class="inline-flex space-x-6">
                        <span class="rounded-full bg-indigo-600/10 px-3 py-1 text-sm/6 font-semibold text-indigo-600 ring-1 ring-inset ring-indigo-600/10">
                            What's new?
                        </span>
            </a>
          </div>

          <h1 class="mt-10 text-pretty text-5xl font-semibold tracking-tight sm:text-7xl">
            Manage your orders and products
          </h1>
          <p class="mt-8 text-pretty text-lg font-medium text-gray-500 sm:text-xl/8">
            Control your orders and products with our easy-to-use dashboard. You can manage your orders, products, and customers all in one place.
          </p>
          <div class="mt-10 flex items-center gap-x-6">

            <ButtonWithLink
              :routeTarget="{
                  name: 'retina.dropshipping.mit_saved_cards.create'
                }"
              :label="trans('Save credit card')"
              icon="fas fa-plus"
            />

          </div>
        </div>

      </div>
    </div>
  </div>
</template>