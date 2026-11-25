<?php
// Add the MySQL credentials you will share here.
// Supports both Docker environment variables and standard local settings.
return [
    "db" => [
        "host" => getenv("DB_HOST") ?: "127.0.0.1",
        "name" => getenv("DB_NAME") ?: "quizonline",
        "user" => getenv("DB_USER") ?: "root",
        "pass" => getenv("DB_PASS") !== false ? getenv("DB_PASS") : "",
        "port" => getenv("DB_PORT") ?: 3306,
    ],
    "openai_key" => getenv("OPENAI_KEY") ?: "",
    "openai_model" => getenv("OPENAI_MODEL") ?: "gpt-4o-mini",
    // Keep this private. It encrypts secrets saved through the admin dashboard.
    "app_secret" => "7e2590da52c346ac89bbd1675bf048d264653069d76eb796f18331bf9929194e",
];
