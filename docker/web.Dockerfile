# syntax=docker/dockerfile:1
# Build context: ./web. Builds the Vue SPA and serves it with Nginx.

FROM node:22-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .

ARG VITE_API_URL=http://localhost:8002
ARG VITE_REVERB_APP_KEY=teamboard-key
ARG VITE_REVERB_HOST=localhost
ARG VITE_REVERB_PORT=8085
ARG VITE_REVERB_SCHEME=http
ENV VITE_API_URL=$VITE_API_URL VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME
RUN npm run build

FROM nginx:1.27-alpine
COPY --from=build /app/dist /usr/share/nginx/html
# Single-page app: every unknown path serves index.html so Vue Router can handle it.
RUN printf 'server {\n  listen 80;\n  root /usr/share/nginx/html;\n  location /assets/ { expires 1y; add_header Cache-Control "public, immutable"; }\n  location / { try_files $uri $uri/ /index.html; }\n}\n' > /etc/nginx/conf.d/default.conf
