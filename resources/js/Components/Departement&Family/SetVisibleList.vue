<script setup lang="ts">
import { faImage, faEye, faEyeSlash } from '@far';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';

const props = defineProps<{
    title: string,
    list_data : Array<any>
}>()

</script>

<template>
    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200 ">
        <div class="flex justify-between items-center border-b pb-4 mb-4">
            <h3 class="text-xl font-semibold">Families List</h3>
            <!-- <Button label="Preview" :size="'xs'" :type="'tertiary'" :icon="faEye"
                @click="isModalFamiliesPreview = true" /> -->
        </div>

        <ul class="divide-y divide-gray-100 max-h-[calc(100vh-30vh)] min-h-12 overflow-auto">
            <li v-for="(item, index) in list_data" :key="item.slug"
                class="flex items-center justify-between py-4 hover:bg-gray-50 px-2 rounded-lg transition">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-gray-100 flex items-center justify-center rounded-lg overflow-hidden">
                        <img v-if="item.image" :src="item.image" alt="Item Image" class="w-full h-full object-cover" />
                        <FontAwesomeIcon v-else :icon="faImage" class="text-gray-400 text-xl" />
                    </div>
                    <div>
                        <div class="font-medium text-gray-800">{{ item.name }}</div>
                        <div class="text-sm text-gray-500">{{ item.code }}</div>
                    </div>
                </div>

                <div 
                    class="text-gray-500 hover:text-primary cursor-pointer transition"
                    
                    title="Toggle visibility"
                >
                    <FontAwesomeIcon :icon="item.show_in_website ? faEye : faEyeSlash" />
                </div>
            </li>
        </ul>
    </div>
</template>
