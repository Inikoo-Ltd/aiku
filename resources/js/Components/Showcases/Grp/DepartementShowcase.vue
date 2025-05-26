<script setup lang="ts">
import SetVisibleList from "@/Components/Departement&Family/SetVisibleList.vue";
import { faImage } from "@far";
import { faInfoCircle } from "@fas";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import Image from "@/Components/Image.vue";
import Message from "primevue/message";
import { Link } from "@inertiajs/vue3";
import { routeType } from "@/types/route";


const props = defineProps<{
    data: {
        families: Array<any>
        department: {
            data: {
                name: string,
                description: string
                image: Array<string>
                url_master: routeType
            }
        }
    }
}>();

const goToPrev = () => {
    console.log("Previous clicked");
};

const goToNext = () => {
    console.log("Next clicked");
};

console.log(props)

</script>

<template>
    <div class="px-4 pb-8 m-5">

        <Message v-if="data.department?.url_master" severity="success" closable>
            <template #icon>
                <FontAwesomeIcon :icon="faInfoCircle" />
            </template>
            <span class="ml-2">Right Now you follow
        <Link :href="route(data.department.url_master.name,data.department.url_master.parameters)" class="underline font-bold">
        the master data
        </Link>
      </span>
        </Message>


        <div class="grid grid-cols-1 lg:grid-cols-[30%_1fr] gap-6 mt-4 ">
            <div>
                <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
                    <!-- <div class="flex justify-between items-center border-b pb-4 mb-4">
                        <h3 class="text-xl font-semibold">Preview {{data?.department?.name }}</h3>
                    </div> -->
                    <div class="flex items-center justify-between mb-6">
                        <!--  <button @click="goToPrev" aria-label="Previous">
                        <FontAwesomeIcon :icon="faChevronCircleLeft" class="text-xl text-gray-600 hover:text-primary" />
                      </button> -->
                        <div class="flex-1 mx-4">
                            <div class="bg-white rounded-lg shadow hover:shadow-md transition duration-300">
                                <Image v-if="data?.department?.image" :src="data?.department?.image" :imageCover="true"
                                       class="w-full h-40 object-cover rounded-t-lg" />
                                <div v-else class="flex justify-center items-center bg-gray-100 w-full h-48">
                                    <FontAwesomeIcon :icon="faImage" class="w-8 h-8 text-gray-400" />
                                </div>
                            </div>
                        </div>
                        <!--  <button @click="goToNext" aria-label="Next">
                        <FontAwesomeIcon :icon="faChevronCircleRight" class="text-xl text-gray-600 hover:text-primary" />
                      </button> -->
                    </div>


                    <div class="border-t pt-4 space-y-4 text-sm text-gray-700">
                        <div class="text-sm font-medium">
                            <span>{{ data?.department?.name || "No label" }}</span>
                        </div>
                        <div class="text-md">
                            <span class="text-gray-400">{{ data?.department?.description || "No description" }}</span>
                        </div>
                    </div>
                </div>

            </div>

          <!--   <SetVisibleList :title="'Family List'" :list_data="data.families?.data" :disabled="true" /> -->
        </div>
    </div>


</template>
