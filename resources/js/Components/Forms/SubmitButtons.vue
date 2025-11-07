<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 08 Oct 2022 12:00:53 Central European Summer Time, Mijas ,Spain
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup>
const props = defineProps(['form']);
import {trans} from 'laravel-vue-i18n';

</script>

<template>
    <div class="divide-y divide-gray-200 pt-6">
        <div class="mt-4 flex justify-end py-4 px-4 sm:px-6">
            <button
                @click="form.reset()"
                v-if="form.isDirty" type="button"
                class="inline-flex justify-center rounded-md border border-gray-300 bg-white py-2 px-4 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">
                {{ trans('Cancel') }}
            </button>
            <button type="submit"
                    :class="[form.isDirty ? 'bg-sky-700 text-white hover:bg-sky-800  focus:outline-hidden focus:ring-2 focus:ring-sky-500 focus:ring-offset-2' : 'bg-gray-100 text-gray-400', 'ml-5 inline-flex justify-center rounded-md border border-transparent  py-2 px-4 text-sm font-medium  shadow-sm ']"
                    :disabled="form.processing">
                {{ trans('Save') }}
            </button>
        </div>
    </div>
</template>
