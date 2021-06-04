FROM debian:buster-slim

MAINTAINER Nikita Tarasov <nikita@mygento.ru>

ENV DEBIAN_FRONTEND=noninteractive VAULT_VERSION=1.7.2 WAYPOINT_VERSION=0.4.0 NOMAD_VERSION=1.1.0

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

COPY --from=hairyhenderson/gomplate:v3.9.0 /gomplate /bin/gomplate

RUN wget -q https://releases.hashicorp.com/vault/${VAULT_VERSION}/vault_${VAULT_VERSION}_linux_amd64.zip && \
    unzip vault_${VAULT_VERSION}_linux_amd64.zip && \
    mv vault /usr/local/bin/vault && \
    chmod +x /usr/local/bin/vault && \
    rm vault_${VAULT_VERSION}_linux_amd64.zip

RUN wget -q https://releases.hashicorp.com/waypoint/${WAYPOINT_VERSION}/waypoint_${WAYPOINT_VERSION}_linux_amd64.zip && \
    unzip waypoint_${WAYPOINT_VERSION}_linux_amd64.zip && \
    mv waypoint /usr/local/bin/waypoint && \
    chmod +x /usr/local/bin/waypoint && \
    rm waypoint_${WAYPOINT_VERSION}_linux_amd64.zip

RUN wget -q https://releases.hashicorp.com/nomad/${NOMAD_VERSION}/nomad_${NOMAD_VERSION}_linux_amd64.zip && \
    unzip nomad_${NOMAD_VERSION}_linux_amd64.zip && \
    mv nomad /usr/local/bin/nomad && \
    chmod +x /usr/local/bin/nomad && \
    rm nomad_${NOMAD_VERSION}_linux_amd64.zip

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
  && composer global require jumbojett/openid-connect-php \
  && composer global require symfony/console \
  && composer global require guzzlehttp/guzzle \
  && rm -fR ~/.composer/cache \
  && echo 'export PATH="$PATH:$HOME/.composer/vendor/bin"' >> ~/.bashrc
