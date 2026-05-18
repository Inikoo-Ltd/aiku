/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Feb 2024 09:06:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import i18n from "laravel-vue-i18n/vite";
import { fileURLToPath, URL } from "node:url";
import path from "node:path";

export default defineConfig(
  {
    server : {
      cors: true
    },
    plugins: [
      laravel({
                hotFile       : "public/aiku-public.hot",
                buildDirectory: "aiku-public",
                input         : "resources/js/app-aiku-public.js",
                ssr           : "resources/js/ssr-aiku-public.js",
                refresh       : true
              }),
      vue({
            template: {
              transformAssetUrls: {
                base           : null,
                includeAbsolute: false
              }
            }
          }),
      i18n()
    ],
    ssr    : {
      noExternal: ["@inertiajs/server"]
    },
    resolve: {
      alias: {
        "@": fileURLToPath(new URL("./resources/js", import.meta.url)),
        "@iris": fileURLToPath(new URL("./resources/js/Iris", import.meta.url)),
        "@common": fileURLToPath(new URL("./resources/js/Common", import.meta.url)),
        "@fad"  : fileURLToPath(
          new URL("./private/fa/pro-duotone-svg-icons",
                  import.meta.url)),
        "@fal"  : fileURLToPath(
          new URL("./private/fa/pro-light-svg-icons",
                  import.meta.url)),
        "@far"  : fileURLToPath(
          new URL("./private/fa/pro-regular-svg-icons",
                  import.meta.url)),
        "@fas"  : fileURLToPath(
          new URL("./private/fa/pro-solid-svg-icons",
                  import.meta.url)),
        "@fonts": fileURLToPath(new URL("./public/assets/Fonts/", import.meta.url)),
        "@art"  : fileURLToPath(new URL("./public/art/", import.meta.url))
      }
    },
    build  : {
      sourcemap    : true,
      devSourcemap : true,
      rollupOptions: {
        output: {
          manualChunks(id) {
            if (id.includes("node_modules") &&
              !id.includes("sentry")) {
              return id.toString().
                split("node_modules/")[1].split(
                "/")[0].toString();
            }
          }
        }
      }
    },
    css    : {
      preprocessorOptions: {
        scss: {
          silenceDeprecations: ["legacy-js-api"]
        }
      }
    }
  });
