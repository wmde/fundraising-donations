FROM php:7.1-cli

RUN apt-get update \
	&& docker-php-ext-install -j$(nproc) pdo pdo_mysql \
	&& apt-get install -y php7.1-cli php7.1-fpm php7.1-common php7.1-dev php7.1-intl php7.1-sqlite3 php7.1-curl php7.1-xml php7.1-mysql php7.1-mbstring \
	&& apt-get install -y unzip build-essential
