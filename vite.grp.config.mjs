/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Sep 2023 12:16:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import i18n from "laravel-vue-i18n/vite";
import { withSharedLangFiles, englishOverridesOnly, faPerIconImports, FA_COMMONJS_OPTIONS, langFilter, serverPlainStrings } from "./vite.app-plugins.mjs";
import { fileURLToPath, URL } from "node:url";
import path from "node:path";
import { createRequire } from "node:module";

/* A plain import would be inlined by the esbuild pass vite runs over this config, breaking
 * the helper's own require calls. */
const grpModuleGraph = createRequire(path.join(process.cwd(), "vite.grp.config.mjs"))("./app-module-graph.cjs").grp;

export default ({ mode }) => {
  process.env = { ...process.env, ...loadEnv(mode, process.cwd()) };

  return defineConfig(
    {
      cacheDir: "node_modules/.vite-grp",
      server : {
        cors : true,
        watch: {
          usePolling: false,
          ignored: ["**/storage/media/**"]
        }
      },
      plugins: [
        laravel({
                  hotFile       : "public/grp.hot",
                  buildDirectory: "grp",
                  input         : "resources/js/app-grp.js",
                  ssr           : "resources/js/ssr-grp.js",
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
        langFilter(grpModuleGraph, serverPlainStrings()),
        englishOverridesOnly(),
        faPerIconImports()
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

      build: {
        sourcemap    : true,
        reportCompressedSize: false,
        devSourcemap : true,
        commonjsOptions: FA_COMMONJS_OPTIONS,
        rollupOptions: {
          output: {
            assetFileNames(assetInfo) {
              const assetName = assetInfo.names?.[0] ?? assetInfo.name ?? "";

              return assetName.endsWith(".mjs")
                ? "assets/[name]-[hash].js"
                : "assets/[name]-[hash][extname]";
            },
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
      css  : {
        preprocessorOptions: {
          scss: {
            silenceDeprecations: ["legacy-js-api"]
          }
        }
      }
    }
  );
}
