/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './public/**/*.html',
    './public/js/**/*.js',
  ],
  theme: {
    extend: {
      fontFamily: {
        'sans': ['Poppins', 'sans-serif'],
      },
      colors: {
        brand: {
          dark: '#111811',
          blue: '#1152d4',
          blueHover: '#0d3fa3',
          bg: '#F0F4F8',
        },
      },
    },
  },
  plugins: [],
}
