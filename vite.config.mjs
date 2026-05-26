import { createViteConfig } from "vite-config-factory";

const entries = {
        'css/modularity-folder-browser':                './source/sass/modularity-folder-browser.scss',
        'js/modularity-folder-browser':                 './source/js/modularity-folder-browser.js',
        'js/modularity-folder-browser-admin':           './source/js/modularity-folder-browser-admin.js',
};

export default createViteConfig(entries, {
	outDir: "assets/dist",
	manifestFile: "manifest.json",
});
