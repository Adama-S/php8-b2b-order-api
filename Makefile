up:
	docker compose up -d --build

stop:
	docker compose down

logs:
	docker compose logs -f api

shell:
	docker compose exec api bash

test:
	docker compose exec api composer test
