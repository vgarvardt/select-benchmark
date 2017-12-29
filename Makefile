build:
	docker-compose pull
	docker-compose build
	docker-compose up -d
	sleep 5
	docker-compose exec app composer install

generate:
	docker-compose exec app php generate.php

select:
	docker-compose exec app php select.php
