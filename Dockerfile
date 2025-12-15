FROM oven/bun:1 AS builder

WORKDIR /app

COPY package.json bun.lock* ./

RUN bun install --frozen-lockfile

COPY . .

RUN bun --bun run build

FROM oven/bun:1-slim

WORKDIR /app

COPY --from=builder /app/build build/
COPY package.json .

RUN mkdir -p /app/data/audio

EXPOSE 3000

ENV NODE_ENV=production

CMD ["bun", "run", "build/index.js"]
