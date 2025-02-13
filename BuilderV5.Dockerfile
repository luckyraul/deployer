FROM docker:26

MAINTAINER Nikita Tarasov <nikita@mygento.com>

ENV VAULT_VERSION=1.18.3 NOMAD_VERSION=1.8.4 LEVANT_VERSION=0.3.3 NOMADPACK_VERSION=0.1.0 GLIBC_VERSION=2.34-r0

COPY --from=hairyhenderson/gomplate:v3.11.7 /gomplate /bin/gomplate

RUN apk add --no-cache git

RUN wget -q https://releases.hashicorp.com/vault/${VAULT_VERSION}/vault_${VAULT_VERSION}_linux_amd64.zip && \
    unzip vault_${VAULT_VERSION}_linux_amd64.zip && \
    mv vault /usr/local/bin/vault && \
    chmod +x /usr/local/bin/vault && \
    rm vault_${VAULT_VERSION}_linux_amd64.zip

RUN wget -q https://releases.hashicorp.com/levant/${LEVANT_VERSION}/levant_${LEVANT_VERSION}_linux_amd64.zip && \
    unzip levant_${LEVANT_VERSION}_linux_amd64.zip && \
    mv levant /usr/local/bin/levant && \
    chmod +x /usr/local/bin/levant && \
    rm levant_${LEVANT_VERSION}_linux_amd64.zip

RUN wget -q -O /etc/apk/keys/sgerrand.rsa.pub https://alpine-pkgs.sgerrand.com/sgerrand.rsa.pub && \
    wget https://github.com/sgerrand/alpine-pkg-glibc/releases/download/${GLIBC_VERSION}/glibc-${GLIBC_VERSION}.apk && \
    apk add --no-cache --force-overwrite glibc-${GLIBC_VERSION}.apk && \
    rm glibc-${GLIBC_VERSION}.apk && \
    wget -q https://releases.hashicorp.com/nomad/${NOMAD_VERSION}/nomad_${NOMAD_VERSION}_linux_amd64.zip && \
    unzip -o nomad_${NOMAD_VERSION}_linux_amd64.zip && \
    mv nomad /usr/local/bin/nomad && \
    chmod +x /usr/local/bin/nomad && \
    /usr/local/bin/nomad -v && \
    rm nomad_${NOMAD_VERSION}_linux_amd64.zip

RUN wget -q https://releases.hashicorp.com/nomad-pack/${NOMADPACK_VERSION}/nomad-pack_${NOMADPACK_VERSION}_linux_amd64.zip && \
    unzip nomad-pack_${NOMADPACK_VERSION}_linux_amd64.zip && \
    mv nomad-pack /usr/local/bin/nomad-pack && \
    chmod +x /usr/local/bin/nomad-pack && \
    rm nomad-pack_${NOMADPACK_VERSION}_linux_amd64.zip

RUN apk add --no-cache php83-curl php83-iconv php83-mbstring php83-simplexml php83-openssl php83-phar php83-zip php83-xmlwriter php83-tokenizer curl php83-pecl-imagick && \
    # ln -s /usr/bin/php83 /usr/bin/php && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer && \
    composer global require symfony/console && \
    composer global require guzzlehttp/guzzle && \
    rm -fR ~/.composer/cache && \
    rm -fR ~/.cache/composer

RUN apk add --no-cache nodejs yarn npm

RUN apk add --no-cache ruby && \
    gem install specific_install && \
    gem install -N bundler && \
    gem specific_install https://github.com/luckyraul/mina.git relative_path

ADD composer.json /opt/deployer/composer.json
ADD bin /opt/deployer/bin/
ADD src /opt/deployer/src/

RUN mkdir -p /opt/deployer \
  && cd /opt/deployer/ \
  && composer install --no-dev \
  && rm -fR ~/.composer/cache \
  && rm -fR ~/.cache/composer \
  && echo 'export PATH="$PATH:/opt/deployer/bin"' >> ~/.bashrc \
  && ln -s /opt/deployer/bin/deployer /usr/local/bin/deployer
