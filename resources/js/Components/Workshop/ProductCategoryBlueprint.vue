<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 08 Feb 2024 12:36:21 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
  import { faGlobe, faLink, faPencil } from "@fal";
  import { ref } from "vue";
  import { library } from "@fortawesome/fontawesome-svg-core";
  import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
  import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
  import { trans } from "laravel-vue-i18n";
  
  library.add(faGlobe, faLink);
  
  const props = defineProps<{
    data: {
      slug: string
      url: string
      domain: string
      state: string
      status: string
      created_at: string
      updated_at: string
      layout: string
    },
  }>();
  

  
  </script>
  <template>
  haloooo
  </template>
  