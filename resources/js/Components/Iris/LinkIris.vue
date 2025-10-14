<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { inject, computed } from "vue"

type RouteType = string | Record<string, any>

const layout = inject("layout", {})

const props = withDefaults(
  defineProps<{
    href: RouteType
    header?: string
    method?: string
    as?: string
    class?: string
    style?: Record<string, any>
    label?: string
    target?: string
    type?: string // "internal" | "external"
    canonical_url?: string
  }>(),
  {
    header: "",
    method: "get",
    as: "a",
    class: "",
    style: () => ({}),
    label: "",
    target: "_self",
    type: "internal",
  }
)

const computedHref = computed(() => {
  const env = layout?.app?.environment || "local"
  const domainType = layout?.retina?.type || "b2b"
  let url = String(props.canonical_url || props.href)

  // Skip rewrite if link type is external
  if (props.type !== "internal") return url

  const domainMap: Record<string, string> = {
    local:
      domainType === "b2b"
        ? "ecom.test"
        : domainType === "dropshipping"
          ? "ds.test"
          : "fulfilment.test",
    staging: "canary",
    production: "www",
  }

  // ✅ Handle relative route (like "/dashboard")
  if (!/^https?:\/\//.test(url)) {
    if (env === "local") {
      return `https://${domainMap.local}${url}`
    }
    return url
  }

  // ✅ Handle full URLs
  try {
    const parsed = new URL(url)
    if (env === "local") {
      parsed.hostname = domainMap.local
      parsed.protocol = "https:"
    }
    return parsed.toString()
  } catch {
    return url
  }
})
</script>

<template>
  <Link v-if="type == 'internal'" :href="computedHref" :method="props.method" :headers="props.header" :as="props.as"
    :class="props.class" :style="props.style" :target="props.target">
  <slot>{{ props.label }}</slot>
  </Link>
  <a v-else :href="props.href" :class="props.class" :style="props.style" :target="props.target"
    rel="noopener noreferrer">
    <slot>{{ props.label }}</slot>
  </a>
</template>
