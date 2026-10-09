<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import LayoutRetinaAuth from "@/Layouts/RetinaAuth.vue";

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faArrowLeft, faTimesCircle } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";


library.add(faArrowLeft, faTimesCircle);
import { ctrans } from "@/Composables/useTrans"

defineOptions({ layout: LayoutRetinaAuth });
defineProps({
  title: {
    type: String,
  },
  error: {
    type: String,
  }
});


</script>

<template>

  <Head title="Reset Password" />

  <Link :href="route('retina.reset-password.edit')" class="absolute left-4 top-4 text-sm text-gray-600 hover:underline">
    <FontAwesomeIcon icon='fal fa-arrow-left' class='' fixed-width aria-hidden='true' />
    {{ctrans("Back to reset password")}}
  </Link>

  <div class="text-center font-bold text-xl mt-7">{{ ctrans("Error resetting password") }}</div>
  <div class="text-center">
    <FontAwesomeIcon icon="fal fa-times-circle" class="text-red-500 text-4xl mb-4 mt-4" fixed-width aria-hidden="true" />
  </div>

  <div class=" mb-3 text-center text-gray-600 ">
    <div class="mb-1">{{ error }}</div>
  </div>

</template>
