<?php
require_once(__DIR__ . '/config.php');
require_login();

// Autoriser explicitement le microphone dans l'en-tête HTTP de Moodle
header("Permissions-Policy: microphone=(self \"http://localhost:3001\"), camera=(self \"http://localhost:3001\")");

$PAGE->set_url(new moodle_url('/ai.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('MadouGPT');
$PAGE->set_heading('MadouGPT');
// Détecte automatiquement localhost (sur PC) ou l'IP Wi-Fi (sur téléphone)
$host = explode(':', $_SERVER['HTTP_HOST'])[0];
$webui_url = "http://" . $host . ":3001/?model=madougpt";

echo $OUTPUT->header();
?>
<div style="width: 100%; height: calc(100vh - 160px); margin: 0; padding: 0;">
    <iframe src="http://localhost:3001"
        allow="microphone; camera; clipboard-read; clipboard-write; autoplay"
        style="width: 100%; height: 100%; border: none; border-radius: 8px;">
    </iframe>
</div>
<?php
echo $OUTPUT->footer();
