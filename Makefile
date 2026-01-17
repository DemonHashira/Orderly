.PHONY: gum dev install

gum: ## Open interactive dev menu
	@./scripts/dev.sh

dev: gum

install: ## Install all dependencies (API + Web)
	@echo "📦 Installing root dependencies..."
	@npm install
	@echo "📦 Installing API dependencies (Composer)..."
	@cd services/api && composer install
	@echo "📦 Installing Web dependencies (npm)..."
	@cd services/web && npm install
	@echo "✅ Installation complete!"
