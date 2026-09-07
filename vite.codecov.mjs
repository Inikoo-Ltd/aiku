import { codecovVitePlugin } from "@codecov/vite-plugin";

export const codecov = (bundleName) => codecovVitePlugin({
    enableBundleAnalysis: process.env.CODECOV_TOKEN !== undefined,
    bundleName,
    uploadToken         : process.env.CODECOV_TOKEN,
    telemetry           : false
});
