/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Sep 2023 20:46:11 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import i18n from "laravel-vue-i18n/vite";
import { fileURLToPath, URL } from "node:url";
import { codecovVitePlugin } from "@codecov/vite-plugin";
import path from "node:path";
import tailwindcss from 'tailwindcss';

export default defineConfig(
  {
    server : {
      cors : true,
      watch: {
        ignored: ["**/storage/media/**"]
      }
    },
    plugins: [
      laravel({
                hotFile       : "public/iris.hot",
                buildDirectory: "iris",
                input         : [
                  "resources/css/app.css",
                  "resources/js/app-iris.js",
                  "node_modules/@fortawesome/fontawesome-free/css/svg-with-js.min.css"
                ],
                ssr           : "resources/js/ssr-iris.js",
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
      i18n(),
      codecovVitePlugin({
                          enableBundleAnalysis: process.env.CODECOV_TOKEN !==
                            undefined,
                          bundleName          : "iris",
                          uploadToken         : process.env.CODECOV_TOKEN
                        })
    ],
    ssr    : {
      noExternal: ["@inertiajs/server", "vue-countup-v3", "floating-vue", "tailwindcss", "@fortawesome/*"]
    },
    resolve: {
      alias: {
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
        "@fonts": path.resolve(__dirname, "./public/assets/Fonts/"),
        "@art"  : path.resolve(__dirname, "./public/art/")
      }
    },
    build  : {
      sourcemap    : true,
      // transpile: ["@fortawesome/vue-fontawesome", "@fortawesome/fontawesome-svg-core"],        
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
      postcss: {
        plugins: [tailwindcss],
      },
      preprocessorOptions: {
        scss: {
          silenceDeprecations: ["legacy-js-api"]
        }
      }
    }
  });
