# Site.pro Marketing Studio

Yii2 demo for keyword imports, cleanup, ad grouping, copy preview, and Google Ads Editor CSV export.

## Run locally

Run `start-local.bat` from this directory and open <http://127.0.0.1:8010>. The app uses SQLite at `runtime/sitepro.sqlite`.
The local cookie validation key is generated in `runtime/cookie-validation-key` and is ignored by Git. Set `COOKIE_VALIDATION_KEY` to a strong secret in the hosting environment; do not commit it.

## Demo data and workflow

On first visit, the app imports four sample files from `data/samples`: Google Ads, Search Console, Ahrefs organic, and Ahrefs paid competitor keywords. A JSON example is also provided. Import CSV or JSON with a `keyword` or `query` field; volume, CPC, language/country, and final URL are optional. Select a fallback language when the file does not specify one.

Competitor paid terms remain visible for reference but are excluded from ad generation. The filters remove brand and forbidden terms, exact already-used keywords, and terms below the minimum volume. Eligible duplicates are merged by normalized keyword and language. Ad copy is localized for English, Lithuanian, German, Spanish, and French. The final URL is taken from source data or uses a language-specific Site.pro fallback.

CSV export is streamed and not written to disk. This unauthenticated demo is intended for local testing, not public production deployment.

## Render deployment

Create a Docker Web Service from this repository; Render will build the root `Dockerfile`. Set `COOKIE_VALIDATION_KEY` in the Render environment settings to a strong secret. The committed `runtime/sitepro.sqlite` is only the initial demo database: Render's free filesystem is ephemeral, so changes made by visitors can be lost after a restart or redeploy. Anyone can use this demo, so do not import private data or use it for production.
