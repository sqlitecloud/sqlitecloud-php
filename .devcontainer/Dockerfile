FROM mcr.microsoft.com/devcontainers/php:7-bullseye

ADD https://dl.yarnpkg.com/debian/pubkey.gpg /etc/apt/trusted.gpg.d/yarn.asc

RUN chmod +r /etc/apt/trusted.gpg.d/*.asc && \
    echo "deb http://dl.yarnpkg.com/debian/ stable main" > /etc/apt/sources.list.d/yarn.list

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "if (hash_file('sha384', 'composer-setup.php') === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php --1 && \
    php -r "unlink('composer-setup.php');" && \
    mv composer.phar /usr/local/bin/composer