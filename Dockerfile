FROM oven/bun:1 AS builder

RUN apt-get update && apt-get install -y python3 make g++ && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY package.json bun.lock* ./

RUN bun install --frozen-lockfile

COPY . .

RUN bun run build

FROM oven/bun:1-slim

WORKDIR /app

COPY --from=builder /app/build build/
COPY --from=builder /app/node_modules node_modules/
COPY package.json .

RUN mkdir -p /app/data/audio

EXPOSE 3000

ENV NODE_ENV=production

CMD ["bun", "run", "build/index.js"]
