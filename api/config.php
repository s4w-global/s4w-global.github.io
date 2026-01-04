<?php
return [
  'db' => [
    'host' => '194.213.127.121',
    'name' => 'h_00077517_s4w-DB',
    'user' => 'h_00077517_s4w-usr',
    'pass' => 'TDLrzook43@v%q8q935)*Q@nv084nwss7435-hasd',
    'charset' => 'utf8mb4'
  ],

  // Environment: 'poc' or 'prod'
  'env' => 'poc',

  // App-level secret for hashing. Rotate before production.
  'app_secret' => 'CHANGE_ME_TO_RANDOM_64_CHARS',

  // Token TTL in seconds (short-lived)
  'token_ttl' => 900, // 15 minutes

  // Session TTL for POC mode (seconds)
  'session_ttl' => 86400, // 24 hours

  // Default limits (can be overridden in DB settings via admin portal)
  'default_max_sessions_per_poc' => 1,

  // Security/Privacy headers
  'privacy_headers' => [
    'enabled' => true,
    // Use strict CSP in production after testing. Keep inline scripts minimal.
    'csp' => "default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https:; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://challenges.cloudflare.com; connect-src 'self' https://challenges.cloudflare.com; frame-src https://challenges.cloudflare.com; base-uri 'self'; form-action 'self'; object-src 'none'",
  ]

  // Access model per env:
  // - poc  : token issuance requires a valid POC cookie (select pilot group)
  // - prod : token issuance is open (production). Keep anti-abuse enabled.
  'access_mode' => [
    'poc'  => 'poc',
    'prod' => 'public'
  ],

  // Turnstile profiles per env (Cloudflare generates site_key + secret_key)
  'turnstile' => [
    'poc' => [
      'enabled' => false,
      'site_key' => '',
      'secret_key' => '',
      'protect_reports' => true,
      'protect_panic' => false,
      // Policy: if Turnstile verification fails due to network/provider issues
      // - 'fail_closed' blocks submission (safer against abuse)
      // - 'fail_open' allows submission (better UX, weaker anti-abuse)
      'failure_policy' => 'fail_closed'
    ],
    'prod' => [
      'enabled' => true,
      'site_key' => '',
      'secret_key' => '',
      'protect_reports' => true,
      'protect_panic' => false,
      'failure_policy' => 'fail_closed'
    ]
  ],

  // Privacy
  'masking' => [
    'report_geohash' => 8,
    'panic_geohash'  => 6,
    'panic_ttl_min'  => 15
  ]
];