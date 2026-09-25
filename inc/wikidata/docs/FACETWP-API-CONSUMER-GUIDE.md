# Filtered Experiences API — Consumer Guide

Audience: external collaborators pulling data out of the site (e.g. into R/Shiny, Python, or any
other analysis/visualization tool). If you're a theme maintainer instead, see
[FACETWP-ARCHIVE-FILTERING.md](FACETWP-ARCHIVE-FILTERING.md) for how this endpoint is implemented.

## What this is

A single public, read-only, unauthenticated JSON endpoint that returns `user-experience` posts,
filterable by the same facets (Wikidata properties + taxonomies + free-text search) as the
`/user-experience/` archive's sidebar on the website. One request gets you both the matching
records **and** the full set of filter choices available, so you can build your own filter UI
(a Shiny `selectInput`, a Python dropdown, etc.) without scraping the site.

```
GET https://wonder-cat.org/wp-json/wondercat/v1/experiences
```

- No API key, login, or authentication of any kind.
- Only `GET` is supported (no writes).
- Only published experiences are ever returned.
- Responses are `application/json`.

## Request parameters

| Parameter | Type | Values | Notes |
|---|---|---|---|
| `wondercat_wd_instance` | array | QIDs, e.g. `q7725634` | "Instance of" (Wikidata P31) |
| `wondercat_wd_genre` | array | QIDs | Genre (P136) |
| `wondercat_wd_depicts` | array | QIDs | Depicts (P180) |
| `wondercat_wd_country` | array | QIDs | Country of Origin (P495, falls back to P17) |
| `wondercat_wd_language` | array | QIDs | Language (P407) |
| `wondercat_experience` | array | taxonomy slugs, e.g. `empathy` | "Experience" taxonomy |
| `wondercat_narrative_technology` | array | taxonomy slugs, e.g. `suspense` | "Narrative Technology" taxonomy |
| `wondercat_search` | string | free text | Matches title + `feature`/`title_of_creative_work`/`benefit_of_experience` |
| `page` | integer | default `1` | 1-indexed |
| `per_page` | integer | default `20`, max `100` | Values above 100 are clamped down to 100 |

**Important — Wikidata QIDs are lowercase in this API.** Wikidata itself uses `Q175173`, but
this endpoint's facet values are lowercased (`q175173`). Always lowercase the QID when filtering,
and treat it case-insensitively when comparing against the `wikidata` map in the response (see
below).

### Array parameter syntax

Use PHP/WordPress-style bracket syntax for every array parameter, and repeat the key for
multiple values (this is an OR within that one facet — see below):

```
?wondercat_wd_genre[]=q175173&wondercat_wd_genre[]=q744038
```

## Filter semantics

- **Multiple values within one facet param = OR.** `wondercat_wd_genre[]=q175173&wondercat_wd_genre[]=q744038` matches experiences tagged with *either* genre.
- **Multiple different facet params = AND.** `wondercat_wd_instance[]=q7725634&wondercat_wd_language[]=q1860` matches experiences that are *both* a literary work *and* in English.
- Facet values are always arrays of alternatives (OR), even when you only supply one value.

## Discovering available filter values

You don't need to already know every QID or taxonomy slug. **A single unfiltered request
already returns choices and counts for every registered facet**, in the top-level `facets`
object:

```
GET /wp-json/wondercat/v1/experiences?per_page=1
```

```json
{
  "facets": {
    "wondercat_wd_instance": {
      "label": "Instance of",
      "choices": [
        { "value": "q7725634", "label": "literary work", "count": 3 },
        { "value": "q7889", "label": "video game", "count": 2 }
      ]
    },
    "wondercat_experience": {
      "label": "Experience",
      "choices": [
        { "value": "empathy", "label": "Empathy", "count": 17 }
      ]
    }
  }
}
```

