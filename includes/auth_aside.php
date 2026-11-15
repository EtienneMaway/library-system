<?php

// The lamp-lit panel beside every sign-in form. Set $aside_note before including it to change the line under the title.
$aside_note = $aside_note ?? 'Every book in its place, every loan back on time.';
?>
<aside class="auth__aside" aria-hidden="true">
    <div class="auth__glow"></div>
    <p class="auth__brand">Library</p>
    <p class="auth__note"><?= e($aside_note) ?></p>
    <div class="auth__shelf">
        <span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span>
    </div>
</aside>
