import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { XMLParser } from 'fast-xml-parser';
import { readFileSync } from 'fs';


let pluginName;

const xmlParser = new XMLParser();
const manifestText = readFileSync('plugin.xml', 'utf8');
const manifest = xmlParser.parse(manifestText);
// <plugin> is the 0.12 root element, <info> the legacy one
pluginName = (manifest?.plugin ?? manifest?.info)?.name;

if(!pluginName) {
    throw new Error('plugin.xml does not contain a name');
}
// Check if Vite is running in development mode
const isDev = process.env.NODE_ENV === 'development';
export default defineConfig({
    plugins: [vue()],
    define: {
        // Sometimes dependencies require the NODE_ENV which is not provided by Vite
        // this will set the process env for all imports (currently required for vee-validate)
        'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV || 'production'),
        'process.env': {}
    },
    build: {
        sourcemap: true,
        lib: {
            // We run the 'playground' when using the vite dev server
            // otherwise we build directly using the main.js
            entry: isDev ? 'src/js/_dev/app.js' : 'src/main.js',
            name: pluginName,
            fileName: (format) => `${pluginName.toLowerCase()}.${format}.js`
        },
        rollupOptions: {
            external: ['vue'],
            output: {
                globals: {
                    vue: 'Vue'
                }
            }
        }
    },
});
