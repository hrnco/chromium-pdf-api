FROM php:8.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates wget gnupg unzip curl \
    chromium \
    # runtime libraries required for Chromium
    libnss3 libx11-6 libxext6 libxrender1 libxcomposite1 libxcursor1 \
    libxdamage1 libxi6 libpango-1.0-0 libpangocairo-1.0-0 libcups2 \
    libatk1.0-0 libatk-bridge2.0-0 libgtk-3-0 libasound2 \
    # fallback fonts for Western scripts
    fonts-dejavu fonts-liberation \
    # Noto family: broad script coverage + emoji
    fonts-noto fonts-noto-cjk fonts-noto-color-emoji fonts-noto-extra \
    # Thai fonts (sometimes render better than Noto)
    fonts-thai-tlwg \
 && rm -rf /var/lib/apt/lists/*

RUN sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#g' /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY ./ ./

HEALTHCHECK --interval=10s --timeout=5s --retries=5 \
  CMD sh -c 'code=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/ || true); [ "$code" = "200" ] || [ "$code" = "405" ] || exit 1'
