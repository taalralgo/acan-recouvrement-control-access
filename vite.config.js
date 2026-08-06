import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';

/**
 * Les URL des polices et des images sont écrites dans le CSS au moment de la
 * compilation : contrairement aux balises générées par Laravel, elles ne
 * peuvent pas s'adapter ensuite au déploiement. Si l'application est servie
 * depuis un sous-dossier, elles doivent le connaître dès maintenant.
 *
 * Le sous-dossier est déjà dans APP_URL : on l'en déduit plutôt que d'exiger
 * une seconde variable, qu'il suffirait d'oublier pour perdre les icônes sans
 * message d'erreur explicite.
 */
function subfolderOf(appUrl) {
    if (!appUrl) {
        return '';
    }

    try {
        return new URL(appUrl).pathname.replace(/\/+$/, '');
    } catch {
        return '';
    }
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    // ASSET_URL explicite prime : il permet de servir les fichiers depuis un
    // CDN, cas où le chemin n'a rien à voir avec celui de l'application.
    if (!process.env.ASSET_URL) {
        const subfolder = subfolderOf(env.APP_URL);

        if (subfolder) {
            process.env.ASSET_URL = subfolder;
        }
    }

    return {
        plugins: [
            laravel({
                input: ['resources/js/app.js'],
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            // Ne compile que les composants Vuetify réellement utilisés.
            vuetify({ autoImport: true }),
        ],
        server: {
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
