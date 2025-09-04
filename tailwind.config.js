// tailwind.config.js
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './resources/views/**/*.blade.php',
    './resources/js/**/*.js',
    './app/View/**/*.php'
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui']
      }
    }
  },
  safelist: ['btn','btn--primary','btn--ghost','dot','dot-pending','dot-paid','dot-expired'],
  plugins: []
}
