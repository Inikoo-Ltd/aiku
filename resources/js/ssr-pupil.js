/*
 * Author: Raul Perusquia <raul@inikoo.com>  
 * Created: Thu, 15 Aug 2024 11:59:41 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/vue.m';
import PupilLayout from '@/Layouts/PupilLayout.vue';

const appName = 'pupil';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => `${title} - ${appName}`,
                         resolve: name => {
                             const pages = import.meta.glob('./Pages/Pupil/**/*.vue', { eager: true })
                             let page = pages[`./Pages/Pupil/${name}.vue`]
                             page.default.layout = page.default.layout || PupilLayout
                             return page
                         },        setup({ App, props, plugin }) {
            return createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .use(ZiggyVue, {
                    ...page.props.ziggy,
                    location: new URL(page.props.ziggy.location),
                });
        },
    })
);
