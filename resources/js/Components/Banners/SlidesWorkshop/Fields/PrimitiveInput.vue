<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
  import { set, get } from "lodash";
  import { ref, watch, toRefs } from "vue";
  
  const props = defineProps<{
      fieldName?: string | [];
      fieldData?: {
          placeholder: string;
          readonly: boolean;
          copyButton: boolean;
          prefix?: string
          suffix?: string
          type?: string
      };
      data?: Object;
      counter?: boolean;
      value?: string;
  }>();
  
  const { data, fieldName, value } = toRefs(props);
  const emits = defineEmits();
  
  const setFormValue = (data: Object, fieldName: string | []) => {
      if (Array.isArray(fieldName)) {
          return getNestedValue(data, fieldName);
      } else {
          return data[fieldName];
      }
  };
  
  const getNestedValue = (obj: Object, keys: string[]) => {
      return keys.reduce((acc, key) => {
          if (acc && typeof acc === "object" && key in acc) return acc[key];
          return get(props.fieldData,'defaultValue',null);
      }, obj);
  };
  
  const valued = ref(props.data ? setFormValue(props.data, props.fieldName) : get(props,'value',null));
  
  watch(valued, (newValue) => {
      // Update the local form value when the value ref changes
      emits('onChange', newValue);
      updateLocalFormValue(newValue);
  });
  
  watch(data, (newValue) => {
      valued.value = setFormValue(newValue, props.fieldName);
  });
  
  watch(value, (newValue) => {
      valued.value = newValue
  });
  
  const updateLocalFormValue = (newValue) => {
      let localData = { ...props.data };
      if (Array.isArray(props.fieldName)) {
          set(localData, props.fieldName, newValue);
      } else {
          localData[props.fieldName] = newValue;
      }
      emits("update:data", localData); 
    }

  </script>
  
  <template>
      <div class="relative">
          <div class="flex rounded-md overflow-hidden shadow-sm ring-2 ring-gray-200 focus-within:ring-gray-500 bg-transparent">
              <span v-if="fieldData?.prefix" class="bg-gray-200 flex select-none items-center px-3 text-gray-500 sm:text-sm">
                  {{ fieldData?.prefix }}
              </span>
              <input
                  v-model.trim="valued"
                  :readonly="fieldData?.readonly"
                  :type="props.fieldData?.type ?? 'text'"
                  :placeholder="fieldData?.placeholder"
                  :pattern="props.fieldData?.rules?.pattern" 
                  style="border: 2px solid transparent; outline: none;" 
                  class="block flex-1 py-1.5 pl-3 rounded-md focus:outline-none focus:border-none focus:ring-0 placeholder:text-gray-400 placeholder:text-sm"
              />
              <span v-if="fieldData?.suffix" class="bg-gray-200 flex select-none items-center px-3 text-gray-500 sm:text-sm">
                  {{ fieldData?.suffix }}
              </span>
          </div>

          <!-- Counter: Letters and Words -->
          <div v-if="counter && fieldData?.[fieldName]"
              class="grid grid-flow-col text-xs italic text-gray-500 mt-2 space-x-12 justify-start">
              <p class="">Letters: {{ fieldData?.[fieldName].length }}</p>
              <p class="">
                  Words: {{ fieldData?.[fieldName].trim().split(/\s+/).filter(Boolean).length }}
              </p>
          </div>
      </div>
  </template>
  
  
  <style scoped>
  /* Add this style to remove the focus styles */
  input:focus {
    outline: none;
    border: none;
    box-shadow: none;
  }
  </style>
  
  
  
  
  
  
  