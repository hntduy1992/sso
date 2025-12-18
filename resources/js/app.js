import {createApp, h} from 'vue'
import {createInertiaApp} from '@inertiajs/vue3'
import {vuetify} from "@/vuetify/index.js";
import DefaultLayout from "@/Pages/Layout/DefaultLayout.vue";

createInertiaApp({
    resolve: name => {
        const pages = import.meta.glob('./Pages/**/*.vue', {eager: true})
        let page = pages[`./Pages/${name}.vue`]
        page.default.layout = DefaultLayout
        return pages[`./Pages/${name}.vue`]
    },
    setup({el, App, props, plugin}) {
        createApp({render: () => h(App, props)})
            .use(plugin)
            .use(vuetify)
            .mount(el)
    },
})
