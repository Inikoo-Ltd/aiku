<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 30 Oct 2023 12:48:06 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue';
import { trans } from 'laravel-vue-i18n'
import { faThLarge, faTreeChristmas, faGlassCheers, faBat, faPlus } from '@fas/'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faSpinnerThird } from '@fad'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import TemplatesSeeder from './TemplatesSeeder.vue';
import { notify } from "@kyvg/vue3-notification"
import axios from 'axios';
import SavedTemplates from './SavedTemplates.vue'

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
    mailshot : object
}>()

const emits = defineEmits();
const selectedIndex = ref(0)
const categories = [
    {
        name: 'templates',
        label: trans('Templates'),
        component: TemplatesSeeder,
    },
    {
        name: 'saved_templates',
        label: trans('Saved Templates'),
        component: SavedTemplates,
    },
]


const getTemplate= async (id) => {
    console.log(id)
      try {
          const response = await axios.get(
              route('grp.json.email_templates.show.compiled_layout', { emailTemplate: id}),
          )
          return response.data
      } catch (error) {
          console.log(error)
          notify({
              title: "Failed to get Templates",
              type: "error"
          });
          return null
      }
  }

  const selectTemplate = (template) => {
    getTemplate(template.id)
        .then(data => {
            console.log(data);
         emits("changeTemplate", data);
        })
        .catch(error => {
            console.error(error);
            notify({
              title: "Failed to get Templates",
              type: "error"
          });
        });
};

</script>
  
<template >
    <div class="text-center text-2xl font-bold mb-4">{{ trans("Available Templates") }}</div>
    <TabGroup @change="(index)=>selectedIndex = index"  :selectedIndex="selectedIndex">
            <TabList class="flex space-x-8 ">
                <Tab v-for="(category, categoryIndex) in categories" as="template" :key="categoryIndex"
                    v-slot="{ selected }">
                    <button :class="[
                        'whitespace-nowrap border-b-2 py-1.5 px-1 text-sm font-medium focus:ring-0 focus:outline-hidden',
                        selected
                            ? 'border-group-5s00 text-org-500'
                            : 'border-transparent text-gray-400 hover:border-gray-300',
                    ]">
                        {{ category.label }}
                    </button>
                </Tab>
            </TabList>

            <TabPanels class="mt-2 h-[600px]">
                <TabPanel v-for="(category, categoryIndex) in categories" :key="categoryIndex"
                    class="rounded bg-gray-50 p-3 ring-2 ring-gray-200 focus:outline-hidden h-full  overflow-auto">
                    <component 
                    :is="category.component" 
                    @changeTemplate="selectTemplate"
                    :mailshot="mailshot"
                />
                </TabPanel>
            </TabPanels>
    
        </TabGroup>
</template>
  
  
  
  
  
  