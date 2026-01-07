<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
  import { faCube, faStar, faImage, faEmptySet } from "@fas"
  import { faPencil  } from "@far"
  import { library } from "@fortawesome/fontawesome-svg-core"
  import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
  import Gallery from "../Gallery/Gallery.vue";
  import Image from "@/Components/Image.vue"
  import { ref } from "vue"
  import Button from "@/Components/Elements/Buttons/Button.vue";
  
  
  library.add(faCube, faStar, faImage, faPencil, faEmptySet)
  
  const props = defineProps<{
      modelValue: any
      webpageData?: any
      web_block : any
  }>()
  
  const emits = defineEmits<{
      (e: 'update:modelValue', value: string): void
  }>()
  
  
  
  </script>
  
  <template>
  
      <div class="p-5">
          <div type="button" 
              class="relative block w-full rounded-lg border-2 border-dashed border-gray-300 p-12 text-center hover:border-gray-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
              <font-awesome-icon :icon="['fas', 'empty-set']" class="mx-auto h-12 w-12 text-gray-400" />
              <span class="mt-2 block text-sm font-semibold text-gray-900">Components not done yet : call a developer to create one</span>
          </div>
      </div>
  
  </template>