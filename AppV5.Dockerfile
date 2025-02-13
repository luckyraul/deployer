FROM debian:bookworm-slim

MAINTAINER Nikita Tarasov <nikita@mygento.com>

ENV DEBIAN_FRONTEND=noninteractive VAULT_VERSION=1.18.3 NOMAD_VERSION=1.8.4 LEVANT_VERSION=0.3.3 NOMADPACK_VERSION=0.1.0

RUN apt-get -qq update && \
  apt-get install -qqy locales && apt-get clean && \
  dpkg-reconfigure locales && \
  locale-gen en_US.UTF-8 && \
  echo 'en_US.UTF-8 UTF-8' >> /etc/locale.gen && \
  locale-gen

RUN apt-get install -qqy sudo git jq unzip ruby && \
    apt-get clean && \
    gem install specific_install && \
    gem install --no-document bundler && \
    gem specific_install https://github.com/luckyraul/mina.git relative_path

RUN apt-get -qqy install curl wget \
  && wget -qO- https://deb.nodesource.com/setup_20.x | bash - \
  && apt-get -qqy install nodejs \
  && apt-get clean \
  && npm install --global npm \
  && npm install --global yarn \
  && npm install --global gulp-cli

COPY --from=hairyhenderson/gomplate:v3.11.7 /gomplate /bin/gomplate

RUN wget -q https://releases.hashicorp.com/vault/${VAULT_VERSION}/vault_${VAULT_VERSION}_linux_amd64.zip && \
    unzip vault_${VAULT_VERSION}_linux_amd64.zip && \
    mv vault /usr/local/bin/vault && \
    chmod +x /usr/local/bin/vault && \
    rm vault_${VAULT_VERSION}_linux_amd64.zip

RUN wget -q https://releases.hashicorp.com/nomad/${NOMAD_VERSION}/nomad_${NOMAD_VERSION}_linux_amd64.zip && \
    unzip -o nomad_${NOMAD_VERSION}_linux_amd64.zip && \
    mv nomad /usr/local/bin/nomad && \
    chmod +x /usr/local/bin/nomad && \
    rm nomad_${NOMAD_VERSION}_linux_amd64.zip

RUN wget -q https://releases.hashicorp.com/nomad-pack/${NOMADPACK_VERSION}/nomad-pack_${NOMADPACK_VERSION}_linux_amd64.zip && \
    unzip nomad-pack_${NOMADPACK_VERSION}_linux_amd64.zip && \
    mv nomad-pack /usr/local/bin/nomad-pack && \
    chmod +x /usr/local/bin/nomad-pack && \
    rm nomad-pack_${NOMADPACK_VERSION}_linux_amd64.zip

RUN wget -q https://releases.hashicorp.com/levant/${LEVANT_VERSION}/levant_${LEVANT_VERSION}_linux_amd64.zip && \
    unzip levant_${LEVANT_VERSION}_linux_amd64.zip && \
    mv levant /usr/local/bin/levant && \
    chmod +x /usr/local/bin/levant && \
    rm levant_${LEVANT_VERSION}_linux_amd64.zip

RUN apt-get -qqy install curl apt-transport-https lsb-release ca-certificates \
  && curl -ssL -o /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg \
  && sh -c 'echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list' \
  && apt-get -qq update \
  && apt-get -qqy install php8.3-cli php8.3-curl php8.3-intl php8.3-xml php8.3-mbstring php8.3-gd php8.3-zip php8.3-imagick \
  && apt-get clean \
  && curl -L https://getcomposer.org/composer-2.phar -o /usr/local/bin/composer \
  && chmod +x /usr/local/bin/composer \
  && composer global config --no-plugins allow-plugins true \
  && composer global require phpro/grumphp \
  && composer global require php-parallel-lint/php-parallel-lint \
  && composer global require jumbojett/openid-connect-php \
  && composer global require symfony/console \
  && composer global require guzzlehttp/guzzle \
  && rm -fR ~/.composer/cache \
  && rm -fR ~/.cache/composer \
  && echo 'export PATH="$PATH:$HOME/.config/composer/vendor/bin"' >> ~/.bashrc

ADD composer.json /opt/deployer/composer.json
ADD bin /opt/deployer/bin/
ADD src /opt/deployer/src/

RUN cd /opt/deployer/ \
  && composer install --no-dev \
  && rm -fR ~/.composer/cache \
  && echo 'export PATH="$PATH:/opt/deployer/bin"' >> ~/.bashrc \
  && ln -s /opt/deployer/bin/deployer /usr/local/bin/deployer