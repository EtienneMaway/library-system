<?php

// The panel beside the sign-in forms. Set $aside_note first to change its line of text.
$aside_note = $aside_note ?? 'Every book in its place, every loan back on time.';
?>
<aside class="auth__aside" aria-hidden="true">
    <p class="auth__brand">Library</p>
    <p class="auth__note"><?= e($aside_note) ?></p>
    <div class="auth__shelf"></div>
</aside>
