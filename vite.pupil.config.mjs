/*
 * Author: Raul Perusquia <raul@inikoo.com>  
 * Created: Thu, 15 Aug 2024 11:59:41 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import i18n from "laravel-vue-i18n/vite";
import { withSharedLangFiles, englishOverridesOnly, faPerIconImports, FA_COMMONJS_OPTIONS, langFilter, serverPlainStrings, startUpVendorChunks } from "./vite.app-plugins.mjs";
import { fileURLToPath, URL } from "node:url";
import path from "node:path";
import tailwindcss from "tailwindcss";
import tailwindcssNesting from "tailwindcss/nesting";
import autoprefixer from "autoprefixer";
import { createRequire } from "node:module";

/* A plain import would be inlined by the esbuild pass vite runs over this config, breaking
 * the helper's own require calls. */
const pupilModuleGraph = createRequire(path.join(process.cwd(), "vite.pupil.config.mjs"))("./app-module-graph.cjs").pupil;

export default defineConfig(
  {
    cacheDir: "node_modules/.vite-pupil",
    server : {
      cors : true,
      watch: {
        ignored: ["**/storage/media/**", "**/vendor/**"]
      }
    },
    plugins: [
      laravel({
                hotFile       : "public/pupil.hot",
                buildDirectory: "pupil",
                input         : "resources/js/app-pupil.js",
                ssr           : "resources/js/ssr-pupil.js",
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
      withSharedLangFiles(i18n()),
      langFilter(pupilModuleGraph, serverPlainStrings()),
      englishOverridesOnly(),
      faPerIconImports()
    ],
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
      reportCompressedSize: false,
      devSourcemap : true,
      commonjsOptions: FA_COMMONJS_OPTIONS,
      rollupOptions: {
        output: {
          manualChunks: startUpVendorChunks(["sentry", "node_modules/primevue/"])
        }
      }
    },
    css    : {
      postcss: {
        plugins: [
          tailwindcssNesting,
          tailwindcss({ config: "tailwind.pupil.config.js" }),
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
