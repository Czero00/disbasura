<?php
$leaderPrefs = get_preferences((int)($_SESSION['user_id'] ?? 0));
$leaderLangCode = ($leaderPrefs['language'] ?? 'en') === 'fil' ? 'fil' : 'en';
$leaderLangTag = $leaderLangCode === 'fil' ? 'fil' : 'en';
$leaderIsDark = !empty($leaderPrefs['dark_mode']);
$leaderT = get_lang((int)($_SESSION['user_id'] ?? 0));
