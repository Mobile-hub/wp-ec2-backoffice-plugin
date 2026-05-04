# Contributing

Thanks for your interest in contributing.

## Before You Start

- Read the main `README.md` for the repository layout.
- Keep the hybrid structure intact: root bootstrap + `src/` application code + `infrastructure/` assets.
- Do not commit secrets, local keypairs, or generated credentials.

## Development Workflow

1. Clone the repository.
2. Install dependencies:

```bash
composer install --working-dir=src
```

3. Run the helper commands as needed:

```bash
make check
make test
make build
```

## Pull Request Guidelines

- Keep changes focused and easy to review.
- Follow the existing coding style.
- Prefer English for code comments, docs, and user-facing strings.
- Explain any infrastructure or IAM changes clearly in the PR description.

## Commit Messages

Use a short conventional prefix when possible, for example:

- `feat: ...`
- `bug: ...`
- `refactor: ...`
- `doc: ...`