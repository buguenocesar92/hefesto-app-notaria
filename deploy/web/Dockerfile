# ---------------------------------------------------------------------------
# Imagen de la interfaz (Nuxt).
#
# Se compila en una etapa aparte y se copia solo el resultado. La imagen final
# no tiene el codigo fuente ni las dependencias de desarrollo: si el host se
# compromete, el atacante no gana acceso al codigo.
# ---------------------------------------------------------------------------

# --- Etapa 1: compilacion --------------------------------------------------
FROM node:22.21.1-bookworm-slim AS build

WORKDIR /app

RUN corepack enable

COPY package.json ./

# En un proyecto real, este archivo tambien se copia (package-lock.json).
RUN --mount=type=cache,target=/root/.npm npm install --no-audit --no-fund

COPY . .

ENV NODE_ENV=production
ENV NITRO_PRESET=node-server

RUN npm run build

# --- Etapa 2: ejecucion ----------------------------------------------------
FROM node:22.21.1-bookworm-slim AS runtime

WORKDIR /app

ENV NODE_ENV=production
ENV NITRO_PORT=3000
ENV NITRO_HOST=0.0.0.0

# Usuario sin privilegios.
RUN groupadd -r hefesto && useradd -r -g hefesto -d /app hefesto

COPY --from=build --chown=hefesto:hefesto /app/.output ./.output

USER hefesto

EXPOSE 3000

CMD ["node", ".output/server/index.mjs"]