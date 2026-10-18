# ADR-0001: Architecture, layout and languages

## Metadata
- Author: Aleksi Ursin / MCX
- Date: 2026-10-18
- Status: Accepted

## Context

mcxSerp is a PHP library (Composer package `magnacapax/mcx-serp`) that queries search engine results page (SERP) APIs through one interface and returns one normalised result model, whatever the provider. It ships two providers, `SerperProvider` and `SerpApiProvider`. It is a library, not an application: no CLI, no storage, no configuration files, no alerting. Consumers build those on top ([AGENTS.md](../../AGENTS.md), "What NOT to Build").

This repository had no decision records. `AGENTS.md` holds the original build specification. This ADR writes down the architecture as it is, so later ADRs have something to supersede, and records how the organisation-wide [engineering principles](../../development/engineering-principles.md) apply here.

## Options Considered
- Keep the specification in `AGENTS.md` as the only design record: rejected. It describes what was to be built, partly as code listings; it does not separate decisions from implementation detail, and parts of its directory tree no longer match `src/Http/`.
- Record the shipped architecture in one ADR, with later changes as new ADRs that supersede parts of it: chosen.

## Decision

### Layout and what each part owns

| Path | Owns |
|---|---|
| `src/Provider/` | `ProviderInterface` (the contract: `getName()`, `getSupportedEngines()`, `search()`) and one class per provider. A provider maps the library's request to the provider's API, maps the response into the models, and maps HTTP failures to the exception hierarchy. |
| `src/Model/` | `SearchResult`, `OrganicResult` and `SerpFeatures`: the normalised, immutable result. Anything the model does not cover stays available in `rawResponse` and `extra`. |
| `src/Enum/` | `Engine` and `Device`, backed enums. |
| `src/Exception/` | `SerpException` and its subclasses: `AuthenticationException`, `RateLimitException` (with `retryAfterSeconds`), `ProviderException`, `UnsupportedEngineException`, `NetworkException`. |
| `src/Http/` | `CurlHttpClient`, a minimal PSR-18 client on PHP's curl extension, with the minimal PSR-7 message classes it needs (`Message`, `Request`, `Response`, `Stream`, `Uri`) and its own client exceptions. Used when the consumer passes no client. |
| `tests/` | `run.php` and `bootstrap.php` (a test runner that needs no Composer install), `stubs/` (stand-ins for the PHPUnit and PSR interfaces), `Http/MockHttpClient.php`, `fixtures/` (saved provider responses), and tests per area under `Provider/` and `Model/`. |
| `composer.json`, `phpunit.xml` | Package metadata, PSR-4 autoloading (`MagnaCapax\Serp\` to `src/`), and a PHPUnit configuration. |

### Languages

The library is PHP because its consumers are PHP programs; principle 1 (PHP for logic, shell only as thin glue, no Python) and the product agree. There is no shell and no Python in the repository.

PHP version: `composer.json` requires PHP 8.1 or newer. The code uses what 8.1 brings: backed enums, `readonly` constructor-promoted properties, and `match`.

Dependencies: only the PSR HTTP interfaces at run time (`psr/http-client`, `psr/http-factory`, `psr/http-message`). Consumers bring their own PSR-18 client; without one, the bundled `CurlHttpClient` is used, which needs the curl extension. `composer.json` does not declare `ext-curl`.

### Build, run and test
- Build: none. Consumers install it with `composer require magnacapax/mcx-serp`.
- Run: there is nothing to run; a consumer constructs a provider with an API key and calls `search()` (see the [README](../../README.md)).
- Test: `php tests/run.php`. It loads the stubs and every test class, runs each `test*` method and exits non-zero on any failure. It needs no network and no Composer install; providers are tested against `MockHttpClient` and the fixtures. There is no CI.

### Conventions in use
- PSR-4 layout under `MagnaCapax\Serp\`: one class per file, file named after its class. Classes are UpperCamelCase, methods and properties camelCase (`findPosition`, `organicResults`, `retryAfterSeconds`).
- Classes are `final` unless other classes extend them (`SerpException`, the abstract `Message`, `ClientRequestException`). Models are immutable value objects with `readonly` properties.
- `jsonSerialize()` output uses snake_case keys (`total_results`, `organic_results`). That output is an interface consumers store and parse.
- No abstract base class for providers: the interface plus concrete classes ([AGENTS.md](../../AGENTS.md)).
- Every HTTP status outside 2xx raises an exception; 401 and 403 raise `AuthenticationException`, 429 raises `RateLimitException`, the rest `ProviderException`. A failure is never decoded into an empty result.
- Provider tests use saved real responses as fixtures and cover the error paths as well as the happy path.

### How the principles apply
1. **PHP, shell as glue:** as above.
2. **Files around 150 lines:** 3 of the 22 tracked files under `src/` were over 150 lines when this ADR was written: `SerpApiProvider.php` (337), `SerperProvider.php` (252) and `Stream.php` (179). Split a provider when changing it, for example by moving its response mapping into its own class, not in a sweep.
3. **camelCase, broad to specific:** as in the conventions. Class names and their file names are UpperCamelCase because PSR-4 autoloading maps the class name to the file name. The snake_case keys of `jsonSerialize()` stay, because renaming them breaks consumers (principle 7).
4. **KISS, DRY, YAGNI:** the "What NOT to Build" list in `AGENTS.md` is this principle applied: no CLI, no storage, no batch method, no provider base class until there is a need.
5. **Delete first:** before adding an option or a feature accessor, check that a consumer needs it; the raw response already carries everything else.
6. **Pit of success:** a provider works with only an API key; a lazily loaded results section is resolved by default and can be turned off; failures are typed exceptions, so a consumer cannot mistake a failed call for a real result.
7. **Never break old users:** `ProviderInterface`, the public model properties, the `jsonSerialize()` keys and the exception classes are the public API. Change them only with an ADR and a compatible path.
8. **Tests that can fail:** `php tests/run.php` before every commit. The non-2xx fix (commit `103d43b`, 2026-07-30) is the example: a quota-exhausted 400 response used to decode into zero results, and a consumer recorded "does not rank" for 16 days. The fix added tests for that path to both providers.
9. **Decisions are binding:** significant changes ship with an ADR in this directory.
10. **Fail soft, explain why:** in a library, failing soft means raising a precise, typed exception and leaving recovery to the consumer. Returning an empty or partial result after a failure is the one thing it must not do, because the consumer cannot tell it from a real answer.

## Consequences
- The library's design decisions are separated from its original build specification.
- Known gaps, recorded here rather than fixed in this ADR: three files are over the size target; `composer.json` does not declare `ext-curl` for the fallback client; there is no CI; the directory tree in `AGENTS.md` lists only `CurlHttpClient.php` under `src/Http/`.
- Any change to the public API, the provider contract or the language rule needs a new ADR that supersedes the relevant part of this one.

## References
- [AGENTS.md](../../AGENTS.md), [README.md](../../README.md), [composer.json](../../composer.json)
- [Engineering principles](../../development/engineering-principles.md)
