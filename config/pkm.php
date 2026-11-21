<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Capture API Token
    |--------------------------------------------------------------------------
    |
    | Bearer token required by the capture API (iOS Shortcut, bookmarklet).
    |
    */

    'capture_token' => env('PKM_CAPTURE_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Embeddings
    |--------------------------------------------------------------------------
    |
    | Must match the vector column in the items migration. Every provider is
    | asked for this size explicitly (Gemini defaults to 3072, OpenAI 1536).
    |
    */

    'embedding_dimensions' => 1536,

    /*
    |--------------------------------------------------------------------------
    | Feedback Loop
    |--------------------------------------------------------------------------
    |
    | When the classifier's probability that a dump is too vague to be useful
    | reaches this threshold, the item asks the author one short question.
    |
    */

    'vague_threshold' => (float) env('PKM_VAGUE_THRESHOLD', 0.7),

    /*
    |--------------------------------------------------------------------------
    | Writing
    |--------------------------------------------------------------------------
    |
    | Notes the author writes are autosaved while typing. The AI pass (tags,
    | summary, links) waits this many seconds after the first change, so a
    | writing session costs one AI run instead of one per autosave.
    |
    */

    'write_processing_delay' => (int) env('PKM_WRITE_PROCESSING_DELAY', 120),

    /*
    |--------------------------------------------------------------------------
    | Linking
    |--------------------------------------------------------------------------
    |
    | Vector similarity selects candidates; the LinkJudge agent then decides
    | which are genuinely related. The candidate threshold is calibrated for
    | gemini-embedding-2, which scores unrelated notes around 0.40-0.60
    | and loosely related ones (two personal errands) around 0.75.
    | Recalibrate when switching embedding models.
    |
    */

    'link_candidate_min_similarity' => (float) env('PKM_LINK_CANDIDATE_MIN_SIMILARITY', 0.6),

    'link_candidate_limit' => 8,

    'link_limit' => 5,

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    |
    | Minimum cosine similarity for semantic search results.
    |
    */

    'search_min_similarity' => (float) env('PKM_SEARCH_MIN_SIMILARITY', 0.6),

    /*
    |--------------------------------------------------------------------------
    | Distilling
    |--------------------------------------------------------------------------
    |
    | After this many uses (opened, or cited in an answer) a note without a
    | key insight invites the author to write one: notes get sharper where
    | they are actually used, instead of all up front.
    |
    */

    'distill_after_uses' => (int) env('PKM_DISTILL_AFTER_USES', 3),

    /*
    |--------------------------------------------------------------------------
    | AI Log
    |--------------------------------------------------------------------------
    |
    | Every AI call is logged with its token usage and pruned after the
    | retention period below.
    |
    */

    'ai_log_retention_days' => (int) env('PKM_AI_LOG_RETENTION_DAYS', 30),

];
