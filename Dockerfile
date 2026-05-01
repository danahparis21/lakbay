FROM php:8.2-apache

# Install pdo_mysql, Tesseract OCR, and ImageMagick
RUN apt-get update && apt-get install -y \
    libmariadb-dev \
    tesseract-ocr \
    tesseract-ocr-eng \
    tesseract-ocr-script-latn \
    imagemagick \
    && docker-php-ext-install pdo pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Allow ImageMagick to read PDF/PS files (optional)
RUN sed -i 's/rights="none" pattern="PDF"/rights="read" pattern="PDF"/' /etc/ImageMagick-6/policy.xml || true

RUN a2enmod rewrite

RUN mkdir -p /var/www/html/uploads/guide_registrations \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 755 /var/www/html/uploads

WORKDIR /var/www/html