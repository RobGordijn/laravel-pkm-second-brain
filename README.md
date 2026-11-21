# PKM / Second Brain on Laravel

An opinionated personal knowledge management app ("second brain") built with Laravel and the [Laravel AI SDK](https://laravel.com/docs/ai-sdk). Dump a loose thought, a link or a to-do; the AI classifies it, writes a title and summary, adds tags and actions, finds related notes and suggests a project. Later you can ask questions across everything you saved and get answers with sources.

> [!WARNING]
> This is a prototype and a "this is how I do it" project, not a finished product. **There is no authentication**: every page is open to anyone who can reach the app. Run it locally or behind something that restricts access (a VPN, Tailscale, HTTP basic auth at the web server). Only the capture API is protected, by a bearer token.

## What it does

The workflow follows the CODE method: Capture, Organize, Distill, Express.

**Capture**
- **Inbox**: dump anything (a thought, a URL, a to-do), optionally with why you saved it.
- **Write**: write notes yourself in Markdown, with `[[wiki links]]` to other notes and autosave. The AI adds tags, summary and links, but leaves your text as it is.
- **Capture API**: send notes from an iOS Shortcut, a bookmarklet or a script.

**Process (by the AI, in the background)**
- Fetches the text of links you save.
- Classifies the note (note, idea, to-do, …) and asks you one short follow-up question when it is too vague.
- Analyzes it: title, summary, tags, actions and a suggested project.
- Finds connections: vector similarity selects candidates, an AI judge keeps the notes that are genuinely related.

**Organize**
- **Review**: accept or change the suggested project, or create a new one.
- **Projects and areas**: their open actions come first in Today.
- **Tags**: the AI spots duplicate tags and you merge them.
- **Weekly digest**: an overview of recent notes and open actions, with suggested projects for notes that belong together.

**Distill**
- After a note has been used a few times (opened, or cited in an answer), the AI suggests a one-line key insight that you can make your own.
- Rewrite notes in your own words, or hand them back to the AI to rework.

**Express**
- **Today**: open actions from your notes, projects first.
- **Ask**: hybrid search (full text + vectors) over your notes, with an AI answer that cites its sources. Save a good answer as a new note linked to its sources.
- **Export**: everything as Markdown, ready to open in Obsidian.

**System**
- **AI log and statistics**: every AI call with its tokens, duration, request and response.
- **How it works**: a live diagram of the whole flow.

## Tech stack

- Laravel 13 with the Laravel AI SDK (agents, structured output, embeddings, classification)
- PostgreSQL with pgvector (vector search) and built-in full-text search
- Blade and Tailwind CSS 4, built with Vite
- Pest for tests

## Requirements

- PHP 8.4 or higher
- Composer
- Node.js and npm
- PostgreSQL with the [pgvector](https://github.com/pgvector/pgvector) extension available. The migrations enable it, so the database user needs permission to create extensions.
- An API key for at least one AI provider supported by the Laravel AI SDK. The defaults use Gemini for text, embeddings and classification.
- A queue worker. Notes are processed by queued jobs.
- The scheduler, for the weekly digest and pruning the AI log.

## Installation

```bash
git clone https://github.com/RobGordijn/laravel-pkm-second-brain.git second-brain
cd second-brain
composer install
cp .env.example .env
php artisan key:generate
```

Create a PostgreSQL database, set the `DB_*` values in `.env`, and add your AI provider key(s) (see [Configuration](#configuration)). Then:

```bash
php artisan migrate
npm install
npm run build
```

## Running

For local development, run the web server, queue worker, log viewer and Vite together:

```bash
composer run dev
```

The app is then available at `http://localhost:8000`.

The scheduler is not part of `composer run dev`. Run it in a separate terminal if you want the weekly digest and AI log pruning:

```bash
php artisan schedule:work
```

Outside development, use a process manager (such as Supervisor) for `php artisan queue:work` and a cron entry for `php artisan schedule:run`, as described in the [Laravel deployment docs](https://laravel.com/docs/deployment).

### Scheduled tasks and commands

| Command | Schedule | What it does |
|---|---|---|
| `php artisan pkm:digest {--days=7}` | Mondays at 08:00 | Generates the AI overview of recent notes, open actions and related notes. |
| `php artisan model:prune --model="App\Models\AiRequest"` | Daily | Removes AI log entries older than the retention period. |

A digest can also be generated from the Digest page.

## Configuration

All settings are environment variables in `.env`. `.env.example` lists every one with a short explanation. The standard Laravel settings (app, database, queue, cache, mail and so on) work as usual; the app-specific ones are below.

### AI providers

| Variable | Default | Description |
|---|---|---|
| `AI_PROVIDER` | `gemini` | Provider for all agents: analysis, linking, answers, digest, tags and insights. Agents use the provider's cheapest model. |
| `AI_EMBEDDINGS_PROVIDER` | `gemini` | Provider for embeddings, used for semantic search and linking. |
| `AI_CLASSIFICATION_PROVIDER` | `gemini` | Provider for classification (note type, and whether a note is too vague). It must support classification in the Laravel AI SDK. |
| `GEMINI_API_KEY`, `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, … | | API keys. Only the keys of the providers you use are needed. Every supported provider and its variables are listed in `config/ai.php`. |

Embeddings are always requested with **1536 dimensions**, matching the `vector(1536)` column in the `items` migration. If you switch embedding provider or model, re-embed your existing notes and recalibrate the similarity thresholds below.

### App settings (`config/pkm.php`)

| Variable | Default | Description |
|---|---|---|
| `PKM_CAPTURE_TOKEN` | *(empty)* | Bearer token for the capture API. While it is empty, every capture request is rejected. |
| `PKM_VAGUE_THRESHOLD` | `0.7` | Probability (0–1) from which a new note counts as too vague, after which the app asks you one follow-up question. |
| `PKM_WRITE_PROCESSING_DELAY` | `120` | Seconds to wait after the first change to a note you write yourself before the AI pass runs, so a writing session triggers one AI run instead of one per autosave. |
| `PKM_LINK_CANDIDATE_MIN_SIMILARITY` | `0.6` | Minimum cosine similarity (0–1) for a note to become a link candidate. The AI then decides which candidates are genuinely related. Calibrated for `gemini-embedding-2`. |
| `PKM_SEARCH_MIN_SIMILARITY` | `0.6` | Minimum cosine similarity (0–1) for semantic search results. |
| `PKM_DISTILL_AFTER_USES` | `3` | Number of uses (opened, or cited in an answer) after which a note without a key insight invites you to write one. |
| `PKM_AI_LOG_RETENTION_DAYS` | `30` | Days the AI log keeps each call before it is pruned. |

A few values are fixed in `config/pkm.php` rather than read from the environment:

| Key | Value | Description |
|---|---|---|
| `embedding_dimensions` | `1536` | Size of the embeddings. Must match the vector column in the migration. |
| `link_candidate_limit` | `8` | Maximum number of candidates the link judge looks at per note. |
| `link_limit` | `5` | Maximum number of links per note. |

## Capture API

Send notes from outside the app, for example from an iOS Shortcut:

```bash
curl -X POST http://localhost:8000/api/capture \
  -H "Authorization: Bearer $PKM_CAPTURE_TOKEN" \
  -H "Accept: application/json" \
  -d raw="https://example.com/an-interesting-article" \
  -d why_saved="Background for the attic renovation"
```

| Field | Required | Description |
|---|---|---|
| `raw` | yes | The note: text or a URL, up to 50,000 characters. |
| `why_saved` | no | Why you saved it, up to 1,000 characters. |

The response is `201 Created` with the note's `id`, `status` and `url`. The endpoint allows 60 requests per minute.

## Testing

The tests need a PostgreSQL database with pgvector. By default they use a database named `testing` (set in `phpunit.xml`); every test run resets it, so never point it at a database with data you want to keep. AI calls are faked, so no API keys are needed.

```bash
php artisan test
```

To use a different test database, set `DB_DATABASE` in your environment when you run the tests:

```bash
DB_DATABASE=my_test_database php artisan test
```

The GitHub Actions workflow in `.github/workflows/tests.yml` runs the test suite and a Pint code style check on every push and pull request.

## Developing with AI agents

The repository includes the [Laravel Boost](https://laravel.com/docs/ai) guidelines (`AGENTS.md`, `CLAUDE.md`), the Boost MCP server configuration (`.mcp.json`) and the Boost skills (`.agents/skills`, `.claude/skills`). After `composer install`, run `php artisan boost:install` to set them up for your own agents and environment.

## License

Released under the [MIT License](LICENSE).
