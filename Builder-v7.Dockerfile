FROM docker:29

MAINTAINER Nikita Tarasov <nikita@mygento.com>

ENV VAULT_VERSION=2.0.0 NOMAD_VERSION=2.0.0 LEVANT_VERSION=0.4.0 NOMADPACK_VERSION=0.4.2 GLIBC_VERSION=2.34-r0

COPY --from=hairyhenderson/gomplate:v4.3.3 /gomplate /bin/gomplate

RUN apk add --no-cache git

RUN wget -q https://releases.hashicorp.com/vault/${VAULT_VERSION}/vault_${VAULT_VERSION}_linux_amd64.zip && \
    unzip -o vault_${VAULT_VERSION}_linux_amd64.zip && \
    mv vault /usr/local/bin/vault && \
    chmod +x /usr/local/bin/vault && \
    rm vault_${VAULT_VERSION}_linux_amd64.zip

RUN wget -q https://releases.hashicorp.com/levant/${LEVANT_VERSION}/levant_${LEVANT_VERSION}_linux_amd64.zip && \
    unzip -o levant_${LEVANT_VERSION}_linux_amd64.zip && \
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
    unzip -o nomad-pack_${NOMADPACK_VERSION}_linux_amd64.zip && \
    mv nomad-pack /usr/local/bin/nomad-pack && \
    chmod +x /usr/local/bin/nomad-pack && \
    rm nomad-pack_${NOMADPACK_VERSION}_linux_amd64.zip

RUN apk add --no-cache php85-curl php85-iconv php85-mbstring php85-xml php85-simplexml php85-openssl php85-phar php85-zip php85-xmlwriter php85-tokenizer curl php85-pecl-imagick && \
    # ln -s /usr/bin/php85 /usr/bin/php && \
    curl -L https://getcomposer.org/download/latest-2.2.x/composer.phar -o /usr/local/bin/composer && \
    chmod +x /usr/local/bin/composer && \
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