Use `choices[].value` as the parameter value and `choices[].label` for display in your own UI.
`choices[].count` is how many experiences currently match that value (given whatever else is
already selected in the request — recompute it per request if you want live counts as a user
narrows their selection, the same way the website's sidebar does).

## Response schema

```json
{
  "items": [ { /* one per matched experience, see below */ } ],
  "facets": { /* all registered facets: choices + counts, see above */ },
  "pager": {
    "page": 1,
    "per_page": 20,
    "total_rows": 312,
    "total_pages": 16
  }
}
```

### `items[]` fields

| Field | Type | Notes |
|---|---|---|
| `id` | integer | WordPress post ID |
| `title` | string | HTML entities decoded (e.g. `Baldur's Gate 3`, not `Baldur&#8217;s Gate 3`) |
| `permalink` | string | Full URL to the experience page |
| `featured_image` | string \| null | Large-size image URL, or `null` if none |
| `feature` | string | Feature quotation text |
| `benefit_of_experience` | string | |
| `title_of_creative_work` | string | |
| `wikidata_qid` | string \| null | Raw QID as stored on the post, or `null` |
| `experience` | array of `{id, name, slug, link}` | "Experience" taxonomy terms |
| `technology` | array of `{id, name, slug, link}` | "Narrative Technology" taxonomy terms |
| `wikidata` | object | See below |

`wikidata` is **always** an object with one key per registered Wikidata facet
(`wondercat_wd_instance`, `wondercat_wd_genre`, `wondercat_wd_depicts`, `wondercat_wd_country`,
`wondercat_wd_language`) — present even for experiences with no QID, in which case every value is
`[]`. Each populated entry is an array of:

```json
{ "qid": "Q7725634", "label": "literary work", "url": "https://www.wikidata.org/wiki/Q7725634" }
```

Because the shape is always the same object of arrays, this flattens predictably in R/Python —
you never have to special-case "no QID" records.

## Pagination

`per_page` is capped at 100, so pulling a full dataset means looping over `page` until you've
seen `pager.total_pages`:

- `pager.total_rows` — total matching experiences across all pages, for the *current* filter selection.
- `pager.total_pages` — `ceil(total_rows / per_page)`.

## Recipes

### 1. Fetch one filtered page

```sh
curl -s 'https://wonder-cat.org/wp-json/wondercat/v1/experiences?wondercat_wd_instance[]=q7725634&wondercat_wd_language[]=q1860&per_page=20&page=1'
```

### 2. Enumerate all facet choices (build a filter UI)

```sh
curl -s 'https://wonder-cat.org/wp-json/wondercat/v1/experiences?per_page=1' | jq '.facets'
```

**R:**

```r
library(httr)
library(jsonlite)

resp <- GET("https://wonder-cat.org/wp-json/wondercat/v1/experiences", query = list(per_page = 1))
facets <- content(resp, as = "parsed", simplifyVector = TRUE)$facets

# e.g. build choices for a Shiny selectInput on "Instance of"
instance_choices <- facets$wondercat_wd_instance$choices
setNames(instance_choices$value, instance_choices$label)
```

**Python:**

```python
import requests

resp = requests.get("https://wonder-cat.org/wp-json/wondercat/v1/experiences", params={"per_page": 1})
facets = resp.json()["facets"]

instance_choices = facets["wondercat_wd_instance"]["choices"]
# [{"value": "q7725634", "label": "literary work", "count": 3}, ...]
```

### 3. Pull the full (filtered) dataset for offline analysis

**R:**

```r
library(httr)
library(jsonlite)
library(purrr)

fetch_all_experiences <- function(...) {
  page <- 1
  all_items <- list()

  repeat {
    resp <- GET(
      "https://wonder-cat.org/wp-json/wondercat/v1/experiences",
      query = c(list(page = page, per_page = 100), list(...))
    )
    body <- content(resp, as = "parsed", simplifyVector = FALSE)
    all_items <- c(all_items, body$items)

    if (page >= body$pager$total_pages) break
    page <- page + 1
  }

  map_dfr(all_items, function(item) {
    data.frame(
      id = item$id,
      title = item$title,
      permalink = item$permalink,
      wikidata_qid = item[["wikidata_qid"]] %||% NA_character_,
      instance_of = paste(map_chr(item$wikidata$wondercat_wd_instance, "label"), collapse = "; "),
      genre = paste(map_chr(item$wikidata$wondercat_wd_genre, "label"), collapse = "; "),
      stringsAsFactors = FALSE
    )
  })
}

# All experiences that are literary works AND in English:
df <- fetch_all_experiences(`wondercat_wd_instance[]` = "q7725634", `wondercat_wd_language[]` = "q1860")
```

**Python:**

```python
import requests
import pandas as pd

def fetch_all_experiences(**filters):
    page = 1
    items = []

    while True:
        params = {"page": page, "per_page": 100, **filters}
        resp = requests.get(
            "https://wonder-cat.org/wp-json/wondercat/v1/experiences",
            params=params,
        )
        body = resp.json()
        items.extend(body["items"])

        if page >= body["pager"]["total_pages"]:
            break
        page += 1

    return pd.json_normalize(items)

# All experiences tagged with the "Empathy" experience taxonomy:
df = fetch_all_experiences(**{"wondercat_experience[]": "empathy"})
```

> `requests`/`httr` both accept repeated bracketed keys (`param[]`) as either a literal key
> passed multiple times with a list value, or (as above) a single dict/list entry — check your
> HTTP client's docs for how it serializes list-valued query params if a filter doesn't seem to
> apply.

## Gotchas

- **No authentication, no rate limiting.** Please cache results client-side (e.g. don't re-fetch
  the full dataset on every Shiny reactive tick) rather than hammering the endpoint.
- **Wikidata QIDs are lowercase** in both requests and the response's `wikidata[].qid` values —
  compare case-insensitively if you're cross-referencing against Wikidata's own API/QID casing.
- **`post_status` is always `publish`** — draft/private experiences never appear, regardless of
  who's asking (there's no login).
- **No versioning guarantee.** This is an internal WonderCat endpoint made public for
  convenience, not a formally versioned public API — if something looks broken or missing,
  contact the WonderCat maintainers rather than assuming it's permanent/stable.
