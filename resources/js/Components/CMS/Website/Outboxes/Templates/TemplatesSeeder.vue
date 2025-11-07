<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 30 Oct 2023 12:48:06 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
  import { ref, onMounted, watch } from 'vue'
  import axios from 'axios';
  import { notify } from "@kyvg/vue3-notification"
  import Button from '@/Components/Elements/Buttons/Button.vue';
  import { trans } from 'laravel-vue-i18n'
  import Tag from '@/Components/Tag.vue';
  import EmptyState from '@/Components/Utils/EmptyState.vue'
  import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
  import { faThLarge, faTreeChristmas, faGlassCheers, faBat, faPlus } from '@fas/'
  import { library } from '@fortawesome/fontawesome-svg-core'
  import Image from '@/Components/Image.vue'
  import { get } from 'lodash-es'
  import { faSpinnerThird } from '@fad'
  
  library.add(faThLarge, faTreeChristmas, faGlassCheers, faBat, faPlus, faSpinnerThird)
  
  const props = defineProps<{
      title: string,
      pageHead: object,
      tabs: {
          current: string;
          navigation: object;
      }
      changelog?: object,
      showcase?: object,
      snapshots?: object,
      mailshot?:object
  }>()
  
  const emits = defineEmits();
  const loadingState = ref(false)
  const templates = ref([])
  const activeCategory = ref(null)
  
  const getTemplates = async () => {
      loadingState.value = true
      try {
          const response = await axios.get(
              route('grp.json.email_templates.seeded', { category: activeCategory.value }),
          )
          templates.value = Object.values(response.data)
          loadingState.value = false
      } catch (error) {
          console.log(error)
          notify({
              title: "Failed to get Templates",
              type: "error"
          });
          loadingState.value = false
      }
  }
  
  
  
  const selectTemplate = (template) => {
      emits("changeTemplate", template);
  }
  
  const categories = [
      { label: trans('All Template'), value: null, icon: 'fas fa-th-large' },
      { label: trans('Christmas'), value: 'Christmas', icon: 'fas fa-tree-christmas' },
      { label: trans('New Year'), value: 'New Year', icon: 'fas fa-glass-cheers' },
      { label: trans('Halloween'), value: 'Halloween', icon: 'fas fa-bat' },
  ]
  
  watch(activeCategory, () => {
      getTemplates()
  })
  
  onMounted(() => {
      getTemplates()
  })
  
  </script>
  
  <template >
      <div v-if="!loadingState">
          <div class="flex flex-wrap justify-center items-center gap-4 m-4">
              <div v-for="category in categories" :key="category.value">
                  <Tag :label="category.label" :theme="category.value == activeCategory ? 5 : 0"
                      @click="() => activeCategory = category.value">
                      <template #label>
                          <FontAwesomeIcon :icon="category.icon" class='' aria-hidden='true' />
                          {{ category.label }}
                      </template>
                  </Tag>
              </div>
          </div>
          <div v-if="templates.length > 0"
              class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
              <div v-for="template in templates" :key="template.slug" class="relative w-full">
                  <div v-if="template.slug != 'blank'"
                      class="relative pb-[90%] border border-gray-300 rounded-lg overflow-hidden">
                      <Image :src="template.image_thumbnail" :alt="template.title"
                          class="absolute inset-0 w-full h-full object-cover rounded-lg" />
                  </div>
  
                  <div v-else
                      class="relative pb-[90%] border border-gray-300 rounded-lg overflow-hidden flex justify-center items-center">
                      <div class="absolute inset-0 w-full h-full object-cover rounded-lg flex justify-center items-center">
                          <font-awesome-icon :icon="['fas', 'plus']" class="text-4xl text-gray-500" />
                      </div>
                  </div>
  
                  <div
                      class="absolute inset-0 flex items-center justify-center bg-black/50 rounded-lg opacity-0 hover:opacity-100 transition duration-300">
                      <div class="text-white text-center">
                          <Button :label="trans('Use Template')" @click="selectTemplate(template)" />
                      </div>
                  </div>
                  <span class="flex justify-center p-2 font-bold text-center">{{ template.slug != 'blank' ?
                      get(template, ['title']) : 'Blank Template' }}</span>
              </div>
  
          </div>
          <div v-else class="p-4">
              <EmptyState :data="{
                  title: trans('You have not uploaded any templates'),
                  description: trans(''),
              }" />
          </div>
      </div>
  
      <div v-else class="flex justify-center align-middle">
          <FontAwesomeIcon v-if="loadingState" icon='fad fa-spinner-third' class='animate-spin text-[30px]' fixed-width
              aria-hidden="true" />
      </div>
  
  </template>
  
  
  
  
  
  