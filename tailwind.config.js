module.exports = {
  content: [
    './index.php',
    './ajax/**/*.php',
    './components/**/*.php',
    './print_*.php',
    './resources/js/**/*.js'
  ],
  theme: {
    extend: {
      colors: {
        shell: '#eef3f8',
        ink: '#0f172a'
      },
      boxShadow: {
        soft: '0 24px 60px rgba(15,23,42,.08)'
      }
    }
  },
  plugins: []
};
