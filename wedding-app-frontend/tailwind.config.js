/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        'wedding-pink': '#F14E95',
        'wedding-gray': '#828282',
      },
      fontFamily: {
        'sacramento': ['Sacramento', 'cursive'],
        'work-sans': ['Work Sans', 'Arial', 'sans-serif'],
      },
      spacing: {
        'section': '7em',
      }
    },
  },
  plugins: [],
}
