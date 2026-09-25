/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Feb 2024 10:38:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import i18n from "laravel-vue-i18n/vite";
import { fileURLToPath, URL } from "node:url";
import { codecov } from "./vite.codecov.mjs";
import { langFilter, faPerIconImports, FA_COMMONJS_OPTIONS } from "./vite.app-plugins.mjs";
import path from "node:path";
import { analyzer } from 'vite-bundle-analyzer'
import tailwindcss from 'tailwindcss';
import tailwindcssNesting from 'tailwindcss/nesting';
import autoprefixer from 'autoprefixer';
import { createRequire } from "node:module";

/* A plain import would be inlined by the esbuild pass vite runs over this config, breaking
 * the helper's own require calls. */
const retinaModuleGraph = createRequire(path.join(process.cwd(), "vite.retina.config.mjs"))("./app-module-graph.cjs").retina;


export default defineConfig(
  {
    cacheDir: "node_modules/.vite-retina",
    server : {
      cors   : true,
      watch: {
        usePolling: false,
        ignored: ["**/storage/media/**"]
      }
    },
    plugins: [
      laravel({
                hotFile       : "public/retina.hot",
                buildDirectory: "retina",
                input         : "resources/js/app-retina.js",
                ssr           : "resources/js/ssr-retina.js",
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
      langFilter(retinaModuleGraph),
      faPerIconImports(),
      codecov("retina")
       //, analyzer()
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
      commonjsOptions: FA_COMMONJS_OPTIONS,
      rollupOptions: {
        output: {
          manualChunks(id) {
            if (id.includes("node_modules") &&
              !id.includes("sentry") && !id.includes("node_modules/primevue/")) {
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
        plugins: [
          tailwindcssNesting,
          tailwindcss({ config: "tailwind.retina.config.js" }),
          autoprefixer
        ],
      },
      preprocessorOptions: {
        scss: {
          silenceDeprecations: ["legacy-js-api"]
        }
      }
    }
  });
