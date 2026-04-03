install:
	git config core.hooksPath hooks
	chmod +x hooks/prepare-commit-msg hooks/commit-msg hooks/pre-push
	cd backend && composer install
	cd frontend && npm install

test:
	cd backend && vendor/bin/phpunit

lint:
	cd backend && vendor/bin/phpstan analyse src --level=8
	cd backend && vendor/bin/php-cs-fixer fix --dry-run --diff

fix:
	cd backend && vendor/bin/php-cs-fixer fix

deploy:
	cd backend && fly deploy
	cd frontend && vercel deploy --prod

.PHONY: install test lint fix deploy
