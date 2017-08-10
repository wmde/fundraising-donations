#!/bin/bash

cd /vagrant

composer install --no-interaction

mkdir -p var/cache
mkdir -p var/log

cd -
