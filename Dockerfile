# ==============================================================================
# MIKHMON by NODERA - Official Container Image for MikroTik RouterOS v7 & Docker
# Optimized Single-Layer Image: 1 single download stream (no multi-layer timeouts)
# Supports Multi-Arch: linux/amd64 (x86_64), linux/arm64 (aarch64), linux/arm/v7
# ==============================================================================

FROM alpine:3.20 AS builder

# Install PHP 8.2 and necessary extensions
RUN apk add --no-cache \
    tzdata \
    curl \
    ca-certificates \
    php82 \
    php82-cli \
    php82-curl \
    php82-session \
    php82-json \
    php82-openssl \
    php82-sockets \
    php82-mbstring \
    php82-gd \
    php82-ctype \
    php82-fileinfo \
    php82-iconv \
    php82-simplexml \
    php82-xml \
    php82-dom \
    php82-zip \
    && ln -sf /usr/bin/php82 /usr/bin/php \
    && rm -rf /var/cache/apk/*

WORKDIR /app/mikhmon
COPY mikhmon /app/mikhmon

# ------------------------------------------------------------------------------
# Final Stage: Flatten into 1 single layer so MikroTik downloads in 1 single shot
# ------------------------------------------------------------------------------
FROM scratch
COPY --from=builder / /

WORKDIR /app/mikhmon
EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080"]
