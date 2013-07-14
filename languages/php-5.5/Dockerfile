FROM jbgi/arch-base
MAINTAINER Spencer Rinehart <anubis@overthemonkey.com>

RUN sed -i 's/SigLevel *=.*/SigLevel = Never/' /etc/pacman.conf

RUN pacman -Syy
RUN pacman -S --noconfirm binutils gcc make

ADD php /tmp/php

RUN bash -c 'cd /tmp/php; makepkg -ci --asroot --noconfirm'

ADD php.ini /etc/php/php.ini
