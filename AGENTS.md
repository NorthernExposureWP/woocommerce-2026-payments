## Project Overview

- This repository is a practical WordPress and WooCommerce development environment used for learning, experimentation, and AI-assisted development.
- The project contains multiple independent educational plugins and development labs.
- Treat each plugin or lab as a separate unit unless the task explicitly involves integration between them.
- Preserve the existing project structure and avoid reorganizing unrelated components.
- Before working on a specific plugin or lab, inspect its existing files and understand its current implementation.
- Do not assume that every component in the repository is production-ready or intended for production use.
- Follow the requirements and constraints of the specific task rather than introducing functionality based on assumptions about the project's purpose.

## Technology Stack

- PHP: 8.4.
- WordPress: 7.1.2.
- WooCommerce: 11.1.2.
- Database: MySQL 9.
- Web server: Apache.
- Development environment: Docker Engine and Docker Compose.
- The project is containerized using Docker and should remain independent of the host operating system.
- Debugging: Xdebug 3.x.
- Dependency management: Composer.
- Version control: Git.

### Environment Notes

- WordPress core is installed using Composer (`johnpbloch/wordpress`).
- The WordPress document root is `/var/www/html/public`.
- `WP_CONTENT_DIR` points to the project-level `wp-content` directory.
- The project uses a custom Apache Alias for `/wp-content`.
- The local development site is exposed on host port `8080`.
- Use the versions and configuration defined by the project files as the source of truth if they differ from this overview.

## Architecture and Coding Conventions

- Treat each educational plugin as an independent component with its own responsibilities and lifecycle.
- Prefer plugin-based implementation for new WordPress functionality. Use theme-level code only for presentation or functionality that is strictly theme-dependent.
- Keep plugin code organized into clear, responsibility-focused classes and files.
- Use namespaces and autoloading where appropriate.
- Follow WordPress coding standards and API conventions when interacting with WordPress and WooCommerce.
- Prefer object-oriented design where it improves maintainability, but avoid unnecessary abstractions and design patterns.
- Do not introduce DTOs, interfaces, repositories, or additional architectural layers without a clear practical justification.
- Keep business logic separate from WordPress hooks and framework-specific integration code where reasonably practical.
- Use WordPress and WooCommerce APIs instead of directly accessing internal database structures or bypassing framework mechanisms.
- Follow existing project conventions rather than introducing a different architectural style without justification.

## WordPress and WooCommerce Development Rules

- Use current WordPress and WooCommerce APIs appropriate to the versions specified in this project.
- Prefer official WordPress and WooCommerce documentation when researching API behavior.
- Do not modify WordPress core or third-party plugin files. Extend their behavior through hooks and filters.
- Use WooCommerce CRUD APIs for accessing and modifying orders, products, customers, and other supported data objects.
- Ensure compatibility with High-Performance Order Storage (HPOS) when working with orders.
- When modifying checkout functionality, account for the Checkout Block and Store API where relevant.
- Do not assume that legacy PHP template-based checkout behavior applies to block-based checkout.
- Use WordPress HTTP API for external HTTP requests.
- Use appropriate WordPress and WooCommerce APIs for options, metadata, transients, logging, scheduling, and other platform-managed functionality.
- Apply appropriate security practices, including capability checks, nonce verification, input sanitization, and output escaping.
- Preserve compatibility with supported WordPress and WooCommerce extension mechanisms.
- Do not use deprecated APIs when supported alternatives are available.

## Testing and Verification

- Identify the appropriate verification steps before implementing changes.
- Follow the existing project's testing setup and conventions.
- Run relevant tests after making changes when the environment permits.
- Use PHP syntax checks and static analysis where appropriate.
- For WordPress and WooCommerce functionality, verify behavior in the actual application environment whenever practical.
- Do not assume that passing syntax checks guarantees correct runtime behavior.
- Do not modify tests merely to make them pass without verifying the underlying requirements.
- Do not introduce new testing frameworks or dependencies without a clear justification.
- If tests cannot be executed, explain why and identify what remains unverified.
- Report the checks performed and their results accurately. Never claim that a test passed unless it was actually executed successfully.