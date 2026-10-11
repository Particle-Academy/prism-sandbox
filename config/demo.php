<?php

return [
    // Application-defined request budgets, not claims about Groq's plan limits.
    // Shared across every demo client/worker using this application database.
    // Failed provider attempts consume budget too; zero closes that window.
    'requests_per_minute' => (int) env('DEMO_REQUESTS_PER_MINUTE', 10),
    'requests_per_day' => (int) env('DEMO_REQUESTS_PER_DAY', 100),
];
