<script setup lang="ts">
import { computed, inject } from "vue"

import Image from "@common/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImage } from "@far"

interface FamilyImage {
  original: string
  alt?: string
}

interface FamilyData {
  name?: string
  description?: string
  description_image?: Record<string, FamilyImage>
}

interface FieldValue {
  id?: string | number
  family?: FamilyData
  container?: {
    properties?: Record<string, unknown>
  }
}

type ScreenType = "mobile" | "tablet" | "desktop"

const props = defineProps<{
  modelValue: FieldValue
  screenType: ScreenType
  indexBlock: number
}>()

const layout = inject<Record<string, any>>("layout", {})

const cleanedDescription = computed(() => {
  const html = props.modelValue?.family?.description || ""

  return html.replace(/<h1[^>]*>.*?<\/h1>/gis, "")
})

const trimmedDescription = computed(() => {
  const html = cleanedDescription.value

  const parser = new DOMParser()
  const doc = parser.parseFromString(html, "text/html")

  let count = 0
  const limit = 400

  const walk = (node: Node): boolean => {
    if (count >= limit) {
      node.parentNode?.removeChild(node)
      return true
    }

    if (node.nodeType === Node.TEXT_NODE) {
      const text = node.textContent || ""
      const remaining = limit - count

      if (text.length > remaining) {
        node.textContent = text.slice(0, remaining) + "..."
        count = limit
        return true
      }

      count += text.length
    }

    const children = [...node.childNodes]

    for (const child of children) {
      if (walk(child)) {
        const siblings = [...node.childNodes]
        const index = siblings.indexOf(child)

        siblings.slice(index + 1).forEach((sibling) => {
          sibling.parentNode?.removeChild(sibling)
        })

        return true
      }
    }

    return false
  }

  walk(doc.body)

  return doc.body.innerHTML
})

const images = computed<FamilyImage[]>(() => {
  const data =
    props.modelValue?.family?.web_images?.description

  if (!data) return []

  return Object.values(data).filter(
    (item) => item && item.original
  )
})

const hasImage = (index: number) => {
  return Boolean(images.value?.[index]?.original)
}

const screenStyles = computed(() => {
  switch (props.screenType) {
    case "mobile":
      return {
        wrapperDirection: "flex-col",

        mainImage: {
          width: "220px",
          height: "280px",
        },

        sideImage: {
          width: "105px",
          height: "137px",
        },

        title: {
          fontSize: "22px",
        },

        description: {
          fontSize: "14px",
        },

        button: {
          height: "38px",
          paddingLeft: "32px",
          paddingRight: "32px",
          fontSize: "14px",
        },

        contentAlign: "text-center",
        buttonAlign: "justify-center",
      }

    case "tablet":
      return {
        wrapperDirection: "flex-row",

        mainImage: {
          width: "340px",
          height: "320px",
        },

        sideImage: {
          width: "160px",
          height: "157px",
        },

        title: {
          fontSize: "24px",
        },

        description: {
          fontSize: "15px",
        },

        button: {
          height: "42px",
          paddingLeft: "40px",
          paddingRight: "40px",
          fontSize: "15px",
        },

        contentAlign: "text-left",
        buttonAlign: "justify-start",
      }

    default:
      return {
        wrapperDirection: "flex-row",

        mainImage: {
          width: "420px",
          height: "380px",
        },

        sideImage: {
          width: "200px",
          height: "187px",
        },

        title: {
          fontSize: "30px",
        },

        description: {
          fontSize: "19px",
        },

        button: {
          height: "48px",
          paddingLeft: "48px",
          paddingRight: "48px",
          fontSize: "16px",
        },

        contentAlign: "text-left",
        buttonAlign: "justify-start",
      }
  }
})
</script>

<template>
  <section class="editor-class" :id="modelValue?.id
    ? modelValue.id
    : `family-2`
    " component="family-2-workshop">
    <div class="mx-auto w-full bg-white px-4 py-4" :style="{
      ...getStyles(
        layout?.app?.webpage_layout?.container
          ?.properties,
        screenType
      ),
      ...getStyles(
        modelValue?.container?.properties,
        screenType
      ),
      width: 'auto',
    }">
      <div class="flex gap-6" :class="screenStyles.wrapperDirection">
        <!-- IMAGES -->
        <div class="flex shrink-0 justify-center gap-[6px]">
          <!-- IMAGE 1 -->
          <template v-if="hasImage(0)">
            <Image :src="images[0].original" :imageCover="true" :alt="images[0]?.alt || 'family image'
              " class="object-cover" :style="screenStyles.mainImage" />
          </template>

          <div v-else class="flex items-center justify-center border border-gray-200 bg-gray-100"
            :style="screenStyles.mainImage">
            <FontAwesomeIcon :icon="faImage" class="h-14 w-14 text-gray-400" />
          </div>

          <div class="flex flex-col gap-[6px]">
            <!-- IMAGE 2 -->
            <template v-if="hasImage(1)">
              <Image :src="images[1].original" :imageCover="true" :alt="images[1]?.alt ||
                'family image'
                " class="object-cover" :style="screenStyles.sideImage" />
            </template>

            <div v-else class="flex items-center justify-center border border-gray-200 bg-gray-100"
              :style="screenStyles.sideImage">
              <FontAwesomeIcon :icon="faImage" class="h-14 w-14 text-gray-400" />
            </div>

            <!-- IMAGE 3 -->
            <template v-if="hasImage(2)">
              <Image :src="images[2].original" :imageCover="true" :alt="images[2]?.alt ||
                'family image'
                " class="object-cover" :style="screenStyles.sideImage" />
            </template>

            <div v-else class="flex items-center justify-center border border-gray-200 bg-gray-100"
              :style="screenStyles.sideImage">
              <FontAwesomeIcon :icon="faImage" class="h-14 w-14 text-gray-400" />
            </div>
          </div>
        </div>

        <!-- CONTENT -->
        <div class="flex min-w-0 flex-1 flex-col" :class="screenStyles.contentAlign">
          <div class="">
            <h1 class="font-bold leading-[1.15] text-[#12243c]" :style="screenStyles.title">
              {{ modelValue.family?.name }}
            </h1>
          </div>

          <div class="flex-1 text-[#1d2430]" :style="{
            ...screenStyles.description,
            lineHeight: '1.6',
          }" v-html="trimmedDescription" />
          <div class="mt-6 flex items-center gap-6">
            <button class="shrink-0 rounded-xl border border-[#333] font-medium" :style="{
              ...screenStyles.button,
              ...getStyles(modelValue?.button?.container?.properties)
            }">
              <span v-if="modelValue?.button?.text">
                {{ modelValue?.button?.text }}
              </span>
              <span v-else>
                {{ ctrans('Learn more') }}
              </span>
            </button>

            <div class="
      flex
      flex-wrap
      items-center
      gap-x-4
      gap-y-2
      min-w-0
    ">
              <div v-for="data in modelValue.family.tags" :key="data.name" class="flex items-center gap-1.5">
                <Image :src="data.web_image" class="h-4 w-4 shrink-0" image-class="object-contain" />

                <span class="
          whitespace-nowrap
          text-[12px]
          leading-none
          text-[#555]
        ">
                  {{ data.name }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped></style>