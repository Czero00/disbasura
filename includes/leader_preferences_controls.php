<?php
$leaderPrefs = $leaderPrefs ?? get_preferences((int)($_SESSION['user_id'] ?? 0));
$leaderIsDark = !empty($leaderPrefs['dark_mode']);
$leaderLanguage = ($leaderPrefs['language'] ?? 'en') === 'fil' ? 'fil' : 'en';
?>
<style>@import url('/disbasura/assets/css/leader-preferences.css');</style>
<script>document.documentElement.classList.toggle('dark', <?= $leaderIsDark ? 'true' : 'false' ?>);document.documentElement.lang=<?= json_encode($leaderLanguage === 'fil' ? 'fil' : 'en') ?>;</script>
<div class="leader-preference-controls" aria-label="Display preferences">
  <button type="button" id="leaderThemeButton" aria-label="Toggle dark or light mode">
    <?= $leaderIsDark ? '☀️ ' . ($leaderLanguage === 'fil' ? 'Maliwanag' : 'Light') : '🌙 ' . ($leaderLanguage === 'fil' ? 'Madilim' : 'Dark') ?>
  </button>
  <select id="leaderLanguageSelect" aria-label="<?= $leaderLanguage === 'fil' ? 'Wika' : 'Language' ?>">
    <option value="en" <?= $leaderLanguage === 'en' ? 'selected' : '' ?>>🇬🇧 EN</option>
    <option value="fil" <?= $leaderLanguage === 'fil' ? 'selected' : '' ?>>🇵🇭 FIL</option>
  </select>
</div>
<script>
(() => {
  const sendPreference = async (payload) => {
    const response = await fetch('/disbasura/api/leader-preferences.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload)
    });
    const result = await response.json();
    if (!response.ok || !result.ok) throw new Error(result.error || 'Could not save preference.');
    window.location.reload();
  };
  document.getElementById('leaderThemeButton')?.addEventListener('click', async (event) => {
    event.currentTarget.disabled = true;
    try { await sendPreference({toggle_dark: 1}); }
    catch (error) { event.currentTarget.disabled = false; alert(error.message); }
  });
  document.getElementById('leaderLanguageSelect')?.addEventListener('change', async (event) => {
    event.currentTarget.disabled = true;
    try { await sendPreference({language: event.currentTarget.value}); }
    catch (error) { event.currentTarget.disabled = false; alert(error.message); }
  });
})();
</script>
