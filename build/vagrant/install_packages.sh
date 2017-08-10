#!/bin/bash -x

# Add PHP repo
add-apt-repository ppa:ondrej/php -y

apt-get update

apt-get install -y unzip build-essential
apt-get install -y php7.1-cli php7.1-fpm php7.1-common php7.1-dev php7.1-intl php7.1-sqlite3 php7.1-curl php7.1-xml php7.1-mysql php7.1-mbstring
