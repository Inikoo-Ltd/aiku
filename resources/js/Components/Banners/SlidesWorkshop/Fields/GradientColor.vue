
<script  setup lang="ts">
import 'vue-color-kit/dist/vue-color-kit.css'
import { useBannerBackgroundColor } from "@/Composables/useStockList"
import { set, get } from 'lodash-es';
import { ref, watch, toRefs } from "vue";
import { faPaintBrushAlt, faText } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faPaintBrushAlt, faText)


const props = withDefaults(defineProps<{
    fieldName?: string | [];
    fieldData?: {
        placeholder: string;
        readonly: boolean;
        copyButton: boolean;
        icon?: string
    };
    data?: Object;
}>(), {
})


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
  

const backgroundColorList = useBannerBackgroundColor() // Fetch color list from Composables
</script>

<template>
    <div class="flex gap-3">
        <div class="h-8 flex items-center w-fit gap-x-1.5">
            <div v-for="bgColor in backgroundColorList.filter((item)=>item.includes('linear-gradient'))" @click="valued = bgColor"
                class="w-full rounded h-full aspect-square shadow cursor-pointer" :class="data?.layout.background?.[screenView || 'desktop'] === bgColor && data?.layout.backgroundType?.[screenView || 'desktop'] === 'color'
                    ? 'ring-2 ring-offset-2 ring-gray-600'
                    : 'hover:ring-2 hover:ring-offset-0 hover:ring-gray-500'"
                :style="{ background: bgColor }" />
        </div>
    </div>
</template>

<style lang="scss" scoped>
.colors {
    @apply hidden
}

.hu-color-picker {
    @apply absolute left-0 bottom-0
}
</style>

