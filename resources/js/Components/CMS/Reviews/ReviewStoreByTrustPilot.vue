<script setup lang="ts">
import { inject, onMounted, nextTick, watch } from 'vue'

interface ReviewConfig {
  provider?: string
  data?: {
    template_id?: string
    business_unit_id?: string
    token?: string
    link?: string
  }
}

const props = defineProps<{
  review: ReviewConfig
  code: string
}>()

const layout: any = inject("layout", {})

const SCRIPT_ID = "trustpilot-widget-script"


const loadScript = (): Promise<void> => {
  return new Promise((resolve) => {
    if (document.getElementById(SCRIPT_ID)) {
      resolve()
      return
    }

    const script = document.createElement("script")
    script.id = SCRIPT_ID
    script.src = "https://widget.trustpilot.com/bootstrap/v5/tp.widget.bootstrap.min.js"
    script.async = true
    script.onload = () => resolve()

    document.body.appendChild(script)
  })
}

const getLocale = () => {
  const locale = layout?.iris?.locale || 'en-US'
  return locale.replace('_', '-')
}

const initWidget = async () => {
  await nextTick()

  const el = document.getElementById(`trustpilot-widget-${props.code}`)
  if (!el) return

  const data = props.review?.data
  if (!data?.template_id || !data?.business_unit_id) return

  // Clear previous render (important for re-init)
  el.innerHTML = `
    <a href="${data.link || '#'}" target="_blank" rel="noopener">Trustpilot</a>
  `

  if ((window as any).Trustpilot) {
    ;(window as any).Trustpilot.loadFromElement(el, true)
  }
}

onMounted(async () => {
  await loadScript()
  await initWidget()
})


watch(
  () => props.review,
  async () => {
    await initWidget()
  },
  { deep: true }
)

watch(
  () => props.code,
  async () => {
    await initWidget()
  }
)
</script>

<template>
  <div
    :id="`trustpilot-widget-${code}`"
    class="trustpilot-widget"
    :data-locale="getLocale()"
    :data-template-id="review?.data?.template_id"
    :data-businessunit-id="review?.data?.business_unit_id"
    data-style-height="200px"  
    data-style-width="100%"
    :data-token="review?.data?.token"
  >
    <a
      :href="review?.data?.link || '#'"
      target="_blank"
      rel="noopener"
    >
      Trustpilot
    </a>
  </div>
</template>