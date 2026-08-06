import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import { fr } from 'vuetify/locale'

/**
 * Palette délibérément sobre : le vert et le rouge portent une information
 * (accès ouvert ou coupé), ils ne doivent pas être noyés dans une interface
 * déjà colorée.
 */
export default createVuetify({
  locale: {
    locale: 'fr',
    messages: { fr },
  },
  theme: {
    defaultTheme: 'light',
    themes: {
      light: {
        colors: {
          primary: '#1f4e79',
          error: '#b3261e',
          success: '#1b5e20',
          warning: '#8a6100',
          surface: '#ffffff',
          background: '#f6f7f9',
        },
      },
    },
  },
  defaults: {
    VTextField: { variant: 'outlined', density: 'comfortable', hideDetails: 'auto' },
    VTextarea: { variant: 'outlined', density: 'comfortable', hideDetails: 'auto' },
    VSelect: { variant: 'outlined', density: 'comfortable', hideDetails: 'auto' },
    VBtn: { variant: 'flat' },
  },
})
