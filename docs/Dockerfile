# Static VitePress documentation site.
# Build stage compiles the site; final stage serves it via nginx on :80,
# fronted by Traefik in docker-compose.prod.yaml.
FROM node:22-alpine AS build

WORKDIR /docs
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run docs:build

FROM nginx:alpine AS serve

COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /docs/.vitepress/dist /usr/share/nginx/html
EXPOSE 80
