FROM php:8.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates wget gnupg unzip \
    chromium \
    # runtime libky pre Chrome
    libnss3 libx11-6 libxext6 libxrender1 libxcomposite1 libxcursor1 \
    libxdamage1 libxi6 libpango-1.0-0 libpangocairo-1.0-0 libcups2 \
    libatk1.0-0 libatk-bridge2.0-0 libgtk-3-0 libasound2 \
    # fallback fonts pre západné písma
    fonts-dejavu fonts-liberation \
    # Noto rodina: veľké pokrytie skriptov + emoji
    fonts-noto fonts-noto-cjk fonts-noto-color-emoji fonts-noto-extra \
    # thajské písma (niekedy lepší rendering ako Noto)
    fonts-thai-tlwg \
 && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

RUN sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#g' /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY ./ ./
