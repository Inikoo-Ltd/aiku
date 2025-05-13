<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 09 May 2025 14:26:55 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">

import { trans } from "laravel-vue-i18n";
import { faCheckCircle } from "@fas";
import { faExclamationTriangle, faClock } from "@fad";
import { library } from "@fortawesome/fontawesome-svg-core";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
import FlashNotification from "@/Components/UI/FlashNotification.vue";
import {FlashNotification  as FlashNotificationType} from "@/types/FlashNotification";

import { PageProps as InertiaPageProps } from "@inertiajs/core";
import { usePage } from "@inertiajs/vue3";

library.add(faCheckCircle, faClock, faExclamationTriangle);

const props = defineProps<{
  mitSavedCards: {
    data: {
      id: number;
      image: string;
      card_type: string;
      last_four_digits: string;
      expires_at: string;
    }[]
  }
  delete_route: {}
}>();

interface PagePropsWithFlash extends InertiaPageProps {
  flash: {
    notification?: FlashNotificationType
  };
}

const page = usePage<PagePropsWithFlash>();

</script>

<template>
  <div>
    <FlashNotification :notification="page.props.flash.notification" />

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