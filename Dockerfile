FROM debian:buster-slim

MAINTAINER Nikita Tarasov <nikita@mygento.ru>

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get -qq update && \
  apt-get install -qqy locales && apt-get clean && \
  dpkg-reconfigure locales && \
  locale-gen en_US.UTF-8 && \
  echo 'en_US.UTF-8 UTF-8' >> /etc/locale.gen && \
  locale-gen

RUN apt-get install -qqy sudo git jq unzip binutils ruby ruby-dev build-essential libxml2-utils dh-autoreconf && \
    apt-get clean && \
    gem install specific_install && \
    gem install --no-rdoc --no-ri bundler && \
    gem specific_install https://github.com/luckyraul/mina.git relative_path && \
    gem install scss_lint

RUN apt-get -qqy install curl wget gnupg2 \
  && curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | sudo apt-key add - \
  && echo "deb https://dl.yarnpkg.com/debian/ stable main" | sudo tee /etc/apt/sources.list.d/yarn.list \
  && wget -qO- https://deb.nodesource.com/setup_12.x | bash - \
  && apt-get -qqy install nodejs yarn \
  && apt-get clean \
  && npm install --global npm \
  && npm install --global gulp-cli

RUN apt-get -qqy install curl apt-transport-https lsb-release ca-certificates \
  && curl -ssL -o /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg \
  && sh -c 'echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list' \
  && apt-get -qq update \
  && apt-get -qqy install php7.3-cli php7.3-curl php7.3-intl php7.3-xml php7.3-mbstring php7.3-gd php7.3-zip \
  && apt-get clean \
  && curl -L https://getcomposer.org/composer-1.phar -o /usr/local/bin/composer \
  && chmod +x /usr/local/bin/composer \
  && composer global require phpro/grumphp \
  && composer global require php-parallel-lint/php-parallel-lint \
  && composer global require symfony/console \
  && composer global require guzzlehttp/guzzle && \
  && echo 'export PATH="$PATH:$HOME/.composer/vendor/bin"' >> ~/.bashrc