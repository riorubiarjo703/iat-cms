<?php

return [
    'panel_id' => null,
    'ai_enabled' => true,
    'ai_provider' => env('FILAMENT_STORY_AI_PROVIDER', 'openrouter'),
    'ai_model' => env('FILAMENT_STORY_AI_MODEL', 'openai/gpt-4o'),
    'ai_api_key' => env('FILAMENT_STORY_AI_API_KEY'),
    'ai_generation_timeout' => env('FILAMENT_STORY_AI_GENERATION_TIMEOUT', 180),
    'frontend_enabled' => true,
    'api_enabled' => true,
    'routes_prefix' => 'blogs',
    'pagination' => 10,
];
