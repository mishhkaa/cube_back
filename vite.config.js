import { defineConfig } from 'vite'

const entries = ['fb-events', 'google-sheets', 'gads-conversions', 'tiktok-events', 'x-events'],
    utils = ['dataLayerEvent', 'helpers'];

const input = Object.fromEntries(
    [
        ...entries.map(name => [
            `resources/js/${name}/index.min.js`,
            `resources/js/${name}/index.js`,
        ]),
        ...utils.map(name => [
            `resources/js/utils/${name}.min.js`,
            `resources/js/utils/${name}.js`,
        ])
    ],
)

export default defineConfig({
    build: {
        rollupOptions: {
            input,
            output: {
                entryFileNames: '[name]',
                dir: process.cwd(),
            },
        },
        emptyOutDir: false,
        copyPublicDir: false,
    },
})
