# Use: make up, make down, make logs, make ps, make exec s=<service> c="<cmd>"
COMPOSE ?= docker compose

.PHONY: up down build start stop restart logs ps exec

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

build:
	$(COMPOSE) build

logs:
	$(COMPOSE) logs -f

ps:
	$(COMPOSE) ps