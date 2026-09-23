/*
 * Same config as tailwind.config.js, with the resources/js glob narrowed to what iris
 * reaches so the grp backoffice and retina classes stay out of the stylesheet iris serves
 * to shoppers. The grid blocks build `grid-cols-${per_row}` from CMS data, which no scan
 * can see, so the range is safelisted.
 */

const base = require('./tailwind.config.js');
const { collectModules } = require('./app-module-graph.cjs').iris;

const ALL_COMPONENTS_GLOB = './resources/js/**/*.vue';
const IRIS_OWNED_GLOBS = [
    './resources/js/Iris/**/*.vue',
    './resources/js/Layouts/Iris.vue',
    './resources/js/Layouts/Iris/**/*.vue',
    './resources/js/Pages/Iris/**/*.vue',
    './resources/js/Common/**/*.vue',
];

module.exports = {
    ...base,
    content: [
        ...base.content.filter((entry) => entry !== ALL_COMPONENTS_GLOB),
        ...IRIS_OWNED_GLOBS,
        ...[...collectModules()].filter((file) => file.endsWith('.vue')),
    ],
    safelist: [
        ...(base.safelist ?? []),
        ...Array.from({ length: 12 }, (_, index) => `grid-cols-${index + 1}`),
    ],
};
