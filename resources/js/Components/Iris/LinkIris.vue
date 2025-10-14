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

  if (env === "staging" || env === "production") {
    const prefix = domainMap[env]
    url = url
      .replace(/^https?:\/\/(www\.|canary\.)?/, `https://${prefix}.`)
      .replace(/ds\.test|ecom\.test|fulfilment\.test/, "example.com")
  } else if (env === "local") {
    if (!url.includes(".test")) {
      url = url.replace(/example\.com/, domainMap.local)
    }
  }

  return url
})
</script>

<template>
  <Link
    :href="computedHref"
    :method="props.method"
    :headers="props.header"
    :as="props.as"
    :class="props.class"
    :style="props.style"
    :target="props.target"
  >
    <slot>{{ props.label }}</slot>
  </Link>
</template>
