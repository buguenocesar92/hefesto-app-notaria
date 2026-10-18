<template>
  <div class="pagina">
    <slot />
  </div>
</template>

<style>
* {
  box-sizing: border-box;
}

body {
  margin: 0;
  background-color: #f9fafb;
  color: #111827;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial,
    sans-serif;
  -webkit-font-smoothing: antialiased;
}

a {
  color: #1d4ed8;
  text-decoration: none;
}

a:hover {
  text-decoration: underline;
}
</style>