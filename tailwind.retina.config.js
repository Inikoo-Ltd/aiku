/*
 * Same config as tailwind.config.js, with the resources/js glob narrowed to what retina
 * reaches so the grp backoffice classes stay out of the stylesheet retina serves. Retina
 * renders iris blocks too, and those arrive through the module graph rather than a glob.
 * The grid blocks build `grid-cols-${per_row}` from CMS data, which no scan can see, so
 * the range is safelisted.
 */

const base = require('./tailwind.config.js');
const { collectModules } = require('./app-module-graph.cjs').retina;

const ALL_COMPONENTS_GLOB = './resources/js/**/*.vue';
const RETINA_OWNED_GLOBS = [
    './resources/js/Pages/Retina/**/*.vue',
    './resources/js/Layouts/Retina*.vue',
    './resources/js/Layouts/Retina/**/*.vue',
    './resources/js/Common/**/*.vue',
];

module.exports = {
    ...base,
    content: [
        ...base.content.filter((entry) => entry !== ALL_COMPONENTS_GLOB),
        ...RETINA_OWNED_GLOBS,
        ...[...collectModules()].filter((file) => file.endsWith('.vue')),
    ],
    safelist: [
        ...(base.safelist ?? []),
        ...Array.from({ length: 12 }, (_, index) => `grid-cols-${index + 1}`),
    ],
};
